<div style="float:right;">
{include file="devblocks:cerberusweb.core::search/quick_search.tpl" view=$view return_url=null reset=false is_popup=true}
</div>

<div style="clear:both;"></div>

{include file="devblocks:cerberusweb.core::internal/views/search_and_view.tpl" view=$view}

<form action="#" method="POST" id="chooser{$view->id}">
<button type="button" class="submit"><span class="cerb-sprite2 sprite-tick-circle"></span> Save Worklist</button>
</form>

<script type="text/javascript">
$(function() {
	var $popup = genericAjaxPopupFetch('{$layer}');
	
	$popup.one('popup_open',function(event,ui) {
		event.stopPropagation();
		$(this).dialog('option','title','{$context->manifest->name|escape:'javascript' nofilter} Worklist');
		
		var on_refresh = function() {
			var $worklist = $('#view{$view->id}').find('TABLE.worklist');
			$worklist.css('background','none');
			$worklist.css('background-color','rgb(100,100,100)');
			
			var $header = $worklist.find('> tbody > tr:first > td:first > span.title');
			$header.css('font-size', '14px');
			var $header_links = $worklist.find('> tbody > tr:first td:nth(1)');
			$header_links.children().each(function(e) {
				if(!$(this).is('a.minimal'))
					$(this).remove();
			});
			$header_links.find('a').css('font-size','11px');

			var $worklist_body = $('#view{$view->id}').find('TABLE.worklistBody');
			$worklist_body.find('a.subject').each(function() {
				$txt = $('<b class="subject">' + $(this).text() + '</b>');
				$txt.insertBefore($(this));
				$(this).remove();
			});
			
			var $actions = $('#{$view->id}_actions');
			$actions.html('');
		}
		
		on_refresh();

		$(this).delegate('DIV[id^=view]','view_refresh', on_refresh);
		
		$("form#chooser{$view->id} button.submit").click(function(event) {
			event.stopPropagation();
			var $popup = genericAjaxPopupFetch('{$layer}');
			
			genericAjaxGet('', 'c=internal&a=serializeView&view_id={$view->id}&context={$context}', function(json) {
				// Trigger event
				var event = jQuery.Event('chooser_save');
				event.view_name = json.view_name;
				event.worklist_model = json.worklist_model;
				$popup.trigger(event);
				
				genericAjaxPopupDestroy('{$layer}');
			});
		});
	});
	
	$popup.one('dialogclose', function(event) {
		event.stopPropagation();
		genericAjaxPopupDestroy('{$layer}');
	});
});
</script>