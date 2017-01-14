<?php namespace wcf\data\award\longevity;

use wcf\data\award\AwardTier;
use wcf\data\DatabaseObject;

class LongevityAward extends DatabaseObject
{
	/**
	 * @inheritDoc
	 */
	protected static $databaseTableName = 'unkso_longevity_award';

	/**
	 * @inheritDoc
	 */
	protected static $databaseTableIndexName = 'longevityAwardID';

	public function getTierName()
	{
		$tier = AwardTier::getTierByID($this->tierID);
		return $tier->getAward()->title . $tier->levelSuffix;
	}
}
