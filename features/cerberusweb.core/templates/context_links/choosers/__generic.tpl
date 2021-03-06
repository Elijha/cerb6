<div style="float:right;">
{include file="devblocks:cerberusweb.core::search/quick_search.tpl" view=$view return_url=null reset=false is_popup=true}
</div>

<div style="clear:both;"></div>

{include file="devblocks:cerberusweb.core::internal/views/search_and_view.tpl" view=$view}

<form action="#" method="POST" id="chooser{$view->id}" style="{if $single}display:none;{/if}}">
	<b>Selected:</b>
	<ul class="buffer bubbles"></ul>
	<br>
	<br>
	<button type="button" class="submit"><span class="cerb-sprite2 sprite-tick-circle"></span> {'common.save_changes'|devblocks_translate}</button>
</form>
<br>

<script type="text/javascript">
$(function() {
	var $popup = genericAjaxPopupFetch('{$layer}');
	
	$popup.find('UL.buffer').sortable({ placeholder: 'ui-state-highlight' });
	
	$popup.one('popup_open',function(event,ui) {
		event.stopPropagation();

		$popup = $(this);
		
		$(this).dialog('option','title','{$context->manifest->name|escape:'javascript' nofilter} Chooser');
		
		$popup.find('input:text:first').focus();

		// Progressive de-enhancement
		
		var on_refresh = function() {
			$worklist = $('#view{$view->id}').find('TABLE.worklist');
			$worklist.css('background','none');
			$worklist.css('background-color','rgb(100,100,100)');
			
			$header = $worklist.find('> tbody > tr:first > td:first > span.title');
			$header.css('font-size', '14px');
			$header_links = $worklist.find('> tbody > tr:first td:nth(1)');
			$header_links.children().each(function(e) {
				if(!$(this).is('a.minimal, input:checkbox'))
					$(this).remove();
			});
			$header_links.find('a').css('font-size','11px');

			$worklist_body = $('#view{$view->id}').find('TABLE.worklistBody');
			$worklist_body.find('a.subject').each(function() {
				$txt = $('<b class="subject">' + $(this).text() + '</b>');
				$txt.insertBefore($(this));
				$(this).remove();
			});
			
			$actions = $('#{$view->id}_actions');
			$actions.html('');
		}
		
		on_refresh();

		$(this).delegate('DIV[id^=view]','view_refresh', on_refresh);
		
		$('#view{$view->id}').delegate('TABLE.worklistBody input:checkbox', 'check', function(event) {
			checked = $(this).is(':checked');

			$view = $('#viewForm{$view->id}');
			$buffer = $('form#chooser{$view->id} UL.buffer');

			$tbody = $(this).closest('tbody');

			$label = $tbody.find('b.subject').text();
			$value = $(this).val();
		
			if(checked) {
				if($label.length > 0 && $value.length > 0) {
					if(0==$buffer.find('input:hidden[value="'+$value+'"]').length) {
						$li = $('<li>'+$label+'<input type="hidden" name="to_context_id[]" title="'+$label+'" value="'+$value+'"><a href="javascript:;" onclick="$(this).parent().remove();"><span class="ui-icon ui-icon-trash" style="display:inline-block;width:14px;height:14px;"></span></a></li>');
						$buffer.append($li);
					}
					
					{if $single}
					$buffer.closest('form').find('button.submit').click();
					{/if}
				}
				
			} else {
				$buffer.find('input:hidden[value="'+$value+'"]').closest('li').remove();
			}
			
		});
		
		$("form#chooser{$view->id} button.submit").click(function(event) {
			event.stopPropagation();
			var $popup = genericAjaxPopupFetch('{$layer}');
			$buffer = $($popup).find('UL.buffer input:hidden');
			$labels = [];
			$values = [];
			
			$buffer.each(function() {
				$labels.push($(this).attr('title'));
				$values.push($(this).val());
			});
		
			// Trigger event
			event = jQuery.Event('chooser_save');
			event.labels = $labels;
			event.values = $values;
			$popup.trigger(event);
			
			genericAjaxPopupDestroy('{$layer}');
		});
	});
	
	$popup.one('dialogclose', function(event) {
		event.stopPropagation();
		genericAjaxPopupDestroy('{$layer}');
	});
	
});
</script>