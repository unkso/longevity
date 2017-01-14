<?php namespace wcf\data\award\longevity;

use wcf\data\AbstractDatabaseObjectAction;

class LongevityAwardAction extends AbstractDatabaseObjectAction
{
	/**
	 * @inheritDoc
	 */
    protected $className = LongeviyAward::class;

	/**
	 * @inheritDoc
	 */
	protected $permissionsDelete = ['admin.clan.award.canManageAwards'];

	/**
	 * @inheritDoc
	 */
	protected $requireACP = ['delete'];
}
