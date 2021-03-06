{if empty($workers)}{$workers = DAO_Worker::getAll()}{/if}

<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmTimeEntry">
<input type="hidden" name="c" value="timetracking">
<input type="hidden" name="a" value="saveEntry">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
{if !empty($link_context)}
<input type="hidden" name="link_context" value="{$link_context}">
<input type="hidden" name="link_context_id" value="{$link_context_id}">
{/if}
<input type="hidden" name="do_delete" value="0">

<fieldset class="peek">
	<legend>{'common.properties'|devblocks_translate}</legend>
	<table cellpadding="2" cellspacing="0" width="100%">
		{if !empty($model->worker_id) && isset($workers.{$model->worker_id})}
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right"><b>{'common.worker'|devblocks_translate|capitalize}</b>:</td>
			<td width="100%">
				{$workers.{$model->worker_id}->getName()}
			</td>
		</tr>
		{/if}
		{if !empty($activities)}
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right"><b>{'timetracking.ui.entry_panel.activity'|devblocks_translate}</b>:</td>
			<td width="100%">
				<select name="activity_id">
					<option value=""></option>
					{foreach from=$activities item=activity}
					<option value="{$activity->id}" {if $model->activity_id==$activity->id}selected{/if}>{$activity->name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
		{/if}
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right"><b>{'timetracking.ui.entry_panel.time_spent'|devblocks_translate}</b>:</td>
			<td width="100%">
				<input type="text" name="time_actual_mins" size="5" value="{$model->time_actual_mins}"> {'timetracking.ui.entry_panel.mins'|devblocks_translate}
			</td>
		</tr>
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right"><b>{'timetracking_entry.log_date'|devblocks_translate|capitalize}</b>:</td>
			<td width="100%">
				<input type="text" name="log_date" size="64" class="input_date" value="{$model->log_date|devblocks_date}"> 
			</td>
		</tr>
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right"><b>{'common.status'|devblocks_translate|capitalize}</b>:</td>
			<td width="100%">
				<label><input type="radio" name="is_closed" value="0" {if !$model->is_closed}checked="checked"{/if}> {'status.open'|devblocks_translate|capitalize}</label>
				<label><input type="radio" name="is_closed" value="1" {if $model->is_closed}checked="checked"{/if}> {'status.closed'|devblocks_translate|capitalize}</label>
			</td>
		</tr>
		
		{* Watchers *}
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right">{'common.watchers'|devblocks_translate|capitalize}: </td>
			<td width="100%">
				{if empty($model->id)}
					<button type="button" class="chooser_watcher"><span class="cerb-sprite sprite-view"></span></button>
					<ul class="chooser-container bubbles" style="display:block;"></ul>
				{else}
					{$object_watchers = DAO_ContextLink::getContextLinks(CerberusContexts::CONTEXT_TIMETRACKING, array($model->id), CerberusContexts::CONTEXT_WORKER)}
					{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context=CerberusContexts::CONTEXT_TIMETRACKING context_id=$model->id full=true}
				{/if}
			</td>
		</tr>

	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>{'common.custom_fields'|devblocks_translate}</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_TIMETRACKING context_id=$model->id}

{* Comments *}
{include file="devblocks:cerberusweb.core::internal/peek/peek_comments_pager.tpl" comments=$comments}

<fieldset class="peek">
	<legend>{'common.comment'|devblocks_translate|capitalize}</legend>
	<textarea name="comment" rows="5" cols="45" style="width:98%;" title="{'comment.notify.at_mention'|devblocks_translate}"></textarea>
</fieldset>

{if $model->context && $model->context_id}
<input type="hidden" name="context" value="{$model->context}">
<input type="hidden" name="context_id" value="{$model->context_id}">
{/if}

{if ($active_worker->hasPriv('timetracking.actions.create') && (empty($model->id) || $active_worker->id==$model->worker_id))
	|| $active_worker->hasPriv('timetracking.actions.update_all')}
	{if empty($model->id)}
		<button type="button" onclick="timeTrackingTimer.finish();genericAjaxPopupPostCloseReloadView(null,'frmTimeEntry','{$view_id}',false,'timetracking_save');"><span class="cerb-sprite2 sprite-tick-circle"></span> {'timetracking.ui.entry_panel.save_finish'|devblocks_translate}</button>
		<button type="button" onclick="timeTrackingTimer.play();genericAjaxPopupClose('peek');"><span class="cerb-sprite sprite-media_play_green"></span> {'timetracking.ui.entry_panel.resume'|devblocks_translate}</button>
		<button type="button" onclick="timeTrackingTimer.finish();genericAjaxPopupClose('peek');"><span class="cerb-sprite sprite-media_stop_red"></span> {'common.cancel'|devblocks_translate|capitalize}</button>
	{else}
		<button type="button" onclick="genericAjaxPopupPostCloseReloadView(null,'frmTimeEntry','{$view_id}',false,'timetracking_save');"><span class="cerb-sprite2 sprite-tick-circle"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
		<button type="button" onclick="if(confirm('Permanently delete this time tracking entry?')) { this.form.do_delete.value='1'; genericAjaxPopupPostCloseReloadView(null,'frmTimeEntry','{$view_id}',false,'timetracking_delete'); } "><span class="cerb-sprite2 sprite-cross-circle"></span> {'common.delete'|devblocks_translate|capitalize}</button>
	{/if}
{else}
	<div class="error">You do not have permission to modify this record.</div>
{/if}

{if !empty($model->id)}
<div style="float:right;">
	<a href="{devblocks_url}c=profiles&type=time_tracking&id={$model->id}{/devblocks_url}">view full record</a>
</div>
<br clear="all">
{/if}
</form>

<script type="text/javascript">
	var $popup = genericAjaxPopupFetch('peek');
	
	$popup.one('popup_open',function(event,ui) {
		var $frm = $('#frmTimeEntry');
		var $textarea = $(this).find('textarea[name=comment]');
		
		$(this).dialog('option','title',"{'timetracking.ui.timetracking'|devblocks_translate|escape:'javascript' nofilter}");
		
		$(this).find('button.chooser_watcher').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.worker','add_watcher_ids', { autocomplete:true });
		});
		
		$frm.find('> fieldset:first input.input_date').cerbDateInputHelper();
		
		$frm.find('button.chooser_worker').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.worker','worker_id', { autocomplete:true });
		});
		
		// Tooltips
		
		$popup.find(':input[title]').tooltip({
			position: {
				my: 'left top',
				at: 'left+10 bottom+5'
			}
		});
		
		$textarea.tooltip({
			position: {
				my: 'right bottom',
				at: 'right top'
			}
		});
		
		// @mentions
		
		var atwho_workers = {CerberusApplication::getAtMentionsWorkerDictionaryJson() nofilter};

		$textarea.atwho({
			at: '@',
			{literal}tpl: '<li data-value="@${at_mention}">${name} <small style="margin-left:10px;">${title}</small></li>',{/literal}
			data: atwho_workers,
			limit: 10
		});
	});
</script>
