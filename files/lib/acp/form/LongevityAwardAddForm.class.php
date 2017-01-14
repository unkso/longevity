<?php namespace wcf\acp\form;

use wcf\data\award\longevity\LongevityAwardAction;
use wcf\system\cache\builder\AwardCacheBuilder;
use wcf\system\exception\UserInputException;
use wcf\data\award\AwardTier;
use wcf\form\AbstractForm;
use wcf\util\StringUtil;
use wcf\system\WCF;

class LongevityAwardAddForm extends AbstractForm
{
	/**
	 * @inheritDoc
	 */
	public $activeMenuItem = 'wcf.acp.menu.link.clan.award.longevity';

	/**
	 * @inheritDoc
	 */
	public $neededPermissions = ['admin.clan.award.canManageAwards'];

    /**
     * number of months before this award is received
     * @var int
     */
    public $months = 0;

    /**
     * ID of the tier that is received
     * @var int
     */
    public $tierID = null;

    /**
     * @inheritDoc
     */
    public function assignVariables()
    {
        parent::assignVariables();

        WCF::getTPL()->assign([
            'action' => 'add',
            'tierID' => $this->tierID,
            'months' => $this->months,
            'tierList' => AwardCacheBuilder::getInstance()->getData()['tiers']
        ]);
    }

	/**
	 * @inheritDoc
	 */
	public function readFormParameters()
    {
		parent::readFormParameters();

		if (isset($_POST['tierID'])) $this->tierID = StringUtil::trim($_POST['tierID']);
		if (isset($_POST['months'])) $this->months = StringUtil::trim($_POST['months']);
	}

	/**
	 * @inheritDoc
	 */
	public function save()
    {
		parent::save();

		$this->objectAction = new LongevityAwardAction([], 'create', [
			'data' => array_merge($this->additionalFields, [
				'tierID' => $this->tierID,
				'months' => $this->months,
			])
		]);
		$this->objectAction->executeAction();

		$this->saved();

		// reset values
		$this->tierID = 0;
		$this->months = 0;

		// show success message
		WCF::getTPL()->assign('success', true);
	}

	/**
	 * @inheritDoc
	 */
	public function validate()
    {
		parent::validate();

		// validate months
		if (empty($this->months)) {
			throw new UserInputException('months');
		}

		// validate tierID
		if (empty($this->tierID)) {
			throw new UserInputException('tierID');
		}
        if (!AwardTier::getTierByID($this->tierID)) {
			throw new UserInputException('tierID', 'invalid');
        }
	}
}
