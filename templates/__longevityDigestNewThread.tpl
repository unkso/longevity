<div>
    <h3 class="big">
        Upcoming Anniversaries
    </h3>

    <p>
        {if $upcoming|count}
            [list]
            {foreach from=$upcoming item=$anniversary}
                [*] {$anniversary['user']->username} ({$anniversary["longevity"]["y"]+1} years)
            {/foreach}
            [/list]
        {else}
            No upcoming anniversaries (within the next 9 days)
        {/if}
    </p>
</div>

<div>
    <h3 class="big">
        Anniversaries just past
    </h3>

    <p>
        {if $past|count}
            [list]
            {foreach from=$past item=$anniversary}
                [*] {$anniversary['user']->username} ({$anniversary["longevity"]["y"]} years)
            {/foreach}
            [/list]
        {else}
            No recent anniversaries (within the last 14 days)
        {/if}
    </p>
</div>

<div>
    <h3 class="big">
        Longevity Ribbons
    </h3>

    <table class="table table-hover">
        <thead>
            <tr>
                <th>User</th>
                <th>Highest award received</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$stats item=$stat}
                <tr>
                    <td>
                        <dl>
                            <dt style="min-height:26px;margin-top:0;width:100px;">Name</dt>
                            <dd style="margin:0 0 0 110px;"><b><a href="http://clanunknownsoldiers.com/user/{$stat['id']}/" target="_blank">{$stat['username']}</a></b></dd>
                            <dt style="min-height:26px;margin-top:0;width:100px;">Joined</dt>
                            <dd style="margin:0 0 0 110px;">{$stat['enlistment']}</dd>
                            <dt style="min-height:26px;margin-top:0;width:100px;">Longevity</dt>
                            <dd style="margin:0 0 0 110px;">{$stat['longevity']['interval']}</dd>
                        </dl>
                    </td>
                    <td>
                        {if !$stat['isRecruit']}
                            {if $stat['highest']}
                                <strong>{$stat['highest']->getAward()->title} (№&nbsp;{$stat['highest']->awardedNumber})</strong><br>
                                <img src="{$stat['highest']->getRibbonURL()}">
                            {else}
                                User doesn't have any longevity awards yet.
                            {/if}
                        {else}
                            Recruit<br><i>Ineligible for longevity award</i>
                        {/if}
                    </td>
                    <td>
                        {if $stat['added']|count}
                            <i class="fa fa-check-square-o fa-2x green" aria-hidden="true"></i> Added:<br>
                            <ul>
                            {foreach from=$stat['added'] item=$added}
                                <li>{$added->getAward()->title} <small>(№&nbsp;{$added->awardedNumber})</small></li>
                            {/foreach}
                            </ul>
                        {else}
                            All up to date.
                        {/if}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>