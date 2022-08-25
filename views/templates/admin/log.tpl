{*
*  Admin log tab
*
*  @author   GoPay
*  @license  https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
*}

<div class="wrap">
    <div class="prestashop-gopay-menu">
        <h1>PrestaShop GoPay gateway</h1>
    </div>

    <div class="prestashop-gopay-menu">
        <table>
            <thead>
            <tr>
                <th>{$head['Id']}</th>
                <th>{$head['Order id']}</th>
                <th>{$head['Transaction id']}</th>
                <th>{$head['Message']}</th>
                <th>{$head['Created at']}</th>
                <th>{$head['Log level']}</th>
                <th>{$head['Log']}</th>
            </tr>
            </thead>
            <tbody id="log_table_body">
            {foreach from=$log_data key=key item=log}
                {assign var=json value=$log['log']|json_decode}
                <tr>
                    <td>{$log['id']}</td>
                    <td><a href="{$orders_link|replace:'?': "{$log['order_id']}/view?"}">{$log['order_id']}</a></td>
                    <td><a href="{$json->json->gw_url|default:''}">{$log['transaction_id']}</a></td>
                    <td>{$log['message']}</td>
                    <td>{$log['created_at']} (GMT)</td>
                    <td>{$log['log_level']}</td>
                    <td><a href="#" onClick="openPopup({$log['log']|escape:"html"})">Open log</a></td>
                </tr>
            {/foreach}
            </tbody>
        </table>

        <div class="prestashop-gopay-menu-form">
            <form name="pagenum_log_table_filter_search" method="post" action=" " onSubmit="window.location.reload();">
                <label for="page"></label>
                <input type="hidden" id="page" name="page" value="prestashop-gopay-menu-log">
                <label for="log_table_filter">Filter table by any column:</label>
                <input type="hidden" id="pagenum" name="pagenum" value="{$pagenum}">
                <input type="text" id="log_table_filter" name="log_table_filter"
                       placeholder="Search here" value="{$log_table_filter}">
                <input type="submit" value="Search">
            </form>
            <form name="pagenum_log_table_filter_go_to" method="post" action=" " onSubmit="window.location.reload();">
                <label for="page"></label>
                <input type="hidden" id="page" name="page" value="prestashop-gopay-menu-log">
                <label for="pagenum">Page ({$pagenum} of {$number_of_pages}):</label>
                <input type="number" id="pagenum" name="pagenum" min="1" max="{$number_of_pages}" style="width: 65px;">
                <input type="hidden" id="log_table_filter" name="log_table_filter" value="{$log_table_filter}">
                <input type="submit" value="Go to">
            </form>
        </div>

        <script>
            function submit_new_values(elem) {
                let pagenum = document.getElementById('pagenum');

                if ( elem === 'previous' ) {
                    pagenum.value = parseInt(pagenum.value) - 1;
                } else if ( elem === 'next' ) {
                    pagenum.value = parseInt(pagenum.value) + 1;
                } else {
                    pagenum.value = parseInt(elem);
                }

                document.pagenum_log_table_filter_search.submit();
            }
        </script>

        {if !empty($log_data) }
            <div id="prestashop-gopay-menu-popup" class="prestashop-gopay-menu-popup">
                <div class="prestashop-gopay-menu-close" onclick="closePopup();"></div>
            </div>

            <nav>
                {if $pagenum > 1 }
                    {assign var="enabled_disabled" value='enabled'}
                {else}
                    {assign var="enabled_disabled" value='disabled'}
                {/if}
                <form method="post" action=" " onSubmit="window.location.reload();">
                <ul class="prestashop-gopay-menu-pagination">
                    <li class="prestashop-gopay-menu-{$enabled_disabled}">
                        <a href="#" id="previous" onclick="submit_new_values(this.id);return false;">Previous</a>
                    </li>
                    {if $number_of_pages > 10 }
                        {assign var="start" value={math equation="max(x - 5, 1)" x=$pagenum}}
                        {assign var="stop" value={math equation="x + 10" x=$start}}

                        {if $number_of_pages > 10 }
                            {assign var="start" value={math equation="x - 10" x=$number_of_pages}}
                            {assign var="stop" value=$number_of_pages}
                        {/if}
                    {else}
                        {assign var="start" value=1}
                        {assign var="stop" value=$number_of_pages}
                    {/if}

                    {for $page_log=$start to $stop}
                        {if $pagenum == $page_log }
                            {assign var="enabled_disabled" value='active'}
                        {else}
                            {assign var="enabled_disabled" value='inactive'}
                        {/if}
                        <li class="prestashop-gopay-menu-{$enabled_disabled}">
                            <a href = "" id="{$page_log}" onclick="submit_new_values(this.id);return false;">{$page_log}</a>
                        </li>
                    {/for}
                    {if $pagenum < $number_of_pages }
                        {assign var="enabled_disabled" value='enabled'}
                    {else}
                        {assign var="enabled_disabled" value='disabled'}
                    {/if}
                    <li class="prestashop-gopay-menu-{$enabled_disabled}">
                        <a href="" id="next" onclick="submit_new_values(this.id);return false;">Next</a>
                    </li>
                </ul>
                </form>
            </nav>
        {/if}
    </div>
</div>