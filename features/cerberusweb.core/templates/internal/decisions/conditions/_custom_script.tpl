<textarea name="{$namePrefix}[tpl]" cols="45" rows="5" style="width:100%;height:75px;" class="placeholders" spellcheck="false">{$params.tpl}</textarea>

<select name="{$namePrefix}[oper]">
	<option value="is" {if $params.oper=='is'}selected="selected"{/if}>is</option>
	<option value="!is" {if $params.oper=='!is'}selected="selected"{/if}>is not</option>
	<option value="contains" {if $params.oper=='contains'}selected="selected"{/if}>contains this phrase</option>
	<option value="!contains" {if $params.oper=='!contains'}selected="selected"{/if}>does not contain this phrase</option>
	<option value="like" {if $params.oper=='like'}selected="selected"{/if}>matches (*) wildcards</option>
	<option value="!like" {if $params.oper=='!like'}selected="selected"{/if}>does not match wildcards</option>
	<option value="regexp" {if $params.oper=='regexp'}selected="selected"{/if}>matches regular expression</option>
	<option value="!regexp" {if $params.oper=='!regexp'}selected="selected"{/if}>does not match regular expression</option>
</select>
<br>

<input type="text" name="{$namePrefix}[value]" value="{$params.value}" size="45">

<script type="text/javascript">
$(function() {
	var $condition = $('li#{$namePrefix}');
	var $textarea = $condition.find('textarea');
	$textarea.elastic();
	
	// Snippet syntax
	$textarea
		.atwho({
			{literal}at: '{%',{/literal}
			limit: 20,
			{literal}tpl: '<li data-value="${name}">${content} <small style="margin-left:10px;">${name}</small></li>',{/literal}
			data: atwho_twig_commands,
			suffix: ''
		})
		.atwho({
			{literal}at: '|',{/literal}
			limit: 20,
			start_with_space: false,
			search_key: "content",
			{literal}tpl: '<li data-value="|${name}">${content} <small style="margin-left:10px;">${name}</small></li>',{/literal}
			data: atwho_twig_modifiers,
			suffix: ''
		})
		;
})
</script>
