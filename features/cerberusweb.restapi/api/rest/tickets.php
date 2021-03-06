<?php
class ChRest_Tickets extends Extension_RestController implements IExtensionRestController {
	function getAction($stack) {
		@$action = array_shift($stack);
		
		// Looking up a single ID?
		if(empty($stack) && is_numeric($action)) {
			$this->getId(intval($action));
			
		} else {
			switch($action) {
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function putAction($stack) {
		@$action = array_shift($stack);
		
		// Updating a single ticket ID?
		if(is_numeric($action)) {
			$id = intval($action);
			$action = array_shift($stack);
			
			switch($action) {
				case 'requester':
					$this->putRequester(intval($id));
					break;
					
				default:
					$this->putId(intval($id));
					break;
			}
			
		} else { // actions
			switch($action) {
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function postAction($stack) {
		@$action = array_shift($stack);
		
		if(is_numeric($action) && !empty($stack)) {
			$id = intval($action);
			$action = array_shift($stack);
			
			switch($action) {
				case 'comment':
					$this->postComment($id);
					break;
			}
			
		} else {
			switch($action) {
				case 'compose':
					$this->postCompose();
					break;
					
				case 'reply':
					$this->postReply();
					break;
					
				case 'search':
					$this->postSearch();
					break;
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function deleteAction($stack) {
		@$action = array_shift($stack);

		// Delete a single ID?
		if(is_numeric($action)) {
			$id = intval($action);
			$action = array_shift($stack);
			
			switch($action) {
				case 'requester':
					$this->deleteRequester(intval($id));
					break;
				default:
					$this->deleteId(intval($id));
					break;
			}
			
		} else { // actions
			switch($action) {
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function getContext($model) {
		$labels = array();
		$values = array();
		$context = CerberusContexts::getContext(CerberusContexts::CONTEXT_TICKET, $model, $labels, $values, null, true);

		unset($values['initial_message_content']);
		unset($values['latest_message_content']);
		
		return $values;
	}
	
	private function getId($id) {
		$worker = CerberusApplication::getActiveWorker();
		
		// Internally search (checks ACL via groups)
		
		$container = $this->search(array(
			array('id', '=', $id),
		));
		
		if(is_array($container) && isset($container['results']) && isset($container['results'][$id]))
			$this->success($container['results'][$id]);

		// Error
		$this->error(self::ERRNO_CUSTOM, sprintf("Invalid ticket id '%d'", $id));
	}
	
	private function putId($id) {
		$worker = CerberusApplication::getActiveWorker();
		$workers = DAO_Worker::getAll();
		
		@$subject = DevblocksPlatform::importGPC($_REQUEST['subject'],'string','');
		@$is_waiting = DevblocksPlatform::importGPC($_REQUEST['is_waiting'],'string','');
		@$is_closed = DevblocksPlatform::importGPC($_REQUEST['is_closed'],'string','');
		@$is_deleted = DevblocksPlatform::importGPC($_REQUEST['is_deleted'],'string','');
		
		if(null == ($ticket = DAO_Ticket::get($id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("Invalid ticket ID %d", $id));
			
		// Check group memberships
		$memberships = $worker->getMemberships();
		if(!$worker->is_superuser && !isset($memberships[$ticket->group_id]))
			$this->error(self::ERRNO_ACL, 'Access denied to modify tickets in this group.');
		
		$fields = array(
//			DAO_Ticket::UPDATED_DATE => time(),
		);
		
		$custom_fields = array(); // [TODO]
		
		if(0 != strlen($subject))
			$fields[DAO_Ticket::SUBJECT] = $subject;
			
		if(0 != strlen($is_waiting))
			$fields[DAO_Ticket::IS_WAITING] = !empty($is_waiting) ? 1 : 0;
		
		// Close
		if(0 != strlen($is_closed)) {
			// ACL
			if(!$worker->hasPriv('core.ticket.actions.close'))
				$this->error(self::ERRNO_ACL, 'Access denied to close tickets.');
			
			$fields[DAO_Ticket::IS_CLOSED] = !empty($is_closed) ? 1 : 0;
		}
			
		// Delete
		if(0 != strlen($is_deleted)) {
			// ACL
			if(!$worker->hasPriv('core.ticket.actions.delete'))
				$this->error(self::ERRNO_ACL, 'Access denied to delete tickets.');

			$fields[DAO_Ticket::IS_DELETED] = !empty($is_deleted) ? 1 : 0;
		}
			
		// Only update fields that changed
		$fields = Cerb_ORMHelper::uniqueFields($fields, $ticket);
		
		// Update
		if(!empty($fields))
			DAO_Ticket::update($id, $fields);
			
		// Handle custom fields
		$customfields = $this->_handleCustomFields($_POST);
		if(is_array($customfields))
			DAO_CustomFieldValue::formatAndSetFieldValues(CerberusContexts::CONTEXT_TICKET, $id, $customfields, true, true, true);

		$this->getId($id);
	}
	
	public function putRequester($id) {
		$worker = CerberusApplication::getActiveWorker();
		
		if(null == ($ticket = DAO_Ticket::get($id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("Invalid ticket ID %d", $id));
			
		// Check group memberships
		$memberships = $worker->getMemberships();
		if(!$worker->is_superuser && !isset($memberships[$ticket->group_id]))
			$this->error(self::ERRNO_ACL, 'Access denied to modify tickets in this group.');

		@$email = DevblocksPlatform::importGPC($_REQUEST['email'],'string','');
			
		if(!empty($email))
			DAO_Ticket::createRequester($email, $id);
		
		$this->getId($id);
	}
	
	private function deleteId($id) {
		$worker = CerberusApplication::getActiveWorker();
		
		// ACL
		if(!$worker->hasPriv('core.ticket.actions.delete'))
			$this->error(self::ERRNO_ACL, 'Access denied to delete tickets.');

		if(null == ($ticket = DAO_Ticket::get($id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("Invalid ticket ID %d", $id));
			
		// Check group memberships
		$memberships = $worker->getMemberships();
		
		if(!$worker->is_superuser && !isset($memberships[$ticket->group_id]))
			$this->error(self::ERRNO_ACL, 'Access denied to delete tickets in this group.');

		$fields = array(
			DAO_Ticket::IS_CLOSED => 1,
			DAO_Ticket::IS_DELETED => 1,
			DAO_Ticket::IS_WAITING => 0,
		);
		
		// Only update fields that changed
		$fields = Cerb_ORMHelper::uniqueFields($fields, $ticket);
		
		if(!empty($fields))
			DAO_Ticket::update($ticket->id, $fields);
		
		$result = array('id'=> $id);
		
		$this->success($result);
	}
	
	public function deleteRequester($id) {
		$worker = CerberusApplication::getActiveWorker();
		
		if(null == ($ticket = DAO_Ticket::get($id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("Invalid ticket ID %d", $id));
			
		// Check group memberships
		$memberships = $worker->getMemberships();
		if(!$worker->is_superuser && !isset($memberships[$ticket->group_id]))
			$this->error(self::ERRNO_ACL, 'Access denied to modify tickets in this group.');

		@$email = DevblocksPlatform::importGPC($_REQUEST['email'],'string','');
		
		if(!empty($email)) {
			if(null != ($email = DAO_Address::lookupAddress($email, false))) {
				DAO_Ticket::deleteRequester($id, $email->id);
			} else {
				$this->error(self::ERRNO_CUSTOM, $email . ' is not a valid requester on ticket ' . $id);
			}
		}
		
		$this->getId($id);
	}
	
	function translateToken($token, $type='dao') {
		$tokens = array();
		
		if('dao'==$type) {
			$tokens = array(
				'id' => DAO_Ticket::ID,
				'is_closed' => DAO_Ticket::IS_CLOSED,
				'is_deleted' => DAO_Ticket::IS_DELETED,
				'is_waiting' => DAO_Ticket::IS_WAITING,
				'mask' => DAO_Ticket::MASK,
				'subject' => DAO_Ticket::SUBJECT,
			);
			
		} elseif ('subtotal'==$type) {
			$tokens = array(
				'fieldsets' => SearchFields_Ticket::VIRTUAL_HAS_FIELDSET,
				'links' => SearchFields_Ticket::VIRTUAL_CONTEXT_LINK,
				'watchers' => SearchFields_Ticket::VIRTUAL_WATCHERS,
				
				'first_wrote' => SearchFields_Ticket::TICKET_FIRST_WROTE,
				'group' => SearchFields_Ticket::TICKET_GROUP_ID,
				'last_action' => SearchFields_Ticket::TICKET_LAST_ACTION_CODE,
				'last_wrote' => SearchFields_Ticket::TICKET_LAST_WROTE,
				'org_name' => SearchFields_Ticket::ORG_NAME,
				'owner' => SearchFields_Ticket::TICKET_OWNER_ID,
				'spam_training' => SearchFields_Ticket::TICKET_SPAM_TRAINING,
				'status' => SearchFields_Ticket::VIRTUAL_STATUS,
				'subject' => SearchFields_Ticket::TICKET_SUBJECT,
			);
			
			$tokens_cfields = $this->_handleSearchTokensCustomFields(CerberusContexts::CONTEXT_TICKET);
			
			if(is_array($tokens_cfields))
				$tokens = array_merge($tokens, $tokens_cfields);
			
		} else {
			$tokens = array(
				'content' => SearchFields_Ticket::FULLTEXT_MESSAGE_CONTENT,
				'created' => SearchFields_Ticket::TICKET_CREATED_DATE,
				'first_wrote' => SearchFields_Ticket::TICKET_FIRST_WROTE,
				'id' => SearchFields_Ticket::TICKET_ID,
				'is_closed' => SearchFields_Ticket::TICKET_CLOSED,
				'is_deleted' => SearchFields_Ticket::TICKET_DELETED,
				'is_waiting' => SearchFields_Ticket::TICKET_WAITING,
				'last_wrote' => SearchFields_Ticket::TICKET_LAST_WROTE,
				'mask' => SearchFields_Ticket::TICKET_MASK,
				'requester' => SearchFields_Ticket::REQUESTER_ADDRESS,
				'subject' => SearchFields_Ticket::TICKET_SUBJECT,
				'updated' => SearchFields_Ticket::TICKET_UPDATED_DATE,
				'group' => SearchFields_Ticket::TICKET_GROUP_ID,
				'group_id' => SearchFields_Ticket::TICKET_GROUP_ID,
				'bucket_id' => SearchFields_Ticket::TICKET_BUCKET_ID,
				'org_id' => SearchFields_Ticket::TICKET_ORG_ID,
				'org_name' => SearchFields_Ticket::ORG_NAME,
					
				'links' => SearchFields_Ticket::VIRTUAL_CONTEXT_LINK,
			);
		}
		
		if(isset($tokens[$token]))
			return $tokens[$token];
		
		return NULL;
	}
	
	function search($filters=array(), $sortToken='updated', $sortAsc=0, $page=1, $limit=10, $options=array()) {
		@$show_results = DevblocksPlatform::importVar($options['show_results'], 'boolean', true);
		@$subtotals = DevblocksPlatform::importVar($options['subtotals'], 'array', array());
		
		$worker = CerberusApplication::getActiveWorker();

		$custom_field_params = $this->_handleSearchBuildParamsCustomFields($filters, CerberusContexts::CONTEXT_TICKET);
		$params = $this->_handleSearchBuildParams($filters);
		$params = array_merge($params, $custom_field_params);
		
		// (ACL) Add worker group privs
		if(!$worker->is_superuser) {
			$memberships = $worker->getMemberships();
			$params['tmp_worker_memberships'] = new DevblocksSearchCriteria(
				SearchFields_Ticket::TICKET_GROUP_ID,
				'in',
				(!empty($memberships) ? array_keys($memberships) : array(0))
			);
		}
		
		// Sort
		$sortBy = $this->translateToken($sortToken, 'search');
		$sortAsc = !empty($sortAsc) ? true : false;

		// Search
		
		$view = $this->_getSearchView(
			CerberusContexts::CONTEXT_TICKET,
			$params,
			$limit,
			$page,
			$sortBy,
			$sortAsc
		);
		
		if($show_results)
			list($results, $total) = $view->getData();
		
		// Get subtotal data, if provided
		if(!empty($subtotals))
			$subtotal_data = $this->_handleSearchSubtotals($view, $subtotals);
		
		if($show_results) {
			$objects = array();
			
			$models = CerberusContexts::getModels(CerberusContexts::CONTEXT_TICKET, array_keys($results));
			
			unset($results);
			
			if(is_array($models))
			foreach($models as $id => $model) {
				$values = $this->getContext($model);
				$objects[$id] = $values;
			}
		}
		
		$container = array();
		
		if($show_results) {
			$container['results'] = $objects;
			$container['total'] = $total;
			$container['count'] = count($objects);
			$container['page'] = $page;
		}
		
		if(!empty($subtotals)) {
			$container['subtotals'] = $subtotal_data;
		}
		
		return $container;
	}
	
	private function postSearch() {
		$worker = CerberusApplication::getActiveWorker();

		// ACL
// 		if(!$worker->hasPriv('core.mail.search'))
// 			$this->error(self::ERRNO_ACL, 'Access denied to search tickets.');

		$container = $this->_handlePostSearch();
		
		$this->success($container);
	}
	
	private function _handlePostCompose() {
		$worker = CerberusApplication::getActiveWorker();
		
		@$group_id = DevblocksPlatform::importGPC($_REQUEST['group_id'],'integer',0);
		@$bucket_id = DevblocksPlatform::importGPC($_REQUEST['bucket_id'],'integer',0);
		@$org_id = DevblocksPlatform::importGPC($_REQUEST['org_id'],'integer',0);
		@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','');
		@$cc = DevblocksPlatform::importGPC($_REQUEST['cc'],'string','');
		@$bcc = DevblocksPlatform::importGPC($_REQUEST['bcc'],'string','');
		@$subject = DevblocksPlatform::importGPC($_REQUEST['subject'],'string','');
		@$content = DevblocksPlatform::importGPC($_REQUEST['content'],'string','');
		
		@$file_ids = DevblocksPlatform::importGPC($_REQUEST['file_id'],'array',array());
		
		@$status = DevblocksPlatform::importGPC($_REQUEST['status'],'integer',0);
		@$reopen_at = DevblocksPlatform::importGPC($_REQUEST['reopen_at'],'integer',0);
		
		$properties = array();
		
		if(empty($group_id))
			$this->error(self::ERRNO_CUSTOM, "The 'group_id' parameter is required");
		
		if(empty($to))
			$this->error(self::ERRNO_CUSTOM, "The 'to' parameter is required");
		
		if(empty($subject))
			$this->error(self::ERRNO_CUSTOM, "The 'subject' parameter is required");
		
		if(empty($content))
			$this->error(self::ERRNO_CUSTOM, "The 'content' parameter is required");

		if(!empty($file_ids))
			$file_ids = DevblocksPlatform::sanitizeArray($file_ids, 'integer', array('nonzero','unique'));
		
		$properties = array(
			'group_id' => $group_id,
			'bucket_id' => $bucket_id,
			'org_id' => $org_id,
			'to' => $to,
			'subject' => $subject,
			'content' => $content,
			'worker_id' => $worker->id,
		);

		if(!empty($cc))
			$properties['cc'] = $cc;
		
		if(!empty($bcc))
			$properties['bcc'] = $bcc;
		
		if(!empty($status) && in_array($status, array(0,1,2)))
			$properties['closed'] = $status;
		
		if(!empty($reopen_at))
			$properties['reopen_at'] = $reopen_at;
		
		if(!empty($file_ids)) {
			$properties['link_forward_files'] = true;
			$properties['forward_files'] = $file_ids;
		}
		
		if(false == ($ticket_id = CerberusMail::compose($properties)))
			$this->error(self::ERRNO_CUSTOM, "Failed to create a new message.");
		
		// Handle custom fields
		$custom_fields = $this->_handleCustomFields($_POST);
		
		if(is_array($custom_fields))
			DAO_CustomFieldValue::formatAndSetFieldValues(CerberusContexts::CONTEXT_TICKET, $ticket_id, $custom_fields, true, true, true);
		
		return $ticket_id;
	}
	
	private function postCompose() {
		$worker = CerberusApplication::getActiveWorker();

		// ACL
		if(!$worker->hasPriv('core.mail.send'))
			$this->error(self::ERRNO_ACL, 'Access denied to compose mail.');
		
		$ticket_id = $this->_handlePostCompose();
		$this->getId($ticket_id);
	}
	
	private function _handlePostReply() {
		$worker = CerberusApplication::getActiveWorker();
		
		/*
		'html_template_id'
		'headers'
		*/
		
		// Required
		@$message_id = DevblocksPlatform::importGPC($_REQUEST['message_id'],'integer',0);
		@$content = DevblocksPlatform::importGPC($_REQUEST['content'],'string','');

		// Optional
		@$bcc = DevblocksPlatform::importGPC($_REQUEST['bcc'],'string','');
		@$bucket_id = DevblocksPlatform::importGPC($_REQUEST['bucket_id'],'string',null);
		@$cc = DevblocksPlatform::importGPC($_REQUEST['cc'],'string','');
		@$content_format = DevblocksPlatform::importGPC($_REQUEST['content_format'],'string','');
		@$dont_keep_copy = DevblocksPlatform::importGPC($_REQUEST['dont_keep_copy'],'integer',0);
		@$dont_send = DevblocksPlatform::importGPC($_REQUEST['dont_send'],'integer',0);
		@$file_ids = DevblocksPlatform::importGPC($_REQUEST['file_id'],'array',array());
		@$group_id = DevblocksPlatform::importGPC($_REQUEST['group_id'],'integer',0);
		@$is_autoreply = DevblocksPlatform::importGPC($_REQUEST['is_autoreply'],'integer',0);
		@$is_broadcast = DevblocksPlatform::importGPC($_REQUEST['is_broadcast'],'integer',0);
		@$is_forward = DevblocksPlatform::importGPC($_REQUEST['is_forward'],'integer',0);
		@$owner_id = DevblocksPlatform::importGPC($_REQUEST['owner_id'],'string',null);
		@$reopen_at = DevblocksPlatform::importGPC($_REQUEST['reopen_at'],'integer',0);
		@$status = DevblocksPlatform::importGPC($_REQUEST['status'],'integer',0);
		@$subject = DevblocksPlatform::importGPC($_REQUEST['subject'],'string','');
		@$to = DevblocksPlatform::importGPC($_REQUEST['to'],'string','');
		@$worker_id = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'integer',0);
		
		$properties = array();

		if(empty($content))
			$this->error(self::ERRNO_CUSTOM, "The 'content' parameter is required");
		
		if(empty($message_id))
			$this->error(self::ERRNO_CUSTOM, "The 'message_id' parameter is required");
		
		if(false == ($message = DAO_Message::get($message_id)))
			$this->error(self::ERRNO_CUSTOM, "The given 'message_id' is invalid");

		if(null == ($context_ext = Extension_DevblocksContext::get(CerberusContexts::CONTEXT_TICKET)))
			$this->error(self::ERRNO_CUSTOM, "The ticket context could not be loaded");
		
		if(false === $context_ext->authorize($message->id, $worker))
			$this->error(self::ERRNO_CUSTOM, "You do not have write access to this ticket");
		
		if(!empty($file_ids))
			$file_ids = DevblocksPlatform::sanitizeArray($file_ids, 'integer', array('nonzero','unique'));
		
		$properties = array(
			'message_id' => $message_id,
			'content' => $content,
		);

		// [TODO] Tell the activity log we're impersonating?
		if($worker->is_superuser && !empty($worker_id)) {
			if(false != ($sender_worker = DAO_Worker::get($worker_id)))
				$properties['worker_id'] = $sender_worker->id;
		}
		
		// Default to current worker
		if(!isset($properties['worker_id']))
			$properties['worker_id'] = $worker->id;
		
		// Bucket
		if(strlen($bucket_id) > 0 && (empty($bucket_id) || false != ($bucket = DAO_Bucket::get($bucket_id)))) {
			$properties['bucket_id'] = $bucket_id;
			
			// Always set the group_id in unison with the bucket_id
			if(isset($bucket))
				$properties['group_id'] = $bucket->group_id;
		}
		
		// Group
		if(!isset($properties['group_id']) && !empty($group_id) && false != ($group = DAO_Group::get($group_id))) {
			$properties['group_id'] = $group->id;
			$properties['bucket_id'] = 0;
		}

		// Owner
		if(strlen($owner_id) > 0 && (empty($owner_id) || false != ($owner = DAO_Worker::get($owner_id)))) {
			if(isset($owner))
				$properties['owner_id'] = $owner->id;
			else
				$properties['owner_id'] = 0;
		}
		
		if(!empty($subject))
			$properties['subject'] = $subject;
		
		if(!empty($to))
			$properties['to'] = $to;
		
		if(!empty($cc))
			$properties['cc'] = $cc;
		
		if(!empty($bcc))
			$properties['bcc'] = $bcc;
		
		if(!empty($content_format) && in_array($content_format, array('markdown','parsedown','html')))
			$properties['content_format'] = $content_format;
		
		if(!empty($status) && in_array($status, array(0,1,2)))
			$properties['closed'] = $status;
		
		if(!empty($reopen_at))
			$properties['reopen_at'] = $reopen_at;
		
		//Files
		
		if(!empty($file_ids)) {
			$properties['link_forward_files'] = true;
			$properties['forward_files'] = $file_ids;
		}

		// Flags
		
		if(!empty($dont_keep_copy))
			$properties['dont_keep_copy'] = $dont_keep_copy ? 1 : 0;
		
		if(!empty($dont_send))
			$properties['dont_send'] = $dont_send ? 1 : 0;
		
		if(!empty($is_autoreply))
			$properties['is_autoreply'] = $is_autoreply ? 1 : 0;
		
		if(!empty($is_broadcast))
			$properties['is_broadcast'] = $is_broadcast ? 1 : 0;
		
		if(!empty($is_forward))
			$properties['is_forward'] = $is_forward ? 1 : 0;
		
		// Custom fields
		
		$custom_fields = $this->_handleCustomFields($_POST);
		
		if(!empty($custom_fields))
			$properties['custom_fields'] = $custom_fields;

		// Send the message
		
		if(false == ($message_id = CerberusMail::sendTicketMessage($properties)))
			$this->error(self::ERRNO_CUSTOM, "Failed to create a reply message.");
		
		return $message->ticket_id;
	}
	
	private function postReply() {
		$worker = CerberusApplication::getActiveWorker();

		$ticket_id = $this->_handlePostReply();
		$this->getId($ticket_id);
	}
	
	private function postComment($id) {
		$worker = CerberusApplication::getActiveWorker();

		@$comment = DevblocksPlatform::importGPC($_POST['comment'],'string','');
		
		if(null == ($ticket = DAO_Ticket::get($id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("Invalid ticket ID %d", $id));
			
		// Check group memberships
		$memberships = $worker->getMemberships();
		if(!$worker->is_superuser && !isset($memberships[$ticket->group_id]))
			$this->error(self::ERRNO_ACL, 'Access denied to delete tickets in this group.');
		
		// Worker address exists
		if(null === ($address = CerberusApplication::hashLookupAddress($worker->email,true)))
			$this->error(self::ERRNO_CUSTOM, 'Your worker does not have a valid e-mail address.');
		
		// Required fields
		if(empty($comment))
			$this->error(self::ERRNO_CUSTOM, "The 'comment' field is required.");
			
		$fields = array(
			DAO_Comment::CREATED => time(),
			DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_TICKET,
			DAO_Comment::CONTEXT_ID => $ticket->id,
			DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
			DAO_Comment::OWNER_CONTEXT_ID => $worker->id,
			DAO_Comment::COMMENT => $comment,
		);
		$comment_id = DAO_Comment::create($fields);

		$this->success(array(
			'ticket_id' => $ticket->id,
			'comment_id' => $comment_id,
		));
	}
	
};