<?php namespace wcf\acp\form;

use wcf\data\award\longevity\LongevityAwardAction;
use wcf\data\award\longevity\LongevityAward;
use wcf\data\award\AwardTier;
use wcf\form\AbstractForm;
use wcf\system\WCF;

class LongevityAwardEditForm extends LongevityAwardAddForm
{
	/**
	 * @inheritDoc
	 */
	public $templateName = 'longevityAwardAdd';

	/**
	 * edited longevity award object
	 * @var LongevityAward
	 */
	public $longevityAward = null;

	/**
	 * id of the edited longevity award
	 * @var	int
	 */
	public $longevityAwardID = 0;

    /**
     * @inheritDoc
     */
    public function assignVariables()
    {
        parent::assignVariables();

        WCF::getTPL()->assign([
            'action' => 'edit',
			'longevityAward' => $this->longevityAward,
        ]);
    }

	/**
	 * @inheritDoc
	 */
	public function readData()
	{
		parent::readData();

		if (empty($_POST)) {
			$this->tierID = $this->longevityAward->tierID;
			$this->months = $this->longevityAward->months;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function readParameters()
	{
		parent::readParameters();

		var_dump($_REQUEST['id']);
		if (isset($_REQUEST['id'])) $this->longevityAwardID = intval($_REQUEST['id']);
		$this->longevityAward = new LongevityAward($this->longevityAwardID);
		if (!$this->longevityAward->longevityAwardID) {
			throw new IllegalLinkException();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function save()
	{
		AbstractForm::save();

		$this->objectAction = new LongevityAwardAction([$this->longevityAward], 'update', [
			'data' => array_merge($this->additionalFields, [
				'tierID' => $this->tierID,
				'months' => $this->months,
			])
		]);
		$this->objectAction->executeAction();

		$this->saved();

		// show success message
		WCF::getTPL()->assign('success', true);
	}
}
