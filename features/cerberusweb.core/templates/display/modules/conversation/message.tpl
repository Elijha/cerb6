{assign var=headers value=$message->getHeaders()}
<div class="block" style="margin-bottom:10px;">
<table style="text-align: left; width: 98%;table-layout: fixed;" border="0" cellpadding="2" cellspacing="0">
  <tbody>
	<tr>
	  <td>
		{assign var=sender_id value=$message->address_id}
		{if isset($message_senders.$sender_id)}
			{assign var=sender value=$message_senders.$sender_id}
			{assign var=sender_org_id value=$sender->contact_org_id}
			{assign var=sender_org value=$message_sender_orgs.$sender_org_id}
			{assign var=is_outgoing value=$message->is_outgoing}
			{assign var=is_not_sent value=$message->is_not_sent}

			<div class="toolbar-minmax" style="display:none;float:right;">
				<button id="btnMsgMax{$message->id}" style="display:none;visibility:hidden;" onclick="genericAjaxGet('{$message->id}t','c=display&a=getMessage&id={$message->id}');"></button>
				<button id="btnMsgMin{$message->id}" style="display:none;visibility:hidden;" onclick="genericAjaxGet('{$message->id}t','c=display&a=getMessage&id={$message->id}&hide=1');"></button>
			{if !$expanded}
				<a href="javascript:;" onclick="$('#btnMsgMax{$message->id}').click();">{'common.maximize'|devblocks_translate|lower}</a>
			{else}
				<a href="javascript:;" onclick="$('#btnMsgMin{$message->id}').click();">{'common.minimize'|devblocks_translate|lower}</a>
			{/if}
			</div>
		
			<span class="tag" style="{if !$is_outgoing}color:rgb(185,50,40);{else}color:rgb(100,140,25);{/if}">{if $is_outgoing}{if $is_not_sent}{'mail.saved'|devblocks_translate|lower}{else}{'mail.sent'|devblocks_translate|lower}{/if}{else}{'mail.received'|devblocks_translate|lower}{/if}</span>
			
			{if $expanded}
			<b style="font-size:1.3em;">
			{if $message->worker_id && isset($workers.{$message->worker_id})}
				{$msg_worker = $workers.{$message->worker_id}}
				<a href="javascript:;" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_ADDRESS}&email={$msg_worker->email|escape:'url'}', null, false, '500', function() { C4_ReloadMessageOnSave('{$message->id}', {if $expanded}true{else}false{/if}); } );" title="{$sender->email}">{if 0 != strlen($msg_worker->getName())}{$msg_worker->getName()}{else}&lt;{$msg_worker->email}&gt;{/if}</a>
			{else}
				<a href="javascript:;" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_ADDRESS}&context_id={$sender_id}', null, false, '500', function() { C4_ReloadMessageOnSave('{$message->id}', {if $expanded}true{else}false{/if}); } );" title="{$sender->email}">{if 0 != strlen($sender->getName())}{$sender->getName()}{else}&lt;{$sender->email}&gt;{/if}</a>
			{/if}
			</b>
			{else}
			<b>
			{if $message->worker_id && isset($workers.{$message->worker_id})}
				{$msg_worker = $workers.{$message->worker_id}}
				<a href="javascript:;" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_ADDRESS}&email={$msg_worker->email|escape:'url'}', null, false, '500', function() { C4_ReloadMessageOnSave('{$message->id}', {if $expanded}true{else}false{/if}); } );">{if 0 != strlen($msg_worker->getName())}{$msg_worker->getName()}{else}&lt;{$msg_worker->email}&gt;{/if}</a>
			{else}
				<a href="javascript:;" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_ADDRESS}&context_id={$sender_id}', null, false, '500', function() { C4_ReloadMessageOnSave('{$message->id}', {if $expanded}true{else}false{/if}); } );">{if 0 != strlen($sender->getName())}{$sender->getName()}{else}&lt;{$sender->email}&gt;{/if}</a>
			{/if}
			</b>
			{/if}
			
			&nbsp;
			
			{if $sender_org_id}
				<a href="javascript:;" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_ORG}&context_id={$sender_org_id}', null, false, '500', function() { C4_ReloadMessageOnSave('{$message->id}',{if $expanded}true{else}false{/if}); } );">{$sender_org->name}</a>
			{else}{* No org *}
				{if $active_worker->hasPriv('core.addybook.addy.actions.update')}<a href="javascript:;" onclick="genericAjaxPopup('peek','c=internal&a=showPeekPopup&context={CerberusContexts::CONTEXT_ADDRESS}&context_id={$sender_id}', null, false, '500', function() { C4_ReloadMessageOnSave('{$message->id}', {if $expanded}true{else}false{/if}); } );"><span style="background-color:rgb(255,255,194);">{'display.convo.set_org'|devblocks_translate|lower}</span></a>{/if}
			{/if}
			
			{$extensions = DevblocksPlatform::getExtensions('cerberusweb.message.badge', true)}
			{foreach from=$extensions item=extension}
				{$extension->render($message)}
			{/foreach}
			
			<br>
		{/if}
	  
	  <div id="{$message->id}sh" style="display:block;">
	  {if isset($headers.from)}<b>{'message.header.from'|devblocks_translate|capitalize}:</b> {$headers.from|escape|nl2br nofilter}<br>{/if}
	  {if isset($headers.to)}<b>{'message.header.to'|devblocks_translate|capitalize}:</b> {$headers.to|escape|nl2br nofilter}<br>{/if}
	  {if isset($headers.cc)}<b>{'message.header.cc'|devblocks_translate|capitalize}:</b> {$headers.cc|escape|nl2br nofilter}<br>{/if}
	  {if isset($headers.bcc)}<b>{'message.header.bcc'|devblocks_translate|capitalize}:</b> {$headers.bcc|escape|nl2br nofilter}<br>{/if}	  
	  {if isset($headers.subject)}<b>{'message.header.subject'|devblocks_translate|capitalize}:</b> {$headers.subject|escape|nl2br nofilter}<br>{/if}
	  {if isset($headers.date)}
	  	<b>{'message.header.date'|devblocks_translate|capitalize}:</b> {$message->created_date|devblocks_date} (<abbr title="{$headers.date}">{$message->created_date|devblocks_prettytime}</abbr>)
	  	
		{if !empty($message->response_time)}
			<span style="margin-left:10px;color:rgb(100,140,25);">Replied in {$message->response_time|devblocks_prettysecs:2}</span>
		{/if}
	  	<br>
	  {/if}
	  </div>

	  <div id="{$message->id}h" style="display:none;">
	  	{if is_array($headers)}
	  	{foreach from=$headers item=headerValue key=headerKey}
	  		<b>{$headerKey|capitalize}:</b>
   			{$headerValue|escape|nl2br nofilter}<br>
	  	{/foreach}
	  	{/if}
	  </div>
	  
	  {if $expanded}
	  <div style="margin:2px;margin-left:10px;">
	  	 <a href="javascript:;" class="brief" onclick="if($(this).hasClass('brief')) { $('#{$message->id}sh').hide();$('#{$message->id}h').show();$(this).html('{'display.convo.brief_headers'|devblocks_translate|lower}').removeClass('brief'); } else { $('#{$message->id}sh').show();$('#{$message->id}h').hide();$(this).html('{'display.convo.full_headers'|devblocks_translate|lower}').addClass('brief'); } ">{'display.convo.full_headers'|devblocks_translate|lower}</a>
	  	 | <a href="#{$message->id}act">{'display.convo.skip_to_bottom'|devblocks_translate|lower}</a>
	  	 | <a href="{devblocks_url}c=profiles&type=ticket&mask={$ticket->mask}&jump=message&jump_id={$message->id}{/devblocks_url}">{'common.permalink'|devblocks_translate|lower}</a>
	  </div>
	  {/if}
	  
  	{if $expanded}
	  <div style="clear:both;display:block;padding-top:10px;">
		  	<pre class="emailbody">{$message->getContent()|trim|escape|devblocks_hyperlinks|devblocks_hideemailquotes nofilter}</pre>
		  	<br>
		  	<table width="100%" cellpadding="0" cellspacing="0" border="0">
		  		<tr>
		  			<td align="left" id="{$message->id}act">
						{* If not requester *}
						{if !$message->is_outgoing && !isset($requesters.{$sender_id})}
						<button type="button" onclick="$(this).remove(); genericAjaxGet('','c=display&a=requesterAdd&ticket_id={$ticket->id}&email='+encodeURIComponent('{$sender->email}'),function(o) { genericAjaxGet('displayTicketRequesterBubbles','c=display&a=requestersRefresh&ticket_id={$ticket->id}'); } );"><span class="cerb-sprite2 sprite-plus-circle"></span> {'display.ui.add_to_recipients'|devblocks_translate}</button>
						{/if}
						
					  	{if $active_worker->hasPriv('core.display.actions.reply')}
					  		<button type="button" class="reply split-left" onclick="displayReply('{$message->id}',0,0,{if empty($mail_reply_button)}1{else}0{/if});" title="{if empty($mail_reply_button)}{'display.reply.quote'|devblocks_translate}{else}{'display.reply.no_quote'|devblocks_translate}{/if}"><span class="cerb-sprite sprite-export"></span> {'display.ui.reply'|devblocks_translate|capitalize}</button><!--
					  		--><button type="button" class="split-right" onclick="$ul=$(this).next('ul');$ul.toggle();if($ul.is(':hidden')) { $ul.blur(); } else { $ul.find('a:first').focus(); }"><span class="cerb-sprite sprite-arrow-down-white"></span></button>
					  		<ul class="cerb-popupmenu cerb-float" style="margin-top:-5px;">
					  			<li><a href="javascript:;" onclick="displayReply('{$message->id}',0,0,1);">{'display.reply.quote'|devblocks_translate}</a></li>
					  			<li><a href="javascript:;" onclick="displayReply('{$message->id}',0,0,0);">{'display.reply.no_quote'|devblocks_translate}</a></li>
					  			{if $active_worker->hasPriv('core.display.actions.forward')}<li><a href="javascript:;" onclick="displayReply('{$message->id}',1);">{'display.ui.forward'|devblocks_translate|capitalize}</a></li>{/if}
					  			<li><a href="javascript:;" class="relay" data-message-id="{$message->id}">Relay to worker email</a></li>
					  		</ul>
					  	{/if}
					  	
					  	{if $active_worker->hasPriv('core.display.actions.note')}<button type="button" onclick="displayAddNote('{$message->id}');"><span class="cerb-sprite sprite-document_plain_yellow"></span> {'display.ui.sticky_note'|devblocks_translate|capitalize}</button>{/if}
					  	
					  	 &nbsp; 
				  		<button type="button" onclick="toggleDiv('{$message->id}options');">{'common.more'|devblocks_translate|lower} &raquo;</button>
		  			</td>
		  		</tr>
		  	</table>
		  	
		  	<form id="{$message->id}options" style="padding-top:10px;display:none;" method="post" action="{devblocks_url}{/devblocks_url}">
		  		<input type="hidden" name="c" value="display">
		  		<input type="hidden" name="a" value="">
		  		<input type="hidden" name="id" value="{$message->id}">
		  		
		  		<button type="button" onclick="document.frmPrint.action='{devblocks_url}c=print&a=message&id={$message->id}{/devblocks_url}';document.frmPrint.submit();"><span class="cerb-sprite sprite-printer"></span> {'common.print'|devblocks_translate|capitalize}</button>
		  		
		  		{if $ticket->first_message_id != $message->id && $active_worker->hasPriv('core.display.actions.split')} {* Don't allow splitting of a single message *}
		  		<button type="button" onclick="$frm=$(this).closest('form');$frm.find('input:hidden[name=a]').val('doSplitMessage');$frm.submit();" title="Split message into new ticket"><span class="cerb-sprite sprite-documents"></span> {'display.button.split_ticket'|devblocks_translate|capitalize}</button>
		  		{/if}
		  		
				{if $active_worker->hasPriv('core.display.message.actions.delete')}
				<button type="button" onclick="if(!confirm('Are you sure you want to delete this message?'))return; $frm=$(this).closest('form');$frm.find('input:hidden[name=a]').val('doDeleteMessage');$frm.submit();" title="Delete this message"><span class="cerb-sprite2 sprite-cross-circle"></span> {'common.delete'|devblocks_translate|capitalize}</button>
				{/if}
				
				{* Plugin Toolbar *}
				{if !empty($message_toolbaritems)}
					{foreach from=$message_toolbaritems item=renderer}
						{if !empty($renderer)}{$renderer->render($message)}{/if}
					{/foreach}
				{/if}
		  	</form>
		  	
		  	{if $active_worker->hasPriv('core.display.actions.attachments.download')}
			{include file="devblocks:cerberusweb.core::internal/attachments/list.tpl" context="{CerberusContexts::CONTEXT_MESSAGE}" context_id=$message->id}
			{/if}
		</div> <!-- end visible -->
	  	{/if}
	  </td>
	</tr>
  </tbody>
</table>
</div>
<div id="{$message->id}b"></div>
<div id="{$message->id}notes" style="background-color:rgb(255,255,255);">
	{include file="devblocks:cerberusweb.core::display/modules/conversation/notes.tpl"}
</div>
<div id="reply{$message->id}"></div>

<script type="text/javascript">
$('#{$message->id}t').hover(
	function() {
		$(this).find('div.toolbar-minmax').show();
	},
	function() {
		$(this).find('div.toolbar-minmax').hide();
	}
);
</script>

{if $active_worker->hasPriv('core.display.actions.reply')}
<script type="text/javascript">
$('#{$message->id}act')
	.find('ul.cerb-popupmenu')
	.hover(
		function(e) { }, 
		function(e) { $(this).hide(); }
	)
	.find('> li')
	.click(function(e) {
		$(this).closest('ul.cerb-popupmenu').hide();

		e.stopPropagation();
		if(!$(e.target).is('li'))
		return;

		$(this).find('a').trigger('click');
	})
;

$('#{$message->id}act')
	.find('li a.relay')
	.click(function() {
		genericAjaxPopup('relay', 'c=display&a=showRelayMessagePopup&id={$message->id}', null, false, '500');
	})
	;

</script>
{/if}

<script type="text/javascript">
	function C4_ReloadMessageOnSave(msgid, expanded) {
		if(null==expanded)
			expanded=false;
		
		var $popup = genericAjaxPopupFetch('peek');
		$popup.one('popup_saved',function(e) {
			if(expanded) 
				$('#btnMsgMax' + msgid).click();
			else 
				$('#btnMsgMin' + msgid).click();
		} );
	}
</script>
