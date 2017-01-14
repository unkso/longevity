<?php
namespace wcf\system\cronjob;
use wcf\data\cronjob\Cronjob;
use wbb\data\thread\Thread;
use wbb\data\thread\ThreadAction;
use wbb\data\thread\ThreadEditor;
use wbb\data\post\Post;
use wbb\data\post\PostAction;
use wbb\data\post\PostEditor;
use wbb\data\board\Board;
use wbb\data\board\BoardCache;
use wcf\util\DateUtil;
use wcf\system\WCF;

class LongevityReportCronjob extends AbstractCronjob
{
	/**
	 * @see	\wcf\system\cronjob\ICronjob::execute()
	 */
	public function execute(Cronjob $cronjob)
	{
		parent::execute($cronjob);

		// Index of longevity awards (in months vs eligible tierID from `wcf1_unkso_award_tier`)
		$awardsIndex = array(
			array('minMonths' => 0, 'tierID' => 89),
			array('minMonths' => 3, 'tierID' => 90),
			array('minMonths' => 6, 'tierID' => 91),
			array('minMonths' => 12, 'tierID' => 92),
			array('minMonths' => 18, 'tierID' => 93),
			array('minMonths' => 24, 'tierID' => 94),
			array('minMonths' => 30, 'tierID' => 95),
			array('minMonths' => 36, 'tierID' => 96),
			array('minMonths' => 42, 'tierID' => 97),
			array('minMonths' => 48, 'tierID' => 98),
			array('minMonths' => 54, 'tierID' => 99),
			array('minMonths' => 60, 'tierID' => 100),
			array('minMonths' => 66, 'tierID' => 101),
			array('minMonths' => 72, 'tierID' => 102),
			array('minMonths' => 78, 'tierID' => 103),
			array('minMonths' => 84, 'tierID' => 104)
		);

		// Compile longevity data
		$groupID = 18; // US Members
		$userLongevity = array();
		$sql = "SELECT 		u.userID, u.username, o.userOption40, u.userTitle
			FROM		wcf".WCF_N."_user AS u
			INNER JOIN 	wcf".WCF_N."_user_to_group AS g
			ON		u.userID=g.userID
			LEFT JOIN	wcf".WCF_N."_user_option_value AS o
			ON		u.userID=o.userID
			WHERE		g.groupID = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute(array($groupID));
		while ($row = $statement->fetchArray()) {
			$userLongevity[$row["userID"]] = array(
				'userID'	=> $row["userID"],
				'username'	=> $row["username"],
				'usertitle'	=> $row["userTitle"],
				'enlistment' 	=> $row["userOption40"],
				'sortorder'	=> (int)$this->getSortOrder($row["userTitle"]),
				'longevity'	=> $this->getLongevity($row["userOption40"])
			);
		}

		usort($userLongevity, array($this,'sortByOrder'));

		$upcomingAnniversaries = array();
		$pastAnniversaries = array();
		$newRecruits = array();



		$reportText	=  "";
		foreach ( $userLongevity as $userID => $user) {
			if ( $user["longevity"]["anniversary"] > 0 ) {
				$upcomingAnniversaries[] = $user;
			}
		}

		foreach ( $userLongevity as $userID => $user) {
			if ( $user["longevity"]["anniversary"] < 0 && $user["longevity"]["y"] > 0 ) {
				$pastAnniversaries[] = $user;
			}
		}

		foreach ( $userLongevity as $userID => $user) {
			if ( $user["longevity"]["anniversary"] < 0 && $user["longevity"]["y"] == 0) {
				$newRecruits[] = $user;
			}
		}

		$reportText	.= "[block][size=18]Upcoming Anniversaries[/size][/block]";
		if (count($upcomingAnniversaries)) {
			foreach ($upcomingAnniversaries as $key => $user) {
				$reportText .= "[block]" . $user["username"] . "   (" . ($user["longevity"]["y"]+1) . "y)[/block]";
			}
		} else {
			$reportText .= "[block]None[/block]";
		}

		$reportText	.= "[block][size=18]Anniversaries just past[/size][/block]";
		if (count($pastAnniversaries)) {
			foreach ($pastAnniversaries as $key => $user) {
				$reportText .= "[block]" . $user["username"] . "   (" . ($user["longevity"]["y"]) . "y)[/block]";
			}
		} else {
			$reportText .= "[block]None[/block]";
		}

		$reportText	.= "[block][size=18]Welcome new Recruits[/size][/block]";
		if (count($newRecruits)) {
			foreach ($newRecruits as $key => $user) {
				$reportText .= "[block]" . $user["username"] . "[/block]";
			}
		} else {
			$reportText .= "[block]None[/block]";
		}

		$reportText	.= "[block][align=center][size=18]Longevity Ribbons[/size][/align][/block]";
		$reportText 	.= "[table]";
		$reportText 	.= "[tr][td]User/Status[/td][td]Enlisted/Should Have[/td][td]Longevity/Has[/td][/tr]";
		$reportText	.= "[tr][td][/td][td][/td][td][/td][/tr]";
		//$reportText	.= "<tbody>";
		foreach ($userLongevity as $userID => $user) {
			// Check for presence of correct award
			// Simple check assuming 30 days/month and 365/year
			//var_dump($user["longevity"]);
			$totalMonths = $user["longevity"]["months"];

			$eligibleTierID = -1;
			for ($i=0; $i<count($awardsIndex); $i++) {
				if ($totalMonths >= $awardsIndex[$i]["minMonths"]) {
					$eligibleTierID = $awardsIndex[$i]["tierID"];
				}
			}

			$sql = "SELECT 		a.*
				FROM		wcf".WCF_N."_unkso_issued_award AS a
				WHERE		a.tierID = ?
				AND		a.userID = ?
				LIMIT 		1";
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute(array($eligibleTierID, $user["userID"]));
			$row = $statement->fetchArray();
			if (empty($row)) {
				$hasEligibleTier = false;
			} else {
				$hasEligibleTier = true;
			}

			$sql = "SELECT 		t.*, a.title, a.description
				FROM		wcf".WCF_N."_unkso_award_tier AS t
				INNER JOIN	wcf".WCF_N."_unkso_award AS a
				ON		t.awardID = a.awardID
				WHERE		t.tierID = ?
				LIMIT		1";
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute(array($eligibleTierID));
			while ($row = $statement->fetchArray()) {
				$eligibleURL = $row["ribbonURL"];
				$eligibleName = $row["title"];
				$eligibleLevel = $row["level"];
				$eligibleDescription = $row["description"];
			}

			$sql = "SELECT		ia.*, t.ribbonURL, t.level, a.title
				FROM		wcf".WCF_N."_unkso_issued_award AS ia
				INNER JOIN	wcf".WCF_N."_unkso_award_tier AS t
				ON		ia.tierID = t.tierID
				INNER JOIN	wcf".WCF_N."_unkso_award AS a
				ON		t.awardID = a.awardID
				WHERE		ia.userID = ?";
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute(array($user["userID"]));
			$userAwards = array();
			while ($row = $statement->fetchArray()) {
				$userAwards[] = $row;
			}

			$highestName = "Unknown";
			$highestLevel = "[?]";
			$highestURL = "blank";

			for ($i=count($awardsIndex)-1; $i>=0; $i--) {
				$found = false;

				for($j=0; $j<count($userAwards); $j++) {
					if ($userAwards[$j]["tierID"] == $awardsIndex[$i]["tierID"]) {
						$highestName = $userAwards[$j]["title"];
						$highestLevel = $userAwards[$j]["level"];
						$highestURL = $userAwards[$j]["ribbonURL"];
						$found = true;
						break;
					}
				}

				if($found) break;
			}

			// Add eligible award for the user
			$awardAdded = false;
			//if ($user["userID"]==4635) {
			$insertDate = date_format( date_create("now"), "Y-m-d" );
			$insertUserID = $user["userID"];
			$insertTierID = $eligibleTierID;
			$insertDescription = $eligibleDescription;
			$sql = "INSERT INTO wcf".WCF_N."_unkso_issued_award
				(userID, tierID, description, date) VALUES (?, ?, ?, ?)";
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute(array($insertUserID, $insertTierID, $insertDescription, $insertDate));

			// Check success
			$sql = "SELECT 		a.*
				FROM		wcf".WCF_N."_unkso_issued_award AS a
				WHERE		a.tierID = ?
				AND		a.userID = ?
				LIMIT 		1";
			$statement = WCF::getDB()->prepareStatement($sql);
			$statement->execute(array($eligibleTierID, $user["userID"]));
			$row = $statement->fetchArray();
			if (empty($row)) {
				$awardAdded = false;
			} else {
				$awardAdded = true;
			}
			//}

			//if (!$hasEligibleTier) {
				$reportText	.= "[tr][td]";
				$reportText	.= "[size=14]".$user["username"]."[/size]";
				$reportText	.= "[/td][td]";
				$reportText	.= $user["enlistment"];
				$reportText	.= "[/td][td]";

				if ($user["longevity"]) {
					//$reportText	.= $user["longevity"]["y"] . "y, " . $user["longevity"]["m"] . "m, " . $user["longevity"]["d"] . "d (" . $user["longevity"]["months"] . " months)";
					$reportText .=  $user["longevity"]["interval"];
				} else {
					$reportText	.= "Unknown";
				}
				$reportText	.= "[/td]";

				$reportText	.= "[/tr]";
				$reportText	.= "[tr]";
				$reportText	.= "[td]";
				if ($hasEligibleTier) { $reportText .= "ok"; }
				else {
					if ($awardAdded) {
						$reportText .= "[block]Successfully added[/block][block]".$eligibleName." [".$eligibleLevel."][/block][img]".$eligibleURL."[/img]";
					} else {
						$reportText .= "Error adding award!";
					}
				}
				$reportText	.= "[/td]";
				$reportText	.= "[td]";
				$reportText	.= "[block]".$eligibleName." [".$eligibleLevel."][/block][img]".$eligibleURL."[/img]";
				$reportText	.= "[/td]";
				$reportText	.= "[td]";
				$reportText	.= "[block]".$highestName." [".$highestLevel."][/block][img]".$highestURL."[/img]";
				$reportText	.= "[/td]";
				//$reportText	.= "[td]";
				//$reportText	.= "[/td]";
				$reportText	.= "[/tr]";
			//}

			$eligibleName = $eligibleLevel = $eligibleURL = null;
			$highestName = $highestLevel = $highestURL = null;
			$hasEligibleTier = null;
		}
		$reportText 	.= "[/table]";
		//var_dump($reportText);

		$theDate = DateUtil::format(DateUtil::getDateTimeByTimestamp(TIME_NOW), DateUtil::DATE_FORMAT);

		// Create the thread
		$boardID = 115; // Engineering office
		$topic = "Longevity Digest (".$theDate.")";
		$nowTime = TIME_NOW;
		$username = "Automated Post Service Account";
		$lastPostTime = TIME_NOW;
		$isClosed = 1;

		$sql = "INSERT INTO wbb".WCF_N."_thread
				    (boardID, topic, time, username, lastPostTime, isClosed)
			     VALUES ('$boardID', '$topic', '$nowTime', '$username', '$lastPostTime', '$isClosed')";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute();

		$lastInsertID = WCF::getDB()->getInsertID("wbb".WCF_N."_thread", "threadID");

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

		$postAction = new PostAction(array(), 'create', $postCreateParameters);
		$resultValues = $postAction->executeAction();

		// Update the thread with first post data
		$threadEditor = new ThreadEditor($thread);
		$threadEditor->update(array(
			'firstPostID' => $resultValues['returnValues']->postID,
			'lastPostID' => $resultValues['returnValues']->postID
		));

	}

