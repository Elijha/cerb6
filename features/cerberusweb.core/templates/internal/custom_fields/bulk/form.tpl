{if !empty($custom_fields)}
{$uniqid = uniqid()}
<table cellspacing="0" cellpadding="2" width="100%" id="cfields{$uniqid}">
	<!-- Custom Fields -->
	{foreach from=$custom_fields item=f key=f_id}
		{if !empty($field_wrapper)}
		{$field_name = "{$field_wrapper}[field_{$f_id}]"}
		{else}
		{$field_name = "field_{$f_id}"}
		{/if}
		<tr>
			<td width="1%" nowrap="nowrap" valign="top">
				{if $bulk}
				<label><input type="checkbox" onclick="toggleDiv('bulkOpts{$f_id}');" name="field_ids[]" value="{$f_id}">{$f->name}:</label>
				{else}
					<input type="hidden" name="field_ids[]" value="{$f_id}">
					{if $f->type=='U'}
						{if !empty($custom_field_values.$f_id)}<a href="{$custom_field_values.$f_id}" target="_blank">{$f->name}</a>{else}{$f->name}{/if}:
					{else}
						{$f->name}:
					{/if}
				{/if}
			</td>
			<td width="99%">
				<div id="bulkOpts{$f_id}" style="display:{if $bulk}none{else}block{/if};">
				{if $f->type==Model_CustomField::TYPE_SINGLE_LINE}
					<input type="text" name="{$field_name}" size="45" style="width:98%;" maxlength="255" value="{$custom_field_values.$f_id}">
				{elseif $f->type==Model_CustomField::TYPE_URL}
					<input type="text" name="{$field_name}" size="45" style="width:98%;" maxlength="255" value="{$custom_field_values.$f_id}" class="url">
				{elseif $f->type==Model_CustomField::TYPE_NUMBER}
					<input type="text" name="{$field_name}" size="45" style="width:98%;" maxlength="255" value="{$custom_field_values.$f_id}" class="number">
				{elseif $f->type==Model_CustomField::TYPE_MULTI_LINE}
					<textarea name="{$field_name}" rows="4" cols="50" style="width:98%;">{$custom_field_values.$f_id}</textarea>
				{elseif $f->type==Model_CustomField::TYPE_CHECKBOX}
					<label><input type="checkbox" name="{$field_name}" value="1" {if $custom_field_values.$f_id}checked="checked"{/if}> {'common.yes'|devblocks_translate|capitalize}</label>
				{elseif $f->type==Model_CustomField::TYPE_MULTI_CHECKBOX}
					{if $bulk}
						{foreach from=$f->params.options item=opt}
							<select name="{$field_name}[]">
								<option value=""></option>
								<option value="+{$opt}">set</option>
								<option value="-{$opt}">unset</option>
							</select>
							{$opt}
							<br>
						{/foreach}
					{else}
						{foreach from=$f->params.options item=opt}
						<label><input type="checkbox" name="{$field_name}[]" value="{$opt}" {if isset($custom_field_values.$f_id.$opt)}checked="checked"{/if}> {$opt}</label><br>
						{/foreach}
					{/if}
				{elseif $f->type==Model_CustomField::TYPE_DROPDOWN}
					<select name="{$field_name}">
						<option value=""></option>
						{foreach from=$f->params.options item=opt}
						<option value="{$opt}" {if $opt==$custom_field_values.$f_id}selected="selected"{/if}>{$opt}</option>
						{/foreach}
					</select>
				{elseif $f->type==Model_CustomField::TYPE_WORKER}
					{if empty($workers)}
						{$workers = DAO_Worker::getAllActive()}
					{/if}
					<select name="{$field_name}">
						<option value=""></option>
						{foreach from=$workers item=worker}
						<option value="{$worker->id}" {if $worker->id==$custom_field_values.$f_id}selected="selected"{/if}>{$worker->getName()}</option>
						{/foreach}
						{foreach from=$values_to_contexts item=context_data key=val_key}
							{if $context_data.context == CerberusContexts::CONTEXT_WORKER && !$context_data.is_multiple}
							<option value="{$val_key}" {if $val_key==$custom_field_values.$f_id}selected="selected"{/if}>(placeholder) {$context_data.label}</option>
							{/if}
						{/foreach}
						{foreach from=$trigger->variables item=var key=var_key}
							{if $var.type == Model_CustomField::TYPE_WORKER}
							<option value="{$var_key}" {if $var_key==$custom_field_values.$f_id}selected="selected"{/if}>(variable) {$var.label}</option>
							{/if}
						{/foreach}
					</select>
					<button type="button" onclick="$(this).siblings('select').val('{$active_worker->id}');">{'common.me'|devblocks_translate|lower}</button>
					<button type="button" onclick="$(this).siblings('select').val('');">{'common.nobody'|devblocks_translate|lower}</button>
				{elseif $f->type==Model_CustomField::TYPE_LINK}
					<button type="button" field_name="{$field_name}" class="chooser-abstract" context="{$f->params.context}"><span class="cerb-sprite sprite-view"></span></button>
					
					<ul class="bubbles chooser-container">
						{if $custom_field_values.$f_id}
							{CerberusContexts::getContext($f->params.context, $custom_field_values.$f_id, $cf_link_labels, $cf_link_values, null, true)}
							<li><input type="hidden" name="{$field_name}" value="{$custom_field_values.$f_id}">{$cf_link_values._label} <a href="javascript:;" onclick="$(this).parent().remove();"><span class="ui-icon ui-icon-trash" style="display:inline-block;width:14px;height:14px;"></span></a></li>
						{/if}
					</ul>
				{elseif $f->type==Model_CustomField::TYPE_FILE}
					<button type="button" field_name="{$field_name}" class="chooser-cfield-file">{'common.upload'|devblocks_translate|lower}</button>
					
					<ul class="bubbles chooser-container">
					{if $custom_field_values.$f_id}
						{$file_id = $custom_field_values.$f_id}
						{$file = DAO_Attachment::get($file_id)}
						<li><input type="hidden" name="{$field_name}" value="{$file->id}"><a href="{devblocks_url}c=files&guid={$file->storage_sha1hash}&file={$file->display_name|escape:'url'}{/devblocks_url}" target="_blank">{$file->display_name}</a> ({$file->storage_size|devblocks_prettybytes}) <a href="javascript:;" onclick="$(this).parent().remove();"><span class="ui-icon ui-icon-trash" style="display:inline-block;width:14px;height:14px;"></span></a></li>
					{/if}
					</ul>
				{elseif $f->type==Model_CustomField::TYPE_FILES}
					<button type="button" field_name="{$field_name}" class="chooser-cfield-files">{'common.upload'|devblocks_translate|lower}</button>
					<ul class="bubbles chooser-container">
					{foreach from=$custom_field_values.$f_id item=file_id}
						{$file = DAO_Attachment::get($file_id)}
						<li><input type="hidden" name="{$field_name}[]" value="{$file->id}"><a href="{devblocks_url}c=files&guid={$file->storage_sha1hash}&file={$file->display_name|escape:'url'}{/devblocks_url}" target="_blank">{$file->display_name}</a> ({$file->storage_size|devblocks_prettybytes}) <a href="javascript:;" onclick="$(this).parent().remove();"><span class="ui-icon ui-icon-trash" style="display:inline-block;width:14px;height:14px;"></span></a></li>
					{/foreach}
					</ul>
				{elseif $f->type==Model_CustomField::TYPE_DATE}
					<input type="text" id="{$field_name}" name="{$field_name}" class="input_date" size="45" maxlength="255" value="{if !empty($custom_field_values.$f_id)}{if is_numeric($custom_field_values.$f_id)}{$custom_field_values.$f_id|devblocks_date}{else}{$custom_field_values.$f_id}{/if}{/if}">
				{/if}
				</div>
			</td>
		</tr>
	{/foreach}
