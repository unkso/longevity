<?php namespace wcf\system\cronjob;

use DateInterval;
use DateTime;
use wbb\data\board\BoardCache;
use wbb\data\post\PostAction;
use wbb\data\thread\Thread;
use wbb\data\thread\ThreadEditor;
use wcf\data\award\Award;
use wcf\data\award\issued\IssuedAward;
use wcf\data\cronjob\Cronjob;
use wcf\data\user\User;
use wcf\system\cache\builder\IssuedAwardCacheBuilder;
use wcf\system\template\TemplateEngine;
use wcf\system\WCF;
use wcf\util\DateUtil;

class LongevityReportCronjob extends AbstractCronjob
{
    /**
     * @see    \wcf\system\cronjob\ICronjob::execute()
     */
    public function execute(Cronjob $cronjob)
    {
        parent::execute($cronjob);

        // Get a sorted list of all clan members and longevity
        $users = array_map(function ($u) {
            $longevityDate = '';
            if ($u->userOption40 === '') {
                $longevityDate = $this->convertRegistrationToJoinDate($u->registrationDate);
            } else {
                $longevityDate = $u->userOption40;
            }
            return ['user' => $u, 'longevity' => $this->getLongevity($longevityDate)];
        }, $this->getClanMembers());

        // Generate lists for upcoming and past anniversaries as well as new recruits
        $lists = ['upcoming' => [], 'past' => [], 'recruits' => []];
        foreach ($users as $userSettings) {
            if ($userSettings['longevity']['anniversary'] > 0) $lists['upcoming'][] = $userSettings;
            else {
                if ($userSettings['longevity']['y'] > 0 && $userSettings['longevity']['anniversary'] < 0) $lists['past'][] = $userSettings;
                else $lists['recruits'][] = $userSettings;
            }
        }

        $awardsToAdd = [];
        $stats = [];

        // Get a list of all longevity awards each user has and should still receive
        foreach ($users as $user) {
            $additionalFields = [];

            // Only if they're not a recruit
            $isRecruit = strcasecmp(substr($user['user']->username, 0, 2), 'RT') == 0;
            if (!$isRecruit) {
                $result = self::findMissingAwards($user);
                $awards = $result['required'];

                $additionalFields['shouldHave'] = $awards;
                $additionalFields['alreadyHas'] = $result['has'];

                // Sort the "has" part by awardID and awardedNumber ...
                usort($result['has'], function ($a, $b) {
                    if ($a->awardID > $b->awardID) return 1;
                    if ($a->awardID < $b->awardID) return -1;

                    if ($a->awardedNumber > $b->awardedNumber) return 1;
                    return -1;
                });

                // so the user's highest award is accurate.
                $additionalFields['highest'] = count($result['has']) ? array_pop($result['has']) : false;

                if (count($awards)) {
                    $awardsToAdd = array_merge($awardsToAdd, $awards);
                }
            }

            $stats[$user['user']->userID] = array_merge([
                'id' => $user['user']->userID,
                'username' => $user['user']->username,
                'longevity' => $user['longevity'],
                'enlistment' => $user['longevity']['join']->format('Y-m-d'),
                'added' => [],
                'isRecruit' => $isRecruit,
            ], $additionalFields);
        }

        // Award all missing awards
        foreach ($awardsToAdd as $award) {
            $result = $this->addAward($award);

            if ($result) {
                $stats[$award['user']->userID]['added'][] = $result;
            }
        }

        // Clear the cache to make all awards available
        IssuedAwardCacheBuilder::getInstance()->reset();

        $this->createForumPost($stats, $lists);
    }

    /**
     * Return an array with info regarding longevity based on the passed date
     *
     * @param string $date
     * @return array|bool
     */
    public function getLongevity($date)
    {
        $object = DateTime::createFromFormat('Y-m-d', $date);
        if (!$object || $object->format('Y-m-d') != $date) return false; // Validate the correct date format YYYY-MM-DD

        $now = new DateTime();

        $interval = $object->diff($now);
        $years = $interval->y;
        $months = $interval->m;
        $days = $interval->d;

        $anniversary = false;
        if ($months == 11 && $days > 22) {
            $anniversary = 1;
        }
        if ($months == 0 && $days < 14) {
            $anniversary = -1;
        }

        return [
            'y' => $years,
            'm' => $months,
            'd' => $days,
            'absoluteMonths' => $years * 12 + $months,
            'anniversary' => $anniversary,
            'join' => $object,
            'interval' => $this->myFormatInterval($interval),
        ];
    }