	public function getLongevity($date) {
		if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$date)) {
			$datetime1 = date_format ( date_create($date), "U" );
			$datetime2 = date_format ( date_create("now"), "U" );

			$diff = date_diff(date_create($date),date_create("now"));
			$diffFormatted = $this->myFormatInterval($diff, true);
			//var_dump(DateUtil::formatInterval($diff, true));

			$years = $diff->format('%y');
			$months = $diff->format('%m');
			$days = $diff->format('%d');

			$numDays = abs($datetime2 - $datetime1)/60/60/24;

			$intervalArr = array();
			//$years = (int)($numDays / 365);
			//$months = (int)(($numDays - ($years * 365))/30);
			//$days = (int)($numDays - ($years * 365) - ($months * 30));
			$numMonths = (int)($numDays / 30);

			$anniversary = 0;
			if ($months == 11 && $days > 22) {
				$anniversary = 1;
			}
			if ($months == 0 && $days < 8) {
				$anniversary = -1;
			}

			$intervalArr = array(
				'days' => $numDays,
				'months' => $numMonths,
				'y' => $years,
				'm' => $months,
				'd' => $days,
				'anniversary' => $anniversary,
				'interval' => $diffFormatted
			);

			return $intervalArr;
		} else {
			return false;
		}
	}

	public function getSortOrder($userTitle) {
		if ($userTitle == "" || $userTitle == null) {
			return -1;
		}

		preg_match('#\((.*?)\)#', trim($userTitle), $paygrade);
		if( $paygrade === false || count($paygrade)==0 ) {
			return -1;
		}

		$removeChars = array("-", "_", "[", "]", " ");
		$paygrade = str_replace($removeChars, "", $paygrade[1]);

		$letter = $paygrade[0];
		$number = substr($paygrade, 1, 2);

		if ($letter=="O" || $letter=="o") {
			return $number+20;
		}

		if ($letter=="W" || $letter=="w") {
			$number = substr($paygrade, 2, 2);
			return $number+10;
		}

		return $number;
	}

	public function sortByOrder($a, $b) {
		if ($b['sortorder'] == $a['sortorder']) {
			$nameA = substr( strstr($a['username'], '.'), 1);
			$nameB = substr( strstr($b['username'], '.'), 1);

			return $b["username"] - $a["username"];
		}

		return $b['sortorder'] - $a['sortorder'];
	}

	public static function myFormatInterval(\DateInterval $interval, $fullInterval = false) {
		$years = $interval->format('%y');
		$months = $interval->format('%m');
		$days = $interval->format('%d');
		switch ($interval->format('%R')) {
			case '+':
				$direction = 'past';
			break;
			case '-':
				$direction = 'future';
			break;
		}

		if ($fullInterval) {
			return WCF::getLanguage()->getDynamicVariable('wcf.date.interval.ymd.'.$direction, array(
				'days' => $days,
				'firstElement' => $years ? 'years' : ($months ? 'months' : 'days'),
				'lastElement' => !$days ? (!$months ? 'years' : 'months') : 'days',
				'months' => $months,
				'years' => $years
			));
		}

		if ($years) {
			return WCF::getLanguage()->getDynamicVariable('wcf.date.interval.years.'.$direction, array(
				'years' => $years
			));
		}

		if ($months) {
			return WCF::getLanguage()->getDynamicVariable('wcf.date.interval.months.'.$direction, array(
				'months' => $months
			));
		}

		if ($days) {
			return WCF::getLanguage()->getDynamicVariable('wcf.date.interval.days.'.$direction, array(
				'days' => $days
			));
		}
	}
}