</table>

<script type="text/javascript">
var $cfields = $('#cfields{$uniqid}');

$cfields.find('input.input_date').cerbDateInputHelper();

$cfields.find('button.chooser-cfield-file').each(function() {
	var options = {
		single: true,
	};
	ajax.chooserFile(this,$(this).attr('field_name'),options);
});

$cfields.find('button.chooser-cfield-files').each(function() {
	ajax.chooserFile(this,$(this).attr('field_name'));
});

// Abstract choosers
$cfields.find('button.chooser-abstract').each(function() {
	$(this).click(function() {
		var $button = $(this);
		var ctx = $button.attr('context');
		
		$chooser = genericAjaxPopup('chooser' + new Date().getTime(),'c=internal&a=chooserOpen&context=' + encodeURIComponent(ctx) + '&single=1',null,true,'750');
		$chooser.one('chooser_save', function(event) {
			if(typeof event.values == "object" && event.values.length > 0) {
				var context_label = event.labels[0];
				var context_id = event.values[0];
				
				var $ul = $button.siblings('ul.chooser-container');
				var context = $button.attr('context');
				var field_name = $button.attr('field_name');
				
				// Clear previous selections
				$ul.find('li').remove();
				
				// Add new bubble
				for(i in event.labels) {
					$li = $('<li>' + event.labels[i] + '</li>'); // + ' (' + $context_name + ')
					$li.append($('<input type="hidden" name="' + field_name + '" value="' + event.values[i] + '">'));
					$li.append($('<span class="ui-icon ui-icon-trash" style="display:inline-block;vertical-align:middle;pointer:middle;" onclick="$(this).closest(\'li\').remove();"></span>'));
					
					$ul.append($li);
				}
			}
		});
	});
});
</script>
{/if}