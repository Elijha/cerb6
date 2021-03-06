{$page_context = CerberusContexts::CONTEXT_GROUP}
{$page_context_id = $group->id}

{$members = $group->getMembers()}
{$reply_to = $group->getReplyTo()}

{$gravatar_plugin = DevblocksPlatform::getPlugin('cerberusweb.gravatar')}
{$gravatar_enabled = $gravatar_plugin && $gravatar_plugin->enabled}

<table cellpadding="0" cellspacing="0" border="0" width="100%">
	<tr>
		<td width="1%" nowrap="nowrap" rowspan="2" valign="top" style="padding-left:10px;">
			{if $gravatar_enabled}
			<img src="{if $is_ssl}https://secure.{else}http://www.{/if}gravatar.com/avatar/{$reply_to->email|trim|lower|md5}?s=64&d=http://cerbweb.com/gravatar/gravatar_nouser.jpg" height="64" width="64" border="0" style="margin:0px 5px 5px 0px;">
			{/if}
		</td>
		<td width="98%" valign="top">
			<h1 style="color:rgb(0,120,0);font-weight:bold;font-size:150%;margin:0px;">{$group->name}</h1>
			{$reply_to->email}<br>
		</td>
		<td width="1%" nowrap="nowrap" align="right">
			{$ctx = Extension_DevblocksContext::get($page_context)}
			{include file="devblocks:cerberusweb.core::search/quick_search.tpl" view=$ctx->getSearchView() return_url="{devblocks_url}c=search&context={$ctx->manifest->params.alias}{/devblocks_url}" reset=true}
		</td>
	</tr>
	<tr>
		<td colspan="2">
			{if !empty($members)}
			<ul class="bubbles">
				{$member_count = $members|count}
				<li><span style="font-weight:bold;">{$member_count} {if $member_count==1}member{else}members{/if}</span></li>
			</ul>
			{/if}
		</td>
	</tr>
</table>

<div style="clear:both;"></div>

<div class="cerb-profile-toolbar">
	<form class="toolbar" action="javascript:;" method="POST" style="margin-top:5px;" onsubmit="return false;">
		<!-- Macros -->
		{if $active_worker->isGroupManager($group->id) || $active_worker->is_superuser}
			{if !empty($page_context) && !empty($page_context_id) && !empty($macros)}
				{devblocks_url assign=return_url full=true}c=profiles&tab=group&id={$page_context_id}-{$group->name|devblocks_permalink}{/devblocks_url}
				{include file="devblocks:cerberusweb.core::internal/macros/display/button.tpl" context=$page_context context_id=$page_context_id macros=$macros return_url=$return_url}
			{/if}
		{/if}
	
		{if $active_worker->is_superuser}
			<button type="button" id="btnProfileGroupEdit" title="{'common.edit'|devblocks_translate|capitalize}">&nbsp;<span class="cerb-sprite2 sprite-gear"></span>&nbsp;</button>
		{/if}
	</form>
</div>

<fieldset class="properties">
	<legend>Group</legend>
	
	<div style="margin-left:15px;">
	{if !empty($properties)}
	{foreach from=$properties item=v key=k name=props}
		<div class="property">
			{if $k == '...'}
				<b>{'...'|devblocks_translate|capitalize}:</b>
				...
			{else}
				{include file="devblocks:cerberusweb.core::internal/custom_fields/profile_cell_renderer.tpl"}
			{/if}
		</div>
		{if $smarty.foreach.props.iteration % 3 == 0 && !$smarty.foreach.props.last}
			<br clear="all">
		{/if}
	{/foreach}
	<br clear="all">
	{/if}
	</div>
</fieldset>

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/profile_fieldsets.tpl" properties=$properties_custom_fieldsets}

