<div id="headerSubMenu">
	<div style="padding-bottom:5px;">
	</div>
</div>

<h2>{$translate->_('reports.ui.ticket.open_tickets')}</h2>

<form action="{devblocks_url}c=reports&report=report.tickets.open_tickets{/devblocks_url}" method="POST" id="frmRange" name="frmRange">
<input type="hidden" name="c" value="reports">
{$translate->_('reports.ui.date_from')} <input type="text" name="start" id="start" size="24" value="{$start}"><button type="button" onclick="devblocksAjaxDateChooser('#start','#divCal');">&nbsp;<img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/calendar.gif{/devblocks_url}" align="top">&nbsp;</button>
{$translate->_('reports.ui.date_to')} <input type="text" name="end" id="end" size="24" value="{$end}"><button type="button" onclick="devblocksAjaxDateChooser('#end','#divCal');">&nbsp;<img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/calendar.gif{/devblocks_url}" align="top">&nbsp;</button>
<button type="submit" id="btnSubmit" onclick="genericAjaxPost('frmRange', 'report');drawChart(document.getElementById('start').value, document.getElementById('end').value);">{$translate->_('common.refresh')|capitalize}</button>
<div id="divCal"></div>
{$translate->_('reports.ui.date_past')} <a href="javascript:;" onclick="document.getElementById('start').value='-1 year';document.getElementById('end').value='now';$('#btnSubmit').click();">{$translate->_('reports.ui.filters.1_year')|lower}</a>
| <a href="javascript:;" onclick="document.getElementById('start').value='-6 months';document.getElementById('end').value='now';$('#btnSubmit').click();">{'reports.ui.filters.n_months'|devblocks_translate:6}</a>
| <a href="javascript:;" onclick="document.getElementById('start').value='-3 months';document.getElementById('end').value='now';$('#btnSubmit').click();">{'reports.ui.filters.n_months'|devblocks_translate:3}</a>
| <a href="javascript:;" onclick="document.getElementById('start').value='-1 month';document.getElementById('end').value='now';$('#btnSubmit').click();">{$translate->_('reports.ui.filters.1_month')|lower}</a>
| <a href="javascript:;" onclick="document.getElementById('start').value='-1 week';document.getElementById('end').value='now';$('#btnSubmit').click();">{$translate->_('reports.ui.filters.1_week')|lower}</a>
| <a href="javascript:;" onclick="document.getElementById('start').value='-1 day';document.getElementById('end').value='now';$('#btnSubmit').click();">{$translate->_('reports.ui.filters.1_day')|lower}</a>
| <a href="javascript:;" onclick="document.getElementById('start').value='today';document.getElementById('end').value='now';$('#btnSubmit').click();">{$translate->_('common.today')|lower}</a>
<br>{$active_worker->name}
{if !empty($years)}
	{foreach from=$years item=year name=years}
		{if !$smarty.foreach.years.first} | {/if}<a href="javascript:;" onclick="document.getElementById('start').value='Jan 1 {$year}';document.getElementById('end').value='Dec 31 {$year}';$('#btnSubmit').click();">{$year}</a>
	{/foreach}
	<br>
{/if}

<!-- Chart -->

<div id="placeholder" style="margin:1em;width:650px;height:{20+(32*count($data))}px;"></div>

<script language="javascript" type="text/javascript">
	$(function() {
		var d = [
			{foreach from=$data item=row key=iter}
			[{$row.hits}, {$iter}],
			{/foreach}
		];
		
		var options = {
			lines: { show: false, fill: false },
			bars: { show: true, fill: true, horizontal: true, align: "center", barWidth: 1 },
			points: { show: false, fill: false },
			grid: {
				borderWidth: 0,
				horizontalLines: false,
				hoverable: false,
			},
			xaxis: {
				min: 0,
				minTickSize: 1,
				tickFormatter: function(val, axis) {
					return Math.floor(val).toString();
				},
			},
			yaxis: {
				ticks: [
					{foreach from=$data item=row key=iter}
					[{$iter},"<b>{$groups.{$row.group_id}->name|escape:'quotes'}</b>"],
					{/foreach}
				]
			}
		} ;
		
		$.plot($("#placeholder"), [d], options);
	} );
</script>

<!-- Table -->

{if !empty($group_counts)}
	<table cellspacing="0" cellpadding="2" border="0">
	{foreach from=$groups key=group_id item=group}
		{assign var=counts value=$group_counts.$group_id}
		{if !empty($counts.total)}
			<tr>
				<td colspan="3" style="border-bottom:1px solid rgb(200,200,200);padding-right:20px;"><h2>{$groups.$group_id->name}</h2></td>
			</tr>
			
			{if !empty($counts.0)}
			<tr>
				<td style="padding-left:10px;padding-right:20px;">Inbox</td>
				<td align="right">{$counts.0}</td>
				<td></td>
			</tr>
			{/if}
			
			{foreach from=$group_buckets.$group_id key=bucket_id item=b}
				{if !empty($counts.$bucket_id)}
				<tr>
					<td style="padding-left:10px;padding-right:20px;">{$b->name}</td>
					<td align="right">{$counts.$bucket_id}</td>
					<td></td>
				</tr>
				{/if}
			{/foreach}

			<tr>
				<td></td>						
				<td align="right" style="border-top:1px solid rgb(200,200,200);"><b>{$counts.total}</b></td>
				{math assign="avg_new" equation="x/y" x=$counts.total y=$age_dur format="%0.2f"}
				<td style="padding-left:10px;"><b>{'reports.ui.average_per_day'|devblocks_translate:$avg_new}</b></td>
			</tr>
		{/if}
	{/foreach}
	</table>
{/if}