    /**
     * Adapted function from DateUtil class. Removed weeks/hours/minutes
     *
     * Returns a formatted date interval. If $fullInterval is set true, the
     * complete interval is returned, otherwise a rounded interval is used.
     *
     * @param    DateInterval $interval
     * @param    boolean $fullInterval
     * @return    string
     */
    public static function myFormatInterval(DateInterval $interval, $fullInterval = false)
    {
        $years = $interval->y;
        $months = $interval->m;
        $days = $interval->d;
        $direction = $interval->format('%R') == '+' ? 'past' : 'future';

        if ($fullInterval) {
            return WCF::getLanguage()->getDynamicVariable('wcf.date.interval.ymd.' . $direction, [
                'days' => $days,
                'firstElement' => $years ? 'years' : ($months ? 'months' : 'days'),
                'lastElement' => !$days ? (!$months ? 'years' : 'months') : 'days',
                'months' => $months,
                'years' => $years
            ]);
        }

        if ($years) {
            return WCF::getLanguage()->getDynamicVariable('wcf.date.interval.years.' . $direction, [
                'years' => $years
            ]);
        }

        if ($months) {
            return WCF::getLanguage()->getDynamicVariable('wcf.date.interval.months.' . $direction, [
                'months' => $months
            ]);
        }

        return WCF::getLanguage()->getDynamicVariable('wcf.date.interval.days.' . $direction, [
            'days' => $days
        ]);
    }

    /**
     * Returns an ordered list of clan members
     *
     * @return array
     */
    protected function getClanMembers()
    {
        // 18 is the ID of the "=US= Members" user group
        $groupID = 18;
        $sql = "SELECT 		u.userID
			FROM		wcf" . WCF_N . "_user AS u
			INNER JOIN 	wcf" . WCF_N . "_user_to_group AS g
			ON		u.userID=g.userID
			WHERE		g.groupID = ?";

        $statement = WCF::getDB()->prepareStatement($sql);
        $statement->execute([$groupID]);

        // We're manually creating the users as opposed to just calling
        // $statement->fetchObjects so we also get userOptions
        $list = [];
        while ($row = $statement->fetchArray()) {
            $list[$row['userID']] = new User($row['userID']);
        }

        // Sort the list by each user's sort order
        usort($list, function ($a, $b) {
            $as = $this->getSortOrder($a->userTitle);
            $bs = $this->getSortorder($b->userTitle);

            if ($as > $bs) return -1;
            if ($as < $bs) return 1;

            return 0;
        });

        return $list;
    }

    /**
     * Returns a numerical sort order based on the passed usertitle
     *
     * @param string $userTitle
     * @return int
     */
    public function getSortOrder($userTitle)
    {
        /*
        * This paygrade check is placeholder until membership
        * plugin is ready with that data available. Currently
        * just checks usertitle and extracts paygrade.
        */
        if ($userTitle == "" || $userTitle == null) {
            return -1;
        }

        // Get text between parentheses
        preg_match('#\((.*?)\)#', trim($userTitle), $paygrade);
        if ($paygrade === false || count($paygrade) == 0) {
            return -1;
        }

        // Do some text sanitizing
        $removeChars = array("-", "_", "[", "]", " ");
        $paygrade = str_replace($removeChars, "", $paygrade[1]);

        // Find the paygrade letter and number
        $letter = $paygrade[0];
        $number = substr($paygrade, 1, 2);

        // Add 20 to ranks starting with "O" for sorting
        if ($letter == "O" || $letter == "o") {
            return $number + 20;
        }

        // Add 10 to ranks starting with "W" for sorting
        if ($letter == "W" || $letter == "w") {
            $number = substr($paygrade, 2, 2);
            return $number + 10;
        }

        // Add nothing to all other ranks for sorting
        return $number;
    }

    /**
     * Creates a list of awards a user is missing and already has
     *
     * @param array $user
     * @return array
     */
    protected static function findMissingAwards($user)
    {
        $longevityInMonths = $user['longevity']['absoluteMonths'];
        $requiredAwards = [];

        foreach (self::getLongevityAwardIndex() as $award) {
            if ($longevityInMonths >= $award['minimum']) {
                // Add X months to enlistment date to build award date
                $awardDate = clone $user['longevity']['join'];
                $awardDate->add(new DateInterval("P{$award['minimum']}M"));

                $insert = array_merge($award, [
                    'user' => $user['user'],
                    'notify' => false,
                    'date' => $awardDate->format('Y-m-d'),
                    'description' => $award['minimum'] == 0
                        ? 'This award goes to newly enlisted members. This award will be improved based on the length of time the member has been active in the clan.'
                        : $award['minimum'] . ' months of service to the Unknown Soldiers.',
                ]);
                unset($insert['minimum']);

                $requiredAwards[] = $insert;
            } elseif (count($requiredAwards)) {
                $lastAward = array_pop($requiredAwards);
                $lastAward['notify'] = true; // Notify the user about the last award
                $requiredAwards[] = $lastAward;

                break; // The list is ordered, so from here on out the if statement will always be false
            }
        }

        // Remove all items from the required list that the user already possesses
        $hasAwards = [];
        foreach (IssuedAward::getAllAwardsForUser($user['user']) as $issuedAward) {
            foreach ($requiredAwards as $id => $requiredAward) {
                if ($issuedAward->awardID == $requiredAward['awardID'] && $issuedAward->awardedNumber == $requiredAward['awardedNumber']) {
                    $hasAwards[] = $issuedAward;
                    unset($requiredAwards[$id]);
                }
            }
        }

        return ['has' => $hasAwards, 'required' => $requiredAwards];
    }