<div>
{include file="devblocks:cerberusweb.core::internal/notifications/context_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div>
{include file="devblocks:cerberusweb.core::internal/macros/behavior/scheduled_behavior_profile.tpl" context=$page_context context_id=$page_context_id}
</div>

<div id="profileTabs">
	<ul>
		{$tabs = []}
		{$point = "cerberusweb.profiles.group.{$group->id}"}
		
		{$tabs[] = 'activity'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabActivityLog&scope=both&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}">{'common.activity_log'|devblocks_translate|capitalize}</a></li>
		
		{$tabs[] = 'comments'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabContextComments&context={$page_context}&id={$page_context_id}{/devblocks_url}">{'common.comments'|devblocks_translate|capitalize}</a></li>
		
		{$tabs[] = 'members'}
		<li><a href="#members">Members</a></li>

		{if $active_worker->is_superuser || $active_worker->isGroupManager($group->id)}
		{$tabs[] = 'attendants'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showAttendantsTab&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}">Virtual Attendants</a></li>
		{/if}

		{if $active_worker->is_superuser || $active_worker->isGroupManager($group->id)}
		{$tabs[] = 'custom_fieldsets'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=handleSectionAction&section=custom_fieldsets&action=showTabCustomFieldsets&context={$page_context}&context_id={$page_context_id}&point={$point}{/devblocks_url}">{'common.custom_fieldsets'|devblocks_translate|capitalize}</a></li>
		{/if}

		{if $active_worker->is_superuser || $active_worker->isGroupManager($group->id)}
		{$tabs[] = 'snippets'}
		<li><a href="{devblocks_url}ajax.php?c=internal&a=showTabSnippets&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}">{'common.snippets'|devblocks_translate|capitalize}</a></li>
		{/if}

		{foreach from=$tab_manifests item=tab_manifest}
			{$tabs[] = $tab_manifest->params.uri}
			<li><a href="{devblocks_url}ajax.php?c=profiles&a=showTab&ext_id={$tab_manifest->id}&point={$point}&context={$page_context}&context_id={$page_context_id}{/devblocks_url}"><i>{$tab_manifest->params.title|devblocks_translate}</i></a></li>
		{/foreach}
	</ul>
	
	<div id="members">
		{foreach from=$members item=member}
		{if isset($workers.{$member->id})}
			{$worker = $workers.{$member->id}}
			<fieldset>
				<div style="float:left;">
					{if $gravatar_enabled}
					<img src="{if $is_ssl}https://secure.{else}http://www.{/if}gravatar.com/avatar/{$worker->email|trim|lower|md5}?s=64&d=http://cerbweb.com/gravatar/gravatar_nouser.jpg" height="64" width="64" border="0" style="margin:0px 5px 5px 0px;">
					{/if}
				</div>
				<div style="float:left;">
					<a href="{devblocks_url}c=profiles&k=worker&id={$worker->id}-{$worker->getName()|devblocks_permalink}{/devblocks_url}" style="color:rgb(0,120,0);font-weight:bold;font-size:150%;margin:0px;">{$worker->getName()}</a><br>
					{if !empty($worker->title)}{$worker->title}<br>{/if}

					{if $member->is_manager}
					<ul class="bubbles">
						<li style="font-weight:bold;">Manager</li>
					</ul>
					{/if}
				</div>
			</fieldset>
		{/if}
		{/foreach}
	</div>
</div> 

<br>

{$selected_tab_idx=0}
{foreach from=$tabs item=tab_label name=tabs}
	{if $tab_label==$selected_tab}{$selected_tab_idx = $smarty.foreach.tabs.index}{/if}
{/foreach}

<script type="text/javascript">
$(function() {
	var tabOptions = Devblocks.getDefaultjQueryUiTabOptions();
	tabOptions.active = {$selected_tab_idx};
	
	var tabs = $("#profileTabs").tabs(tabOptions);

	{if $active_worker->is_superuser}
	$('#btnProfileGroupEdit').bind('click', function() {
		$popup = genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={$page_context}&context_id={$page_context_id}',null,false,'550');
		$popup.one('group_save', function(event) {
			event.stopPropagation();
			document.location.href = '{devblocks_url}c=profiles&k=group&id={$group->id}-{$group->name|devblocks_permalink}{/devblocks_url}';
		});
	});
	{/if}
	
	{include file="devblocks:cerberusweb.core::internal/macros/display/menu_script.tpl" selector_button=null selector_menu=null}
});
</script>

{$profile_scripts = Extension_ContextProfileScript::getExtensions(true, $page_context)}
{if !empty($profile_scripts)}
{foreach from=$profile_scripts item=renderer}
	{if method_exists($renderer,'renderScript')}
		{$renderer->renderScript($page_context, $page_context_id)}
	{/if}
{/foreach}
{/if}

