{include file='header' pageTitle='wcf.acp.award.longevity.list'}

<header class="boxHeadline">
	<h1>{lang}wcf.acp.award.longevity.list{/lang}</h1>

	<script data-relocate="true">
		//<![CDATA[
		$(function() {
			new WCF.Action.Delete('wcf\\data\\award\\longevity\\LongevityAwardAction', '.jsAwardActionRow .jsDeleteButton');
		});

		{event name='afterJavascriptInitialization'}
		//]]>
	</script>

</header>

<div class="contentNavigation">
	{pages print=true assign=pagesLinks controller="LongevityAwardsList" link="pageNo=%d&sortField=$sortField&sortOrder=$sortOrder"}

	<nav>
		<ul>
			<li><a href="{link controller='LongevityAwardAdd'}{/link}" class="button"><span class="icon icon16 icon-plus"></span> <span>{lang}wcf.acp.award.longevity.add{/lang}</span></a></li>

			{event name='contentNavigationButtonsTop'}
		</ul>
	</nav>
</div>

{if $objects|count}
	<div class="tabularBox tabularBoxTitle marginTop">
		<header>
			<h2>{lang}wcf.acp.award.longevity.list{/lang} <span class="badge badgeInverse">{#$items}</span></h2>
		</header>

		<table class="table">
			<thead>
				<tr>
					<th class="columnID{if $sortField == 'longevityAwardID'} active {@$sortOrder}{/if}" colspan="2"><a href="{link controller='LongevityAwardList'}pageNo={@$pageNo}&sortField=longevityAwardID&sortOrder={if $sortField == 'longevityAwardID' && $sortOrder == 'ASC'}DESC{else}ASC{/if}{/link}">{lang}wcf.global.objectID{/lang}</a></th>
					<th class="columnAward"><a>{lang}wcf.acp.award.longevity.award{/lang}</a></th>
					<th class="columnMonths{if $sortField == 'months'} active {@$sortOrder}{/if}"><a href="{link controller='LongevityAwardList'}pageNo={@$pageNo}&sortField=months&sortOrder={if $sortField == 'months' && $sortOrder == 'ASC'}DESC{else}ASC{/if}{/link}">{lang}wcf.acp.award.longevity.months{/lang}</a></th>

					{event name='columnHeads'}
				</tr>
			</thead>

			<tbody>
				{foreach from=$objects item=object}
					<tr class="jsAwardActionRow">
						<td class="columnIcon">
							<a href="{link controller='LongevityAwardEdit' id=$object->longevityAwardID}{/link}" title="{lang}wcf.global.button.edit{/lang}" class="jsTooltip"><span class="icon icon16 icon-pencil"></span></a>
							<span class="icon icon16 icon-remove jsDeleteButton jsTooltip pointer" title="{lang}wcf.global.button.delete{/lang}" data-object-id="{@$object->longevityAwardID}" data-confirm-message="{lang}wcf.acp.clan.award.longevity.delete.sure{/lang}"></span>

							{event name='rowButtons'}
						</td>
						<td class="columnID">{$object->longevityAwardID}</td>
						<td class="columnAward">{$object->getTierName()}</td>
						<td class="columnMonths">{$object->months}</td>

						{event name='columns'}
					</tr>
				{/foreach}
			</tbody>
		</table>

	</div>

	<div class="contentNavigation">
		{@$pagesLinks}

		<nav>
			<ul>
				<li><a href="{link controller='LongevityAwardAdd'}{/link}" class="button"><span class="icon icon16 icon-plus"></span> <span>{lang}wcf.acp.award.longevity.add{/lang}</span></a></li>

				{event name='contentNavigationButtonsBottom'}
			</ul>
		</nav>
	</div>
{else}
	<p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

{include file='footer'}
