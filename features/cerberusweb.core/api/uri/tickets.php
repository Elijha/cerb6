<?php
/***********************************************************************
| Cerberus Helpdesk(tm) developed by WebGroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2012, WebGroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
***********************************************************************/

class ChTicketsPage extends CerberusPageExtension {
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		
		return $worker->hasPriv('core.mail');
	}
	
	function getActivity() {
		return new Model_Activity('activity.tickets',array(
	    	""
	    ));
	}
	
	function render() {
	}
	
	// Ajax
	function showTabAction() {
		@$ext_id = DevblocksPlatform::importGPC($_REQUEST['ext_id'],'string','');
		
		$visit = CerberusApplication::getVisit();
		
		if(null != ($tab_mft = DevblocksPlatform::getExtension($ext_id)) 
			&& null != ($inst = $tab_mft->createInstance()) 
			&& $inst instanceof Extension_MailTab) {
				$visit->set(Extension_MailTab::POINT, $inst->manifest->params['uri']);
				$inst->showTab();
		}
	}
	
	// Post
	function saveTabAction() {
		@$ext_id = DevblocksPlatform::importGPC($_REQUEST['ext_id'],'string','');
		
		if(null != ($tab_mft = DevblocksPlatform::getExtension($ext_id)) 
			&& null != ($inst = $tab_mft->createInstance()) 
			&& $inst instanceof Extension_MailTab) {
				$inst->saveTab();
		}
	}
	
	/*
	 * Proxy any func requests to be handled by the tab directly, 
	 * instead of forcing tabs to implement controllers.  This should check 
	 * for the *Action() functions just as a handleRequest would
	 */
	function handleTabActionAction() {
		@$tab = DevblocksPlatform::importGPC($_REQUEST['tab'],'string','');
		@$action = DevblocksPlatform::importGPC($_REQUEST['action'],'string','');

		if(null != ($tab_mft = DevblocksPlatform::getExtension($tab)) 
			&& null != ($inst = $tab_mft->createInstance()) 
			&& $inst instanceof Extension_MailTab) {
				if(method_exists($inst,$action.'Action')) {
					call_user_func(array(&$inst, $action.'Action'));
				}
		}
	}	
	
	function viewTicketsExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::getUrlService();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time()); 
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}
		
		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = array();
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
					'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=tickets', true),
				);
				$models[] = $model; 
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $ticket_id => $row) {
				if($ticket_id==$explore_from)
					$orig_pos = $pos;
				
				$url = $url_writer->writeNoProxy(sprintf("c=profiles&type=ticket&id=%s", $row[SearchFields_Ticket::TICKET_MASK]), true);

				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $row[SearchFields_Ticket::TICKET_ID],
					'url' => $url,
				);
				$models[] = $model; 
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
	
	function viewMessagesExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::getUrlService();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time()); 
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}
		
		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = array();
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
					//'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=tickets&tab=messages', true),
