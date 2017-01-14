{include file='header' pageTitle='wcf.acp.award.longevity.'|concat:$action}

<header class="boxHeadline">
	<h1>{lang}wcf.acp.award.longevity.{$action}{/lang}</h1>
</header>

{include file='formError'}

{if $success|isset}
	<p class="success">{lang}wcf.global.success.{$action}{/lang}</p>
{/if}

<div class="contentNavigation">
	<nav>
		<ul>
			<li><a href="{link controller='LongevityAwardList'}{/link}" class="button"><span class="icon icon16 icon-list"></span> <span>{lang}wcf.acp.menu.link.clan.award.longevity{/lang}</span></a></li>

			{event name='contentNavigationButtons'}
		</ul>
	</nav>
</div>

<form method="post" action="{if $action == 'add'}{link controller='LongevityAwardAdd'}{/link}{else}{link controller='LongevityAwardEdit' id=$longevityAward->longevityAwardID}{/link}{/if}">
	<div class="container containerPadding marginTop">
		<fieldset>
			<legend>{lang}wcf.acp.award.longevity.general{/lang}</legend>
			<dl>
				<dt><label for="months">{lang}wcf.acp.award.longevity.months{/lang}</label></dt>
				<dd>
					<input id="months" name="months" value="{$months}" type="text" class="medium" />
					<small>{lang}wcf.acp.award.longevity.months.description{/lang}</small>
					{if $errorField == 'months'}
						<small class="innerError">
							{lang}wcf.global.form.error.{$errorType}{/lang}
						</small>
					{/if}
				</dd>
			</dl>
			<dl>
				<dt><label for="tierID">{lang}wcf.acp.award.longevity.tier{/lang}</label></dt>
				<dd>
					<select name="tierID" id="tierID">
						{foreach from=$tierList item=tier}
							<option value="{@$tier->tierID}"{if $tier->tierID == $tierID} selected="selected"{/if}>{$tier->getAward()->title}{$tier->levelSuffix}</option>
						{/foreach}
					</select>
					{if $errorField == 'tierID'}
						<small class="innerError">
							{lang}wcf.global.form.error.{$errorType}{/lang}
						</small>
					{/if}
				</dd>
			</dl>
		</fieldset>

		<div class="formSubmit">
			<input type="submit" value="{lang}wcf.global.button.submit{/lang}" accesskey="s" />
			{@SECURITY_TOKEN_INPUT_TAG}
		</div>

		{event name='fieldsets'}
	</div>
</form>

{include file='footer'}