    /**
     * Receives a list of longevity awards where each array element has to have a
     * 'minimum', 'awardID' and 'awardedNumber' key
     *
     * @return array
     */
    public static function getLongevityAwardIndex()
    {
        return [
            ['minimum' => 0, 'awardID' => 1, 'awardedNumber' => 1],
            ['minimum' => 3, 'awardID' => 1, 'awardedNumber' => 2],
            ['minimum' => 6, 'awardID' => 1, 'awardedNumber' => 3],
            ['minimum' => 12, 'awardID' => 1, 'awardedNumber' => 4],
            ['minimum' => 18, 'awardID' => 2, 'awardedNumber' => 1],
            ['minimum' => 24, 'awardID' => 2, 'awardedNumber' => 2],
            ['minimum' => 30, 'awardID' => 2, 'awardedNumber' => 3],
            ['minimum' => 36, 'awardID' => 2, 'awardedNumber' => 4],
            ['minimum' => 42, 'awardID' => 3, 'awardedNumber' => 1],
            ['minimum' => 48, 'awardID' => 3, 'awardedNumber' => 2],
            ['minimum' => 54, 'awardID' => 3, 'awardedNumber' => 3],
            ['minimum' => 60, 'awardID' => 3, 'awardedNumber' => 4],
            ['minimum' => 66, 'awardID' => 4, 'awardedNumber' => 1],
            ['minimum' => 72, 'awardID' => 4, 'awardedNumber' => 2],
            ['minimum' => 78, 'awardID' => 4, 'awardedNumber' => 3],
            ['minimum' => 84, 'awardID' => 4, 'awardedNumber' => 4],
        ];
    }

    /**
     * Gives out an award with the options specified in the array
     *
     * @param array $award
     * @return null|IssuedAward
     */
    protected function addAward($award)
    {
        $result = IssuedAward::giveToUser($award['user'], new Award($award['awardID']), $award['description'], $award['date'], $award['awardedNumber'], $award['notify']);

        return $result;
    }

    /**
     * Creates the longevity digest
     *
     * @param array $stats
     * @param array $lists
     */
    protected function createForumPost($stats, $lists)
    {
        $options = [
            'stats' => $stats,
            'recruits' => $lists['recruits'],
            'upcoming' => $lists['upcoming'],
            'past' => $lists['past'],
        ];

        $reportText = TemplateEngine::getInstance()->fetch('__longevityDigestNewThread', 'wcf', $options);

        // Current date in correct format
        $theDate = DateUtil::format(new DateTime, DateUtil::DATE_FORMAT);

        // Create the thread
        $boardID = 115; // 115 = Engineering Office
        $topic = "Longevity Digest ($theDate)";
        $username = "Automated Post Service Account";
        $nowTime = TIME_NOW;
        $lastPostTime = TIME_NOW;
        $isClosed = 1;

        // Insert thread into db
        $sql = "INSERT INTO wbb" . WCF_N . "_thread
				    (boardID, topic, time, username, lastPostTime, isClosed)
			     VALUES ('$boardID', '$topic', '$nowTime', '$username', '$lastPostTime', '$isClosed')";
        $statement = WCF::getDB()->prepareStatement($sql);
        $statement->execute();

        // Get last insert ID for post building
        $lastInsertID = WCF::getDB()->getInsertID("wbb" . WCF_N . "_thread", "threadID");

        // Create the post
        $thread = new Thread($lastInsertID);
        $board = BoardCache::getInstance()->getBoard($boardID);

        $postData = array(
            'threadID' => $lastInsertID,
            'subject' => $topic,
            'time' => TIME_NOW,
            'message' => $reportText,
            'userID' => 5517,
            'username' => "Automated Post Service Account",
            'isDisabled' => 0,
            'enableHtml' => 1
        );

        $postCreateParameters = array(
            'data' => $postData,
            'thread' => $thread,
            'board' => $board,
            'isFirstPost' => true,
            'attachmentHandler' => null
        );

        // Make the post
        $postAction = new PostAction(array(), 'create', $postCreateParameters);
        $resultValues = $postAction->executeAction();

        // Update the thread with first post data
        $threadEditor = new ThreadEditor($thread);
        $threadEditor->update(array(
            'firstPostID' => $resultValues['returnValues']->postID,
            'lastPostID' => $resultValues['returnValues']->postID
        ));
    }

    private function convertRegistrationToJoinDate($timestamp) {
        $dt = new DateTime;
        $dt->setTimestamp($timestamp);
        return $dt->format('Y-m-d');
    }
}