//					'toolbar_extension_id' => 'cerberusweb.explorer.toolbar.',
				);
				$models[] = $model; 
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $id => $row) {
				if($id==$explore_from)
					$orig_pos = $pos;
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $id,
					'url' => $url_writer->writeNoProxy(sprintf("c=profiles&type=ticket&id=%s", $row[SearchFields_Message::TICKET_MASK]), true),
				);
				$models[] = $model; 
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}	
	
	// Ajax
	function reportSpamAction() {
	    @$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer');
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['viewId'],'string');
	    if(empty($id)) return;

		$fields = array(
				DAO_Ticket::IS_CLOSED => 1,
				DAO_Ticket::IS_DELETED => 1,
		);
	    
	    
        //====================================
	    // Undo functionality
        $last_action = new Model_TicketViewLastAction();
        $last_action->action = Model_TicketViewLastAction::ACTION_SPAM;

		$last_action->ticket_ids[$id] = array(
				DAO_Ticket::SPAM_TRAINING => CerberusTicketSpamTraining::BLANK,
				DAO_Ticket::SPAM_SCORE => 0.5000, // [TODO] Fix
				DAO_Ticket::IS_CLOSED => 0,
				DAO_Ticket::IS_DELETED => 0
		);

        $last_action->action_params = $fields;
        
        View_Ticket::setLastAction($view_id,$last_action);
        //====================================	    
	    
	    CerberusBayes::markTicketAsSpam($id);
	    
	    // [TODO] Move buckets (according to config)
	    $fields = array(
	        DAO_Ticket::IS_DELETED => 1,
	        DAO_Ticket::IS_CLOSED => CerberusTicketStatus::CLOSED
	    );
	    DAO_Ticket::update($id, $fields);
	    
	    $tpl = DevblocksPlatform::getTemplateService();

	    $visit = CerberusApplication::getVisit();
		$view = C4_AbstractViewLoader::getView($view_id);
		$tpl->assign('view', $view);
		
		if(!empty($last_action) && !is_null($last_action->ticket_ids)) {
			$tpl->assign('last_action_count', count($last_action->ticket_ids));
		}
		
		$tpl->assign('last_action', $last_action);
		$tpl->display('devblocks:cerberusweb.core::tickets/rpc/ticket_view_output.tpl');
	} 
	
	// Post	
	function doQuickSearchAction() {
        @$type = DevblocksPlatform::importGPC($_POST['type'],'string'); 
        @$query = DevblocksPlatform::importGPC($_POST['query'],'string');

        $query = trim($query);
        
        $visit = CerberusApplication::getVisit(); /* @var $visit CerberusVisit */
		$active_worker = CerberusApplication::getActiveWorker();
		$searchView = C4_AbstractViewLoader::getView(CerberusApplication::VIEW_SEARCH);
		
		if(null == $searchView)
			$searchView = View_Ticket::createSearchView();

        $visit->set('quick_search_type', $type);
        
        $params = array();
        
        switch($type) {
            case "mask":
            	if(is_numeric($query)) {
            		$params[SearchFields_Ticket::TICKET_ID] = new DevblocksSearchCriteria(SearchFields_Ticket::TICKET_ID,DevblocksSearchCriteria::OPER_EQ,intval($query));
            	} else {
			        if($query && false===strpos($query,'*'))
			            $query .= '*';
            		$params[SearchFields_Ticket::TICKET_MASK] = new DevblocksSearchCriteria(SearchFields_Ticket::TICKET_MASK,DevblocksSearchCriteria::OPER_LIKE,strtoupper($query));
            	}
                break;
                
            case "sender":
		        if($query && false===strpos($query,'*'))
		            $query .= '*';
                $params[SearchFields_Ticket::TICKET_FIRST_WROTE] = new DevblocksSearchCriteria(SearchFields_Ticket::TICKET_FIRST_WROTE,DevblocksSearchCriteria::OPER_LIKE,strtolower($query));               
                break;
                
            case "requester":
		        if($query && false===strpos($query,'*'))
		            $query .= '*';
                $params[SearchFields_Ticket::REQUESTER_ADDRESS] = new DevblocksSearchCriteria(SearchFields_Ticket::REQUESTER_ADDRESS,DevblocksSearchCriteria::OPER_LIKE,strtolower($query));               
                break;
                
            case "subject":
		        if($query && false===strpos($query,'*'))
		            $query .= '*';
            	$params[SearchFields_Ticket::TICKET_SUBJECT] = new DevblocksSearchCriteria(SearchFields_Ticket::TICKET_SUBJECT,DevblocksSearchCriteria::OPER_LIKE,$query);               
                break;
                
            case "org":
		        if($query && false===strpos($query,'*'))
		            $query .= '*';
            	$params[SearchFields_Ticket::ORG_NAME] = new DevblocksSearchCriteria(SearchFields_Ticket::ORG_NAME,DevblocksSearchCriteria::OPER_LIKE,$query);               
                break;
                
            case "comments_all":
            	$params[SearchFields_Ticket::FULLTEXT_COMMENT_CONTENT] = new DevblocksSearchCriteria(SearchFields_Ticket::FULLTEXT_COMMENT_CONTENT,DevblocksSearchCriteria::OPER_FULLTEXT,array($query,'all'));               
                break;
                
            case "comments_expert":
            	$params[SearchFields_Ticket::FULLTEXT_COMMENT_CONTENT] = new DevblocksSearchCriteria(SearchFields_Ticket::FULLTEXT_COMMENT_CONTENT,DevblocksSearchCriteria::OPER_FULLTEXT,array($query,'expert'));               
                break;
                
            case "comments_phrase":
            	$params[SearchFields_Ticket::FULLTEXT_COMMENT_CONTENT] = new DevblocksSearchCriteria(SearchFields_Ticket::FULLTEXT_COMMENT_CONTENT,DevblocksSearchCriteria::OPER_FULLTEXT,array($query,'phrase'));               
                break;
                
            case "messages_expert":
            	$params[SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT] = new DevblocksSearchCriteria(SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT,DevblocksSearchCriteria::OPER_FULLTEXT,array($query,'expert'));               
                break;
                
            case "messages_all":
            	$params[SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT] = new DevblocksSearchCriteria(SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT,DevblocksSearchCriteria::OPER_FULLTEXT,array($query,'all'));               
                break;
                
            case "messages_phrase":
            	$params[SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT] = new DevblocksSearchCriteria(SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT,DevblocksSearchCriteria::OPER_FULLTEXT,array($query,'phrase'));               
                break;
                
        }
        
		// Force group ACL
		if(!$active_worker->is_superuser)
        	$params[SearchFields_Ticket::TICKET_GROUP_ID] = new DevblocksSearchCriteria(SearchFields_Ticket::TICKET_GROUP_ID, 'in', array_keys($active_worker->getMemberships()));
        
        $searchView->addParams($params, true);
        $searchView->renderPage = 0;
        
        C4_AbstractViewLoader::setView($searchView->id,$searchView);
        
        DevblocksPlatform::redirect(new DevblocksHttpResponse(array('tickets','search')));
	}

	// [TODO] Refactor for group-based signatures
	function getLogTicketSignatureAction() {
		@$email = DevblocksPlatform::importGPC($_REQUEST['email'],'string','');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$replyto_default = DAO_AddressOutgoing::getDefault();
		
		if(false !== ($address = DAO_Address::lookupAddress($email, false)))
			$replyto = DAO_AddressOutgoing::get($address->id);
			
		if(!empty($replyto->reply_signature)) {
			$sig = $replyto->reply_signature;
		} else {
			$sig = $replyto_default->reply_signature;
		}
		
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, $active_worker, $token_labels, $token_values);
		echo $tpl_builder->build($sig, $token_values);
	}
	
	// Ajax
	function showPreviewAction() {
	    @$tid = DevblocksPlatform::importGPC($_REQUEST['tid'],'integer',0);
	    @$msgid = DevblocksPlatform::importGPC($_REQUEST['msgid'],'integer',0);
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
	    @$edit_mode = DevblocksPlatform::importGPC($_REQUEST['edit'],'integer',0);
	    
		$tpl = DevblocksPlatform::getTemplateService();

		$tpl->assign('view_id', $view_id);
		$tpl->assign('edit_mode', $edit_mode);

		$messages = array();
		
		if(null != ($ticket = DAO_Ticket::get($tid))) {
			/* @var $ticket Model_Ticket */
		    $tpl->assign('ticket', $ticket);
		    
			$messages = $ticket->getMessages();
		}
		
		// Do we have a specific message to look at?
		if(!empty($msgid) && null != (@$message = $messages[$msgid])) {
			 // Good
		} else {
			$message = null;
			$msgid = null;
			
			if(is_array($messages)) {
				if(null != ($message = end($messages)))
					$msgid = $message->id;
			}
		}

		if(!empty($message)) {
			$tpl->assign('message', $message);
			$tpl->assign('content', $message->getContent());
		}
		
		// Paging
		$message_ids = array_keys($messages);
		$tpl->assign('p_count', count($message_ids));
		if(false !== ($pos = array_search($msgid, $message_ids))) {
			$tpl->assign('p', $pos);
			// Prev
			if($pos > 0)
				$tpl->assign('p_prev', $message_ids[$pos-1]);
			// Next
			if($pos+1 < count($message_ids))
				$tpl->assign('p_next', $message_ids[$pos+1]);
		}
		
		// Props
		$workers = DAO_Worker::getAllActive();
		$tpl->assign('workers', $workers);
		
		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		
		$group_buckets = DAO_Bucket::getGroups();
		$tpl->assign('group_buckets', $group_buckets);
	    
		// Watchers
		$object_watchers = DAO_ContextLink::getContextLinks(CerberusContexts::CONTEXT_TICKET, array($ticket->id), CerberusContexts::CONTEXT_WORKER);
		$tpl->assign('object_watchers', $object_watchers);
		
		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_TICKET, $ticket->group_id);
		$tpl->assign('custom_fields', $custom_fields);
		
		$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_TICKET, $ticket->id);
		if(isset($custom_field_values[$ticket->id]))
			$tpl->assign('custom_field_values', $custom_field_values[$ticket->id]);
		
		// Comments
		$comments = DAO_Comment::getByContext(CerberusContexts::CONTEXT_TICKET, $ticket->id);
		$last_comment = array_shift($comments);
		unset($comments);
		$tpl->assign('last_comment', $last_comment);
			
		// Display
		$tpl->display('devblocks:cerberusweb.core::tickets/rpc/peek.tpl');
	}
	
	// Ajax
	function savePreviewAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$subject = DevblocksPlatform::importGPC($_REQUEST['subject'],'string','');
		@$org_id = DevblocksPlatform::importGPC($_REQUEST['org_id'],'integer',0);
		@$org_name = DevblocksPlatform::importGPC($_REQUEST['org_name'],'string','');
		@$closed = DevblocksPlatform::importGPC($_REQUEST['closed'],'integer',0);
		@$owner_id = DevblocksPlatform::importGPC($_REQUEST['owner_id'],'integer',0);
		@$bucket = DevblocksPlatform::importGPC($_REQUEST['bucket_id'],'string','');
		@$spam_training = DevblocksPlatform::importGPC($_REQUEST['spam_training'],'string','');
		@$ticket_reopen = DevblocksPlatform::importGPC(@$_REQUEST['ticket_reopen'],'string','');
		@$comment = DevblocksPlatform::importGPC(@$_REQUEST['comment'],'string','');

		$active_worker = CerberusApplication::getActiveWorker();
		
		$fields = array(
			DAO_Ticket::SUBJECT => $subject,
			DAO_Ticket::OWNER_ID => $owner_id,
		);
		
		// Status
		if(isset($closed)) {
			switch($closed) {
				case 0: // open
					$fields[DAO_Ticket::IS_WAITING] = 0;
					$fields[DAO_Ticket::IS_CLOSED] = 0;
					$fields[DAO_Ticket::IS_DELETED] = 0;
					$fields[DAO_Ticket::DUE_DATE] = 0;
					break;
				case 1: // closed
					$fields[DAO_Ticket::IS_WAITING] = 0;
					$fields[DAO_Ticket::IS_CLOSED] = 1;
					$fields[DAO_Ticket::IS_DELETED] = 0;
					break;
				case 2: // waiting
					$fields[DAO_Ticket::IS_WAITING] = 1;
					$fields[DAO_Ticket::IS_CLOSED] = 0;
					$fields[DAO_Ticket::IS_DELETED] = 0;
					break;
				case 3: // deleted
					$fields[DAO_Ticket::IS_WAITING] = 0;
					$fields[DAO_Ticket::IS_CLOSED] = 1;
					$fields[DAO_Ticket::IS_DELETED] = 1;
					$fields[DAO_Ticket::DUE_DATE] = 0;
					break;
			}
			
			if(1==$closed || 2==$closed) {
				if(!empty($ticket_reopen) && false !== ($due = strtotime($ticket_reopen))) {
					$fields[DAO_Ticket::DUE_DATE] = $due;
				} else {
					$fields[DAO_Ticket::DUE_DATE] = 0;
				}
			}
		}
		
		// Group/Bucket
		if(!empty($bucket)) {
			list($group_id, $bucket_id) = CerberusApplication::translateGroupBucketCode($bucket);

			if(!empty($group_id)) {
			    $fields[DAO_Ticket::GROUP_ID] = $group_id;
			    $fields[DAO_Ticket::BUCKET_ID] = $bucket_id;
			}
		}
		
		// Org
		if(!empty($org_id) && empty($org_name)) {
			$fields[DAO_Ticket::ORG_ID] = 0;
		} elseif(!empty($org_name)) {
			if(null !== ($org_lookup_id = DAO_ContactOrg::lookup($org_name, true))) {
				$fields[DAO_Ticket::ORG_ID] = $org_lookup_id;
			}
		}
		
		// Spam Training
		if(!empty($spam_training)) {
			if('S'==$spam_training)
				CerberusBayes::markTicketAsSpam($id);
			elseif('N'==$spam_training)
				CerberusBayes::markTicketAsNotSpam($id);
		}
		
		DAO_Ticket::update($id, $fields);
		
		// Custom field saves
		@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', array());
		DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_TICKET, $id, $field_ids);

		// Comments
		if(!empty($comment)) {
			@$also_notify_worker_ids = DevblocksPlatform::importGPC($_REQUEST['notify_worker_ids'],'array',array());
			
			$fields = array(
				DAO_Comment::CREATED => time(),
				DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_TICKET,
				DAO_Comment::CONTEXT_ID => $id,
				DAO_Comment::COMMENT => $comment,
				DAO_Comment::ADDRESS_ID => $active_worker->getAddress()->id,
			);
			$comment_id = DAO_Comment::create($fields, $also_notify_worker_ids);
		}		
		exit;
	}
		
	function showViewAutoAssistAction() {
        @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
        @$mode = DevblocksPlatform::importGPC($_REQUEST['mode'],'string','senders');
        @$mode_param = DevblocksPlatform::importGPC($_REQUEST['mode_param'],'string','');

		$tpl = DevblocksPlatform::getTemplateService();
        
        $visit = CerberusApplication::getVisit(); /* @var $visit CerberusVisit */

        $view = C4_AbstractViewLoader::getView($view_id);
        
        $tpl->assign('view_id', $view_id);
        $tpl->assign('mode', $mode);

        if($mode == "headers" && empty($mode_param)) {
	        $tpl->display('devblocks:cerberusweb.core::tickets/rpc/ticket_view_assist_headers.tpl');
	        
        } else {
			$groups = DAO_Group::getAll();
			$tpl->assign('groups', $groups);
			
			$group_buckets = DAO_Bucket::getGroups();
			$tpl->assign('group_buckets', $group_buckets);
			
			$workers = DAO_Worker::getAllActive();
			$tpl->assign('workers', $workers);
			
			// Enforce group memberships
			$active_worker = CerberusApplication::getActiveWorker();
			$memberships = $active_worker->getMemberships();
			
			$params = $view->getParams();
			$params[] = new DevblocksSearchCriteria(SearchFields_Ticket::TICKET_GROUP_ID, 'in', array_keys($memberships)); 
			
	        // [JAS]: Calculate statistics about the current view (top unique senders/subjects/domains)
	        
		    $biggest = DAO_Ticket::analyze($params, 15, $mode, $mode_param);
		    $tpl->assign('biggest', $biggest);
	        
	        $tpl->display('devblocks:cerberusweb.core::tickets/rpc/ticket_view_assist.tpl');
        }
	}
	
	function viewAutoAssistAction() {
	    @$view_id = DevblocksPlatform::importGPC($_POST['view_id'],'string');

        $visit = CerberusApplication::getVisit(); /* @var $visit CerberusVisit */
		$view = C4_AbstractViewLoader::getView($view_id);

		$buckets = DAO_Bucket::getAll();
		
	    @$piles_always = DevblocksPlatform::importGPC($_POST['piles_always'],'array', array());
	    @$piles_hash = DevblocksPlatform::importGPC($_POST['piles_hash'],'array', array());
	    @$piles_moveto = DevblocksPlatform::importGPC($_POST['piles_moveto'],'array', array());
	    @$piles_type = DevblocksPlatform::importGPC($_POST['piles_type'],'array', array());
	    @$piles_type_param = DevblocksPlatform::importGPC($_POST['piles_type_param'],'array', array());
	    @$piles_value = DevblocksPlatform::importGPC($_POST['piles_value'],'array', array());
	    
	    $piles_always = array_flip($piles_always); // Flip hash

	    // Enforce worker memberships
		$active_worker = CerberusApplication::getActiveWorker();
		$memberships = $active_worker->getMemberships();
		$view->addParam(new DevblocksSearchCriteria(SearchFields_Ticket::TICKET_GROUP_ID, 'in', array_keys($memberships)), 'tmpMemberships'); 
	    
	    foreach($piles_hash as $idx => $hash) {
	        @$moveto = $piles_moveto[$idx];
	        @$type = $piles_type[$idx];
	        @$type_param = $piles_type_param[$idx];
	        @$val = $piles_value[$idx];
	        
	        /*
	         * [TODO] [JAS]: Somewhere here we should be ignoring these values for a bit
	         * so other options have a chance to bubble up
	         */
	        if(empty($hash) || empty($moveto) || empty($type) || empty($val))
	            continue;
	        
	        switch(strtolower(substr($moveto,0,1))) {
	            // Group/Bucket Move
	            case 't':
	            	$g_id = intval(substr($moveto,1));
	            	$doActions = array(
	            		'move' => array(
	            			'group_id' => $g_id,
	            			'bucket_id' => 0,
	            		)
	            	);
	            	break;
	            	
	            case 'c':
            		$b_id = intval(substr($moveto,1));
            		@$g_id = intval($buckets[$b_id]->group_id);
            		
            		if(!empty($g_id))
	            	$doActions = array(
	            		'move' => array(
	            			'group_id' => $g_id,
	            			'bucket_id' => $b_id,
	            		)
	            	);
	                break;
	                
	            // Action
	            case 'a':
	                switch(strtolower(substr($moveto,1))) {
	                    case 'c': // close
							$doActions = array(
								'status' => array(
									'is_closed' => 1,
									'is_deleted' => 0,
								)
							);
	                    	break;
	                    case 's': // spam
							$doActions = array(
								'status' => array(
									'is_closed' => 1,
									'is_deleted' => 1,
								),
								'spam' => array(
									'is_spam' => 1,
								)
							);
							break;
	                    case 'd': // delete
							$doActions = array(
								'status' => array(
									'is_closed' => 1,
									'is_deleted' => 1,
								)
							);
	                    	break;
	                }
	                break;
	                
				// Owners
	            case 'o':
            		$w_id = intval(substr($moveto,1));
            		
            		if(!empty($w_id))
	            	$doActions = array(
	            		'owner' => array(
	            			'worker_id' => $w_id,
	            		),
	            	);
	                break;
	                
				// Watchers
	            case 'w':
            		$w_id = intval(substr($moveto,1));
            		
            		if(!empty($w_id))
	            	$doActions = array(
	            		'watchers' => array(
	            			'add' => array($w_id),
	            		)
	            	);
	                break;
	                
	            default:
	                $doActions = array();
	                break;
	        }
	        
            $doTypeParam = $type_param;
            
            // Domains, senders are both sender batch actions
	        switch($type) {
	            default:
	            case 'sender':
	                $doType = 'sender';
	                break;
	                
	            case 'subject':
	                $doType = 'subject';
	                break;
	                
	            case 'header':
	                $doType = 'header';
	                break;
	        }

            // Make wildcards
            $doData = array();
            if($type=="domain") {
                $doData = array('*'.$val);
            } else {
                $doData = array($val);
            }
            
            $view->doBulkUpdate($doType, $doTypeParam, $doData, $doActions, array());
	    }

	    $view->renderPage = 0; // Reset the paging since we may have reduced our list size
	    $view->removeParam('tmpMemberships'); // Remove our filter
	    C4_AbstractViewLoader::setView($view_id,$view);
	    	    
        DevblocksPlatform::redirect(new DevblocksHttpResponse(array('tickets')));
	}

	function viewMoveTicketsAction() {
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
	    @$ticket_ids = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'array');
	    @$group_id = DevblocksPlatform::importGPC($_REQUEST['group_id'],'integer',0);
	    @$bucket_id = DevblocksPlatform::importGPC($_REQUEST['bucket_id'],'integer',0);
	    
	    if(empty($ticket_ids)) {
		    $view = C4_AbstractViewLoader::getView($view_id);
		    $view->render();
		    return;
	    }
	    
        $visit = CerberusApplication::getVisit(); /* @var $visit CerberusVisit */

        $fields = array(
            DAO_Ticket::GROUP_ID => $group_id,
            DAO_Ticket::BUCKET_ID => $bucket_id,
        );
	    
        //====================================
	    // Undo functionality
        $orig_tickets = DAO_Ticket::getTickets($ticket_ids);
        
        $last_action = new Model_TicketViewLastAction();
        $last_action->action = Model_TicketViewLastAction::ACTION_MOVE;
        $last_action->action_params = $fields;

        if(is_array($orig_tickets))
        foreach($orig_tickets as $orig_ticket_idx => $orig_ticket) { /* @var $orig_ticket Model_Ticket */
            $last_action->ticket_ids[$orig_ticket_idx] = array(
                DAO_Ticket::GROUP_ID => $orig_ticket->group_id,
                DAO_Ticket::BUCKET_ID => $orig_ticket->bucket_id
            );
            $orig_ticket->group_id = $group_id;
            $orig_ticket->bucket_id = $bucket_id;
            $orig_tickets[$orig_ticket_idx] = $orig_ticket;
        }
        
        View_Ticket::setLastAction($view_id,$last_action);
	    
	    // Make our changes to the entire list of tickets
	    if(!empty($ticket_ids) && !empty($group_id)) {
	        DAO_Ticket::update($ticket_ids, $fields);
	    }
	    
	    $view = C4_AbstractViewLoader::getView($view_id);
	    $view->render();
	    return;
	}

	function viewMergeTicketsAction() {
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
	    @$ticket_ids = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'array');

        View_Ticket::setLastAction($view_id,null);
        //====================================

	    if(!empty($ticket_ids)) {
	    	$oldest_id = DAO_Ticket::merge($ticket_ids);
	    }
	    
	    $view = C4_AbstractViewLoader::getView($view_id);
	    $view->render();
	    return;
	}
	
	function viewCloseTicketsAction() {
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
	    @$ticket_ids = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'array');
	    
        $fields = array(
            DAO_Ticket::IS_CLOSED => CerberusTicketStatus::CLOSED,
        );
	    
        //====================================
	    // Undo functionality
        $last_action = new Model_TicketViewLastAction();
        $last_action->action = Model_TicketViewLastAction::ACTION_CLOSE;

        if(is_array($ticket_ids))
        foreach($ticket_ids as $ticket_id) {
            $last_action->ticket_ids[$ticket_id] = array(
                DAO_Ticket::IS_CLOSED => CerberusTicketStatus::OPEN
            );
        }

        $last_action->action_params = $fields;
        
        View_Ticket::setLastAction($view_id,$last_action);
        //====================================
	    
        DAO_Ticket::update($ticket_ids, $fields);
	    
	    $view = C4_AbstractViewLoader::getView($view_id);
	    $view->render();
	    return;
	}
	
	function viewWaitingTicketsAction() {
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
	    @$ticket_ids = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'array');

        $fields = array(
            DAO_Ticket::IS_WAITING => 1,
        );
	    
        //====================================
	    // Undo functionality
        $last_action = new Model_TicketViewLastAction();
        $last_action->action = Model_TicketViewLastAction::ACTION_WAITING;

        if(is_array($ticket_ids))
        foreach($ticket_ids as $ticket_id) {
            $last_action->ticket_ids[$ticket_id] = array(
                DAO_Ticket::IS_WAITING => 0,
            );
        }

        $last_action->action_params = $fields;
        
        View_Ticket::setLastAction($view_id,$last_action);
        //====================================

        DAO_Ticket::update($ticket_ids, $fields);
	    
	    $view = C4_AbstractViewLoader::getView($view_id);
	    $view->render();
	    return;
	}
	
	function viewNotWaitingTicketsAction() {
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
	    @$ticket_ids = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'array');

        $fields = array(
            DAO_Ticket::IS_WAITING => 0,
        );
	    
        //====================================
	    // Undo functionality
        $last_action = new Model_TicketViewLastAction();
        $last_action->action = Model_TicketViewLastAction::ACTION_NOT_WAITING;

        if(is_array($ticket_ids))
        foreach($ticket_ids as $ticket_id) {
            $last_action->ticket_ids[$ticket_id] = array(
                DAO_Ticket::IS_WAITING => 1,
            );
        }

        $last_action->action_params = $fields;
        
        View_Ticket::setLastAction($view_id,$last_action);
        //====================================

        DAO_Ticket::update($ticket_ids, $fields);
	    
	    $view = C4_AbstractViewLoader::getView($view_id);
	    $view->render();
	    return;
	}
	
	function viewNotSpamTicketsAction() {
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
	    @$ticket_ids = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'array');

        $fields = array(
            DAO_Ticket::IS_CLOSED => 0,
            DAO_Ticket::IS_DELETED => 0,
        );
	    
        //====================================
	    // Undo functionality
        $last_action = new Model_TicketViewLastAction();
        $last_action->action = Model_TicketViewLastAction::ACTION_NOT_SPAM;

        if(is_array($ticket_ids))
        foreach($ticket_ids as $ticket_id) {
//            CerberusBayes::calculateTicketSpamProbability($ticket_id); // [TODO] Ugly (optimize -- use the 'interesting_words' to do a word bayes spam score?
            
            $last_action->ticket_ids[$ticket_id] = array(
                DAO_Ticket::SPAM_TRAINING => CerberusTicketSpamTraining::BLANK,
                DAO_Ticket::SPAM_SCORE => 0.0001, // [TODO] Fix
                DAO_Ticket::IS_CLOSED => 0,
                DAO_Ticket::IS_DELETED => 0
            );
        }

        $last_action->action_params = $fields;
        
        View_Ticket::setLastAction($view_id,$last_action);
        //====================================

        // [TODO] Bayes should really be smart enough to allow training of batches of IDs
	    if(!empty($ticket_ids))
	    foreach($ticket_ids as $id) {
	        CerberusBayes::markTicketAsNotSpam($id);
	    }
	    
        DAO_Ticket::update($ticket_ids, $fields);
	    
	    $view = C4_AbstractViewLoader::getView($view_id);
	    $view->render();
	    return;
	}
	
	function viewSpamTicketsAction() {
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
	    @$ticket_ids = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'array');

        $fields = array(
            DAO_Ticket::IS_CLOSED => 1,
            DAO_Ticket::IS_DELETED => 1,
        );
	    
        //====================================
	    // Undo functionality
        $last_action = new Model_TicketViewLastAction();
        $last_action->action = Model_TicketViewLastAction::ACTION_SPAM;

        if(is_array($ticket_ids))
        foreach($ticket_ids as $ticket_id) {
//            CerberusBayes::calculateTicketSpamProbability($ticket_id); // [TODO] Ugly (optimize -- use the 'interesting_words' to do a word bayes spam score?
            
            $last_action->ticket_ids[$ticket_id] = array(
                DAO_Ticket::SPAM_TRAINING => CerberusTicketSpamTraining::BLANK,
                DAO_Ticket::SPAM_SCORE => 0.5000, // [TODO] Fix
                DAO_Ticket::IS_CLOSED => 0,
                DAO_Ticket::IS_DELETED => 0
            );
        }

        $last_action->action_params = $fields;
        
        View_Ticket::setLastAction($view_id,$last_action);
        //====================================
	    
        // {TODO] Batch
	    if(!empty($ticket_ids))
	    foreach($ticket_ids as $id) {
	        CerberusBayes::markTicketAsSpam($id);
	    }
	    
        DAO_Ticket::update($ticket_ids, $fields);
	    
	    $view = C4_AbstractViewLoader::getView($view_id);
	    $view->render();
	    return;
	}
	
	function viewDeleteTicketsAction() {
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
	    @$ticket_ids = DevblocksPlatform::importGPC($_REQUEST['ticket_id'],'array');

        $fields = array(
            DAO_Ticket::IS_CLOSED => 1,
            DAO_Ticket::IS_DELETED => 1,
        );
	    
        //====================================
	    // Undo functionality
        $last_action = new Model_TicketViewLastAction();
        $last_action->action = Model_TicketViewLastAction::ACTION_DELETE;

        if(is_array($ticket_ids))
        foreach($ticket_ids as $ticket_id) {
            $last_action->ticket_ids[$ticket_id] = array(
                DAO_Ticket::IS_CLOSED => 0,
                DAO_Ticket::IS_DELETED => 0
            );
        }

        $last_action->action_params = $fields;
        
        View_Ticket::setLastAction($view_id,$last_action);
        //====================================
	    
        DAO_Ticket::update($ticket_ids, $fields);
	    
	    $view = C4_AbstractViewLoader::getView($view_id);
	    $view->render();
	    return;
	}
	
	function viewUndoAction() {
	    @$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
	    @$clear = DevblocksPlatform::importGPC($_REQUEST['clear'],'integer',0);
	    $last_action = View_Ticket::getLastAction($view_id);
	    
	    if($clear || empty($last_action)) {
            View_Ticket::setLastAction($view_id,null);
		    $view = C4_AbstractViewLoader::getView($view_id);
		    $view->render();
	        return;
	    }
	    
	    /*
	     * [TODO] This could be optimized by only doing the row-level updates for the 
	     * MOVE action, all the rest can just be a single DAO_Ticket::update($ids, ...)
	     */
	    if(is_array($last_action->ticket_ids) && !empty($last_action->ticket_ids))
	    foreach($last_action->ticket_ids as $ticket_id => $fields) {
	        DAO_Ticket::update($ticket_id, $fields);
	    }
	    
	    $visit = CerberusApplication::getVisit();
	    $visit->set(CerberusVisit::KEY_VIEW_LAST_ACTION,null);
	    
	    $view = C4_AbstractViewLoader::getView($view_id);
	    $view->render();
	    return;
	}

	function showBatchPanelAction() {
		@$ids = DevblocksPlatform::importGPC($_REQUEST['ids']);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id']);

		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);

	    $unique_sender_ids = array();
	    $unique_subjects = array();
	    
	    if(!empty($ids)) {
	        $ticket_ids = DevblocksPlatform::parseCsvString($ids);
	        
	        if(empty($ticket_ids))
	        	break;
	        
	        $tickets = DAO_Ticket::getTickets($ticket_ids);
	        if(is_array($tickets))
		    foreach($tickets as $ticket) { /* @var $ticket Model_Ticket */
	            $ptr =& $unique_sender_ids[$ticket->first_wrote_address_id]; 
		        $ptr = intval($ptr) + 1;
		        $ptr =& $unique_subjects[$ticket->subject];
		        $ptr = intval($ptr) + 1;
		    }
	
		    arsort($unique_subjects); // sort by occurrences
		    
		    $senders = DAO_Address::getWhere(
		    	sprintf("%s IN (%s)",
		    		DAO_Address::ID,
		    		implode(',',array_keys($unique_sender_ids))
		    ));
		    
		    foreach($senders as $sender) {
		        $ptr =& $unique_senders[$sender->email];
		        $ptr = intval($ptr) + 1;
		    }
		    
		    arsort($unique_senders);
		    
		    unset($senders);
		    unset($unique_sender_ids);
		    
	        @$tpl->assign('ticket_ids', $ticket_ids);
	        @$tpl->assign('unique_senders', $unique_senders);
	        @$tpl->assign('unique_subjects', $unique_subjects);
	    }
		
		// Groups
		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		
		// Buckets
		$group_buckets = DAO_Bucket::getGroups(); // [TODO] Cache these
		$tpl->assign('group_buckets', $group_buckets);
		
		$workers = DAO_Worker::getAllActive();
		$tpl->assign('workers', $workers);
		
		// Custom Fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_TICKET);
		$tpl->assign('custom_fields', $custom_fields);
		
		// Macros
		$macros = DAO_TriggerEvent::getByOwner(CerberusContexts::CONTEXT_WORKER, $active_worker->id, 'event.macro.ticket');
		$tpl->assign('macros', $macros);
		
		// Broadcast
		CerberusContexts::getContext(CerberusContexts::CONTEXT_TICKET, null, $token_labels, $token_values);
		
		// Signature
		$translate = DevblocksPlatform::getTranslationService();
		$token_labels['signature'] = mb_convert_case($translate->_('common.signature'), MB_CASE_TITLE);
		asort($token_labels);
		
		$tpl->assign('token_labels', $token_labels);
		
		$tpl->display('devblocks:cerberusweb.core::tickets/rpc/bulk.tpl');
	}
	
	// Ajax
	function doBulkUpdateAction() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		@$ticket_id_str = DevblocksPlatform::importGPC($_REQUEST['ticket_ids'],'string');
	    @$shortcut_name = DevblocksPlatform::importGPC($_REQUEST['shortcut_name'],'string','');

	    @$filter = DevblocksPlatform::importGPC($_REQUEST['filter'],'string','');
	    @$senders = DevblocksPlatform::importGPC($_REQUEST['senders'],'string','');
	    @$subjects = DevblocksPlatform::importGPC($_REQUEST['subjects'],'string','');
	    
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		$view = C4_AbstractViewLoader::getView($view_id);

        $subjects = DevblocksPlatform::parseCrlfString($subjects);
        $senders = DevblocksPlatform::parseCrlfString($senders);
		
		// Scheduled behavior
		@$behavior_id = DevblocksPlatform::importGPC($_POST['behavior_id'],'string','');
		@$behavior_when = DevblocksPlatform::importGPC($_POST['behavior_when'],'string','');
		@$behavior_params = DevblocksPlatform::importGPC($_POST['behavior_params'],'array',array());
        
		$do = array();
		
		// Move to Group/Bucket
		// [TODO] This logic is repeated in several places -- try to condense (like custom field form handlers)
		@$move_code = DevblocksPlatform::importGPC($_REQUEST['do_move'],'string',null);
		if(0 != strlen($move_code)) {
			list($g_id, $b_id) = CerberusApplication::translateGroupBucketCode($move_code);
			$do['move'] = array(
				'group_id' => intval($g_id),
				'bucket_id' => intval($b_id),
			);
		}
		
		// Watchers
		$watcher_params = array();
		
		@$watcher_add_ids = DevblocksPlatform::importGPC($_REQUEST['do_watcher_add_ids'],'array',array());
		if(!empty($watcher_add_ids))
			$watcher_params['add'] = $watcher_add_ids;
			
		@$watcher_remove_ids = DevblocksPlatform::importGPC($_REQUEST['do_watcher_remove_ids'],'array',array());
		if(!empty($watcher_remove_ids))
			$watcher_params['remove'] = $watcher_remove_ids;
		
		if(!empty($watcher_params))
			$do['watchers'] = $watcher_params;
			
		// Spam training
		@$is_spam = DevblocksPlatform::importGPC($_REQUEST['do_spam'],'string',null);
		if(0 != strlen($is_spam)) {
			$do['spam'] = array(
				'is_spam' => (!$is_spam?0:1)
			);
		}
		
		// Owner
		@$owner_id = DevblocksPlatform::importGPC($_REQUEST['do_owner'],'string',null);
		if(0 != strlen($owner_id)) {
			$do['owner'] = array(
				'worker_id' => intval($owner_id),
			);
		}
		
		// Org
		@$org_name = DevblocksPlatform::importGPC($_REQUEST['do_org'],'string', null);
		if(0 != strlen($org_name)) {
			$org_id = DAO_ContactOrg::lookup($org_name, true);

			if(!empty($org_id)) {
				$do['org'] = array(
					'org_id' => $org_id,
					'org_name' => $org_name,
				);
			}
		}
		
		// Set status
		@$status = DevblocksPlatform::importGPC($_REQUEST['do_status'],'string',null);
		if(0 != strlen($status)) {
			$do['status'] = array(
				'is_waiting' => (3==$status?1:0), // explicit waiting
				'is_closed' => ((0==$status||3==$status)?0:1), // not open or waiting
				'is_deleted' => (2==$status?1:0), // explicit deleted
			);
			
			// Waiting until
			$reopen = '';
			switch($status) {
				case 1: // closed
				case 3: // waiting
					@$reopen = DevblocksPlatform::importGPC($_REQUEST['do_reopen'],'string',null);
					break;
			}
			
			$do['reopen'] = array(
				'date' => $reopen,
			);
		}
		
		// Do: Scheduled Behavior
		if(0 != strlen($behavior_id)) {
			$do['behavior'] = array(
				'id' => $behavior_id,
				'when' => $behavior_when,
				'params' => $behavior_params,
			);
		}
		
		// Broadcast: Mass Reply
		if($active_worker->hasPriv('core.ticket.view.actions.broadcast_reply')) {
			@$do_broadcast = DevblocksPlatform::importGPC($_REQUEST['do_broadcast'],'string',null);
			@$broadcast_message = DevblocksPlatform::importGPC($_REQUEST['broadcast_message'],'string',null);
			@$broadcast_is_queued = DevblocksPlatform::importGPC($_REQUEST['broadcast_is_queued'],'integer',0);
			if(0 != strlen($do_broadcast) && !empty($broadcast_message)) {
				$do['broadcast'] = array(
					'message' => $broadcast_message,
					'is_queued' => $broadcast_is_queued,
					'worker_id' => $active_worker->id,
				);
			}
		}
		
	    $data = array();
	    $ids = array();
	    
	    switch($filter) {
	    	case 'sender':
		        $data = $senders;
	    		break;
	    	case 'subject':
		        $data = $subjects;
	    		break;
	    	case 'checks':
		    	$filter = ''; // bulk update just looks for $ids == !null
		        $ids = DevblocksPlatform::parseCsvString($ticket_id_str);
	    		break;
			case 'sample':
				@$sample_size = min(DevblocksPlatform::importGPC($_REQUEST['filter_sample_size'],'integer',0),9999);
				$filter = '';
				$ids = $view->getDataSample($sample_size);
				break;
			default:
				break;
	    }
		
	    // Restrict to current worker groups
		$memberships = $active_worker->getMemberships();
		$view->addParam(new DevblocksSearchCriteria(SearchFields_Ticket::TICKET_GROUP_ID, 'in', array_keys($memberships)), 'tmp'); 
	    
		// Do: Custom fields
		$do = DAO_CustomFieldValue::handleBulkPost($do);
		
		$view->doBulkUpdate($filter, '', $data, $do, $ids);
		
		// Clear our temporary group restriction before re-rendering
		$view->removeParam('tmp');
		
		$view->render();
		return;
	}

	function doBulkUpdateBroadcastTestAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		$view = C4_AbstractViewLoader::getView($view_id);

		$tpl = DevblocksPlatform::getTemplateService();
		
		if($active_worker->hasPriv('core.ticket.view.actions.broadcast_reply')) {
			@$broadcast_message = DevblocksPlatform::importGPC($_REQUEST['broadcast_message'],'string',null);

			@$filter = DevblocksPlatform::importGPC($_REQUEST['filter'],'string','');
			@$ids = DevblocksPlatform::importGPC($_REQUEST['ids'],'string','');
			
			// Filter to checked
			if('checks' == $filter && !empty($ids)) {
				$view->addParam(new DevblocksSearchCriteria(SearchFields_Ticket::ID,'in',explode(',', $ids)));
			}
			
			$results = $view->getDataSample(1);
			
			if(empty($results)) {
				$success = false;
				$output = "There aren't any rows in this view!";
				
			} else {
				// Try to build the template
				CerberusContexts::getContext(CerberusContexts::CONTEXT_TICKET, current($results), $token_labels, $token_values);
				
				// Add the signature to the token_values
				// [TODO] This shouldn't be redundant with ::doBulkUpdateAction()
				if(in_array('signature', $tpl_builder->tokenize($broadcast_message))) {
					if(isset($token_values['group_id']) && null != ($sig_group = DAO_Group::get($token_values['group_id']))) {
						 $sig_template = $sig_group->getReplySignature(@intval($token_values['bucket_id']));
						 CerberusContexts::getContext(CerberusContexts::CONTEXT_WORKER, $active_worker->id, $worker_labels, $worker_values);
						 if(false !== ($out = $tpl_builder->build($sig_template, $worker_values))) {
						 	$token_values['signature'] = $out;
						 }
					}
				}
				
				if(false === ($out = $tpl_builder->build($broadcast_message, $token_values))) {
					// If we failed, show the compile errors
					$errors = $tpl_builder->getErrors();
					$success= false;
					$output = @array_shift($errors);
				} else {
					// If successful, return the parsed template
					$success = true;
					$output = $out;
				}
			}
			
			$tpl->assign('success', $success);
			$tpl->assign('output', $output);
			$tpl->display('devblocks:cerberusweb.core::internal/renderers/test_results.tpl');
		}
	}
	
	// ajax
	function showViewRssAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');
		@$source = DevblocksPlatform::importGPC($_REQUEST['source'],'string','');
		
		$view = C4_AbstractViewLoader::getView($view_id);
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		$tpl->assign('view', $view);
		$tpl->assign('source', $source);
		
		$tpl->display('devblocks:cerberusweb.core::internal/views/view_rss_builder.tpl');
	}
	
	// post
	// [TODO] Move to 'internal'
	function viewBuildRssAction() {
		@$view_id = DevblocksPlatform::importGPC($_POST['view_id']);
		@$source = DevblocksPlatform::importGPC($_POST['source']);
		@$title = DevblocksPlatform::importGPC($_POST['title']);
		$active_worker = CerberusApplication::getActiveWorker();

		$view = C4_AbstractViewLoader::getView($view_id);
		
		$hash = md5($title.$view_id.$active_worker->id.time());
		
	    // Restrict to current worker groups
		$active_worker = CerberusApplication::getActiveWorker();
		
		$params = array(
			'params' => $view->getParams(),
			'sort_by' => $view->renderSortBy,
			'sort_asc' => $view->renderSortAsc
		);
		
		$fields = array(
			DAO_ViewRss::TITLE => $title, 
			DAO_ViewRss::HASH => $hash, 
			DAO_ViewRss::CREATED => time(),
			DAO_ViewRss::WORKER_ID => $active_worker->id,
			DAO_ViewRss::SOURCE_EXTENSION => $source, 
			DAO_ViewRss::PARAMS => serialize($params),
		);
		$feed_id = DAO_ViewRss::create($fields);
				
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('preferences','rss')));
	}
	
	function searchviewAction() {
		$visit = CerberusApplication::getVisit();
	    
	    $response = DevblocksPlatform::getHttpRequest();
	    $path = $response->path;
	    array_shift($path); // tickets
	    array_shift($path); // searchview
	    $id = array_shift($path);

	    $view = C4_AbstractViewLoader::getView($id);
	    $view_params = $view->getParams();

		if(!empty($view_params)) {
		    $params = array();
		    
		    // Index by field name for search system
		    if(is_array($view_params))
		    foreach($view_params as $key => $criteria) { /* @var $criteria DevblocksSearchCriteria */
                $params[$key] = $criteria;
		    }
		}
		
		if(null == ($search_view = C4_AbstractViewLoader::getView(CerberusApplication::VIEW_SEARCH))) {
			$search_view = View_Ticket::createSearchView();
		}
		$search_view->addParams($params, true);
		$search_view->renderPage = 0;
		C4_AbstractViewLoader::setView($search_view->id,$search_view);

		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('tickets','search')));
	}
	
	function getCustomFieldEntryAction() {
		@$group_id = DevblocksPlatform::importGPC($_REQUEST['group_id'],'integer',0);
		
		$tpl = DevblocksPlatform::getTemplateService();
		
		$group_fields = DAO_CustomField::getByContextAndGroupId(CerberusContexts::CONTEXT_TICKET, $group_id);
		$tpl->assign('custom_fields', $group_fields);
		$tpl->assign('bulk', false);
		
		$tpl->display('devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl');
	}
};
