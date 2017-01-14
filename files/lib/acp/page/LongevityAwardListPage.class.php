<?php namespace wcf\acp\page;

use wcf\data\award\longevity\LongevityAwardList;
use wcf\page\SortablePage;

class LongevityAwardListPage extends SortablePage
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
	 * @inheritDoc
	 */
	public $objectListClassName = LongevityAwardList::class;

	/**
	 * @inheritDoc
	 */
	public $validSortFields = ['longevityAwardID', 'months'];
}
