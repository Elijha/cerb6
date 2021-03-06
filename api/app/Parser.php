<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2014, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerbweb.com	    http://www.webgroupmedia.com/
***********************************************************************/
/*
 * IMPORTANT LICENSING NOTE from your friends on the Cerb Development Team
 *
 * Sure, it would be so easy to just cheat and edit this file to use the
 * software without paying for it.  But we trust you anyway.  In fact, we're
 * writing this software for you!
 *
 * Quality software backed by a dedicated team takes money to develop.  We
 * don't want to be out of the office bagging groceries when you call up
 * needing a helping hand.  We'd rather spend our free time coding your
 * feature requests than mowing the neighbors' lawns for rent money.
 *
 * We've never believed in hiding our source code out of paranoia over not
 * getting paid.  We want you to have the full source code and be able to
 * make the tweaks your organization requires to get more done -- despite
 * having less of everything than you might need (time, people, money,
 * energy).  We shouldn't be your bottleneck.
 *
 * We've been building our expertise with this project since January 2002.  We
 * promise spending a couple bucks [Euro, Yuan, Rupees, Galactic Credits] to
 * let us take over your shared e-mail headache is a worthwhile investment.
 * It will give you a sense of control over your inbox that you probably
 * haven't had since spammers found you in a game of 'E-mail Battleship'.
 * Miss. Miss. You sunk my inbox!
 *
 * A legitimate license entitles you to support from the developers,
 * and the warm fuzzy feeling of feeding a couple of obsessed developers
 * who want to help you get more done.
 *
 \* - Jeff Standen, Darren Sugita, Dan Hildebrandt
 *	 Webgroup Media LLC - Developers of Cerb
 */

class CerberusParserMessage {
	public $encoding = '';
	public $headers = array();
	public $body = '';
	public $body_encoding = '';
	public $htmlbody = '';
	public $files = array();
	public $custom_fields = array();
};

class CerberusParserModel {
	private $_message = null;
	
	private $_pre_actions = array();
	
	private $_is_new = true;
	private $_sender_address_model = null;
	private $_sender_worker_model = null;
	private $_subject = '';
	private $_date = 0;
	private $_ticket_id = 0;
	private $_ticket_model = null;
	private $_message_id = 0;
	private $_group_id = 0;
	
	public function __construct(CerberusParserMessage $message) {
		$this->setMessage($message);
		
		$this->_parseHeadersFrom();
		$this->_parseHeadersSubject();
		$this->_parseHeadersDate();
		$this->_parseHeadersIsNew();
	}
	
	public function validate() {
		$logger = DevblocksPlatform::getConsoleLog('Parser');
		
		// [TODO] Try...Catch
		
		// Is valid sender?
		if(null == $this->_sender_address_model) {
			$logger->error("From address could not be created.");
			return FALSE;
		}
		
		// Is banned?
		if($this->_sender_address_model->is_banned) {
			$logger->warn("Ignoring ticket from banned address: " . $this->_sender_address_model->email);
			return NULL;
		}
		
		return TRUE;
	}
	
	/**
	 * @return Model_Address|null
	 */
	private function _parseHeadersFrom() {
		try {
			$this->_sender_address_model = null;
			
			@$sReturnPath = $this->_message->headers['return-path'];
			@$sReplyTo = $this->_message->headers['reply-to'];
			@$sFrom = $this->_message->headers['from'];
			
			$from = array();
			
			if(empty($from) && !empty($sReplyTo))
				$from = CerberusParser::parseRfcAddress($sReplyTo);
			
			if(empty($from) && !empty($sFrom))
				$from = CerberusParser::parseRfcAddress($sFrom);
			
			if(empty($from) && !empty($sReturnPath))
				$from = CerberusParser::parseRfcAddress($sReturnPath);
			
			if(empty($from) || !is_array($from))
				throw new Exception('No sender headers found.');
			
			foreach($from as $addy) {
				if(empty($addy->mailbox) || empty($addy->host))
					continue;
			
				@$fromAddress = $addy->mailbox.'@'.$addy->host;
				
				if(null != ($fromInst = CerberusApplication::hashLookupAddress($fromAddress, true))) {
					$this->_sender_address_model = $fromInst;
					
					if(null != ($fromWorkerAuth = DAO_AddressToWorker::getByAddress($fromAddress))) {
						if($fromWorkerAuth->is_confirmed && null != ($fromWorker = DAO_Worker::get($fromWorkerAuth->worker_id)))
							$this->setSenderWorkerModel($fromWorker);
					}
					
					return;
				}
			}
			
		} catch (Exception $e) {
			$this->_sender_address_model = null;
			$this->_sender_worker_model = null;
			return false;
			
		}
	}
	
	/**
	 * @return string $subject
	 */
	private function _parseHeadersSubject() {
		$subject = '';
		
		// Handle multiple subjects
		if(isset($this->_message->headers['subject']) && !empty($this->_message->headers['subject'])) {
			$subject = $this->_message->headers['subject'];
			if(is_array($subject))
				$subject = array_shift($subject);
		}
		
		// Remove tabs, returns, and linefeeds
		$subject = str_replace(array("\t","\n","\r")," ",$subject);
		
		// The subject can still end up empty after QP decode
		if(0 == strlen(trim($subject)))
			$subject = "(no subject)";
			
		$this->_subject = $subject;
	}
	
	/**
	 * @return int $timestamp
	 */
	private function _parseHeadersDate() {
		$timestamp = @strtotime($this->_message->headers['date']);
		
		// If blank, or in the future, set to the current date
		if(empty($timestamp) || $timestamp > time())
			$timestamp = time();
			
		$this->_date = $timestamp;
	}
	
	/**
	 * First we check the references and in-reply-to headers to find a
	 * historical match in the database. If those don't match we check
	 * the subject line for a mask (if one exists). If none of those
	 * options match we return null.
	 */
	private function _parseHeadersIsNew() {
		@$aSubject = $this->_message->headers['subject'];
		@$sMessageId = trim($this->_message->headers['message-id']);
		@$sInReplyTo = trim($this->_message->headers['in-reply-to']);
		@$sReferences = trim($this->_message->headers['references']);
		@$sThreadTopic = trim($this->_message->headers['thread-topic']);

		@$senderWorker = $this->getSenderWorkerModel();
		
		$aReferences = array();
		
		// Add all References
		if(!empty($sReferences)) {
			if(preg_match("/(\<.*?\@.*?\>)/", $sReferences, $matches)) {
				unset($matches[0]); // who cares about the pattern
				foreach($matches as $ref) {
					$ref = trim($ref);
					if(!empty($ref) && 0 != strcasecmp($ref,$sMessageId))
						$aReferences[$ref] = 1;
				}
			}
		}

		unset($matches);
		
		// Append first <*> from In-Reply-To
		if(!empty($sInReplyTo)) {
			if(preg_match("/(\<.*?\@.*?\>)/", $sInReplyTo, $matches)) {
				if(isset($matches[1])) { // only use the first In-Reply-To
					$ref = trim($matches[1]);
					if(!empty($ref) && 0 != strcasecmp($ref,$sMessageId))
						$aReferences[$ref] = 1;
				}
			}
		}
		
		// Try matching our references or in-reply-to
		if(is_array($aReferences) && !empty($aReferences)) {
			foreach(array_keys($aReferences) as $ref) {
				if(empty($ref))
					continue;
				
				// Only consider the watcher auth header to be a reply if it validates
				
				if($senderWorker instanceof Model_Worker
						&& @preg_match('#\<([a-f0-9]+)\@cerb\d{0,1}\>#', $ref, $hits)
						&& false != ($relay_message_id = $this->isValidAuthHeader($ref, $senderWorker))) {
					
					if(null != ($ticket = DAO_Ticket::getTicketByMessageId($relay_message_id))) {
						$this->_is_new = false;
						$this->_ticket_id = $ticket->id;
						$this->_ticket_model = $ticket;
						$this->_message_id = $relay_message_id;
						return;
					}
				}
				
				// Otherwise, look up the normal header
				if(null != ($ids = DAO_Ticket::getTicketByMessageIdHeader($ref))) {
					$this->_is_new = false;
					$this->_ticket_id = $ids['ticket_id'];
					$this->_ticket_model = DAO_Ticket::get($this->_ticket_id);
					$this->_message_id = $ids['message_id'];
					return;
				}
			}
		}
		
		// Try matching the subject line
		// [TODO] This should only happen if the destination has subject masks enabled
		
		if(!is_array($aSubject))
			$aSubject = array($aSubject);
			
		foreach($aSubject as $subject) {
			if(preg_match("/.*\[.*?\#(.*?)\].*/", $subject, $matches)) {
				if(isset($matches[1])) {
					$mask = $matches[1];
					if(null != ($ticket = DAO_Ticket::getTicketByMask($mask))) {
						$this->_is_new = false;
						$this->_ticket_id = $ticket->id;
						$this->_ticket_model = $ticket;
						$this->_message_id = $ticket->last_message_id; // [TODO] ???
						return;
					}
				}
			}
		}

		$this->_is_new = true;
		$this->_ticket_id = 0;
		$this->_ticket_model = null;
		$this->_message_id = 0;
	}
	
	public function getRecipients() {
		$headers =& $this->_message->headers;
		$sources = array();
		
		if(isset($headers['to']))
			$sources = array_merge($sources, is_array($headers['to']) ? $headers['to'] : array($headers['to']));

		if(isset($headers['cc']))
			$sources = array_merge($sources, is_array($headers['cc']) ? $headers['cc'] : array($headers['cc']));
		
		if(isset($headers['envelope-to']))
			$sources = array_merge($sources, is_array($headers['envelope-to']) ? $headers['envelope-to'] : array($headers['envelope-to']));
		
		if(isset($headers['x-envelope-to']))
			$sources = array_merge($sources, is_array($headers['x-envelope-to']) ? $headers['x-envelope-to'] : array($headers['x-envelope-to']));
		
		if(isset($headers['delivered-to']))
			$sources = array_merge($sources, is_array($headers['delivered-to']) ? $headers['delivered-to'] : array($headers['delivered-to']));
		
		$destinations = array();
		foreach($sources as $source) {
			@$parsed = imap_rfc822_parse_adrlist($source,'localhost');
			$destinations = array_merge($destinations, is_array($parsed) ? $parsed : array($parsed));
		}
		
		$addresses = array();
		foreach($destinations as $destination) {
			if(empty($destination->mailbox) || empty($destination->host))
				continue;
			
			$addresses[] = $destination->mailbox.'@'.$destination->host;
		}
		
		@imap_errors(); // Prevent errors from spilling out into STDOUT

		return $addresses;
	}
	
	public function isWorkerRelayReply() {
		@$in_reply_to = trim($this->_message->headers['in-reply-to']);
		
		if(!empty($in_reply_to) && @preg_match('#\<[a-f0-9]+\@cerb\d{0,1}\>#', $in_reply_to))
			return true;
		
		return false;
	}
	
	public function isValidAuthHeader($in_reply_to, $worker) {
		if(empty($worker) || !($worker instanceof Model_Worker))
			return false;
		
		// See if we can trust the given message_id
		if(@preg_match('#\<([a-f0-9]+)\@cerb\d{0,1}\>#', $in_reply_to, $hits)) {
			@$hash = $hits[1];
			@$signed = substr($hash, 4, 40);
			@$message_id = hexdec(substr($hash, 44));
			
			$signed_compare = sha1($message_id . $worker->id . APP_DB_PASS);
			
			$is_authenticated = ($signed_compare == $signed);
			
			return $message_id;
		}
		
		return false;
	}
	
	// Getters/Setters
	
	/**
	 * @return CerberusParserMessage
	 */
	public function &getMessage() {
		return $this->_message;
	}
	
	public function setMessage(CerberusParserMessage $message) {
		$this->_message = $message;
	}
	
	public function &getHeaders() {
		return $this->_message->headers;
	}
	
	public function &getPreActions() {
		return $this->_pre_actions;
	}
	
	public function addPreAction($action, $params=array()) {
		$this->_pre_actions[$action] = $params;
	}
	
	public function &getIsNew() {
		return $this->_is_new;
	}
	
	public function setIsNew($bool) {
		$this->_is_new = $bool;
	}
	
	public function &getSenderAddressModel() {
		return $this->_sender_address_model;
	}
	
	public function setSenderAddressModel($model) {
		$this->_sender_address_model = $model;
	}
	
	public function &getSenderWorkerModel() {
		return $this->_sender_worker_model;
	}
	
	public function setSenderWorkerModel($model) {
		$this->_sender_worker_model = $model;
	}
	
	public function isSenderWorker() {
		return $this->_sender_worker_model instanceof Model_Worker;
	}
	
	public function &getSubject() {
		return $this->_subject;
	}
	
	public function setSubject($subject) {
		$this->_subject = $subject;
	}
	
	public function &getDate() {
		return $this->_date;
	}
	
	public function setDate($date) {
		$this->_date = $date;
	}
	
	public function setTicketId($id) {
		$this->_ticket_id = $id;
	}
	
	public function &getTicketId() {
		return $this->_ticket_id;
	}
	
	/**
	 * @return Model_Ticket
	 */
	public function getTicketModel() {
		if(!empty($this->_ticket_model))
			return $this->_ticket_model;

		if(empty($this->_ticket_id))
			return null;
		
		// Lazy-load
		if(!empty($this->_ticket_id)) {
			$this->_ticket_model = DAO_Ticket::get($this->_ticket_id);
		}
		
		return $this->_ticket_model;
	}
	
	public function setMessageId($id) {
		$this->_message_id = $id;
	}
	
	public function getMessageId() {
		return $this->_message_id;
	}
	
	public function setGroupId($id) {
		$groups = DAO_Group::getAll();
		
		if(!isset($groups[$id])) {
			$id = 0;
		}
		
		$this->_group_id = $id;
	}
	
	public function getGroupId() {
		if(!empty($this->_group_id))
			return $this->_group_id;
		
		$ticket_id = $this->getTicketId();
			
		if(!empty($ticket_id)) {
			if(null != ($model = $this->getTicketModel())) {
				$this->_group_id = $model->group_id;
			}
		}
		
		return $this->_group_id;
	}
};

class ParserFile {
	public $tmpname = null;
	public $mime_type = '';
	public $file_size = 0;
	public $parsed_attachment_id = 0;
	public $parsed_attachment_guid = '';

	function __destruct() {
		if(file_exists($this->tmpname)) {
			@unlink($this->tmpname);
		}
	}

	public function setTempFile($tmpname,$mimetype='application/octet-stream') {
		$this->mime_type = $mimetype;

		if(!empty($tmpname) && file_exists($tmpname)) {
			$this->tmpname = $tmpname;
		}
	}

	public function getTempFile() {
		return $this->tmpname;
	}

	static public function makeTempFilename() {
		$path = APP_TEMP_PATH . DIRECTORY_SEPARATOR;
		return tempnam($path,'mime');
	}
};

class ParseFileBuffer extends ParserFile {
	public $mime_filename = '';
	public $section = null;
	public $info = array();
	private $fp = null;

	function __construct($section, $info, $mime_filename) {
		$this->mime_filename = $mime_filename;
		$this->section = $section;
		$this->info = $info;

		$this->setTempFile(ParserFile::makeTempFilename(),@$info['content-type']);
		$this->fp = fopen($this->getTempFile(),'wb');

		if($this->fp && !empty($this->section) && !empty($this->mime_filename)) {
			mailparse_msg_extract_part_file($this->section, $this->mime_filename, array($this, "writeCallback"));
		}

		@fclose($this->fp);
	}

	function writeCallback($chunk) {
		$this->file_size += fwrite($this->fp, $chunk);
	}
};

class CerberusParser {
	static public function parseMessageSource($message_source, $delete_on_success=true, $delete_on_failure=true) {
		$file = null;
		
		try {
			if(preg_match('/^file:\/\/(.*?)$/', $message_source, $matches)) {
				$file = $matches[1];
				
			} else {
				$message_source .= PHP_EOL;
				
				if(null == ($file = CerberusParser::saveMimeToFile($message_source))) {
					throw new Exception('The MIME file could not be saved.');
				}
			}
			
			if(null == ($mime = mailparse_msg_parse_file($file))) {
				throw new Exception("The message mime could not be decoded (it's probably malformed).");
			}
				
			if(null == ($parser_msg = CerberusParser::parseMime($mime, $file))) {
				throw new Exception("The message mime could not be parsed (it's probably malformed).");
			}
			
			if(false === ($ticket_id = CerberusParser::parseMessage($parser_msg))) {
				throw new Exception("The message was rejected by the parser.");
			}
			
			if($delete_on_success)
				@unlink($file);
			
			if(is_numeric($ticket_id)) {
				$values = array();
				CerberusContexts::getContext(CerberusContexts::CONTEXT_TICKET, $ticket_id, $null, $values);
				$dict = new DevblocksDictionaryDelegate($values);
				return $dict;
				
			} else {
				return $ticket_id;
			}
			
		} catch (Exception $e) {
			if($delete_on_failure)
				@unlink($file);
			throw $e;
		}
		
		return false;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param object $mime
	 * @return CerberusParserMessage
	 */
	static public function parseMime($mime, $full_filename) {
		$struct = mailparse_msg_get_structure($mime);
		$msginfo = mailparse_msg_get_part_data($mime);
		
		$message = new CerberusParserMessage();
		@$message->encoding = $msginfo['charset'];
		@$message->body_encoding = $message->encoding; // default

		// Decode headers
		@$message->headers = $msginfo['headers'];
		
		if(is_array($message->headers))
		foreach($message->headers as $header_name => $header_val) {
			if(is_array($header_val)) {
				foreach($header_val as $idx => $val) {
					$message->headers[$header_name][$idx] = self::fixQuotePrintableString($val, $message->body_encoding);
				}
			} else {
				$message->headers[$header_name] = self::fixQuotePrintableString($header_val, $message->body_encoding);
			}
		}
		
		$settings = DevblocksPlatform::getPluginSettingsService();
		$is_attachments_enabled = $settings->get('cerberusweb.core',CerberusSettings::ATTACHMENTS_ENABLED,CerberusSettingsDefaults::ATTACHMENTS_ENABLED);
		$attachments_max_size = $settings->get('cerberusweb.core',CerberusSettings::ATTACHMENTS_MAX_SIZE,CerberusSettingsDefaults::ATTACHMENTS_MAX_SIZE);
		
		$ignore_mime_prefixes = array();
		
		$message_counter_attached = 0;
		$message_counter_delivery_status = 0;
		
		foreach($struct as $st) {
			// Are we ignoring specific nested mime parts?
			$skip = false;
			foreach($ignore_mime_prefixes as $ignore) {
				if(0 == strcmp(substr($st, 0, strlen($ignore)), $ignore)) {
					$skip = true;
				}
			}
			
			if($skip)
				continue;
			
			$section = mailparse_msg_get_part($mime, $st);
			$info = mailparse_msg_get_part_data($section);

			// Overrides
			switch(strtolower($info['charset'])) {
				case 'gb2312':
					$info['charset'] = 'gbk';
					break;
			}

			// See if we have a content filename
			
			$content_filename = isset($info['disposition-filename']) ? $info['disposition-filename'] : '';
			
			if(empty($content_filename))
				$content_filename = isset($info['content-name']) ? $info['content-name'] : '';
			
			$content_filename = self::fixQuotePrintableString($content_filename, $info['charset']);
			
			// Content type
			
			$content_type = isset($info['content-type']) ? $info['content-type'] : '';
			
			// handle parts that shouldn't have a content-name, don't handle twice
			$handled = false;
			
			if(empty($content_filename)) {
				switch(strtolower($content_type)) {
					case 'text/calendar':
						$content_filename = 'calendar.ics';
						break;
						
					case 'text/plain':
						$text = mailparse_msg_extract_part_file($section, $full_filename, NULL);
						
						if(isset($info['charset']) && !empty($info['charset'])) {
							
							// Extract inline bounces as attachments
							
							$bounce_token = '------ This is a copy of the message, including all the headers. ------';
							
							if(false !== ($bounce_pos = @mb_strpos($text, $bounce_token, 0, $info['charset']))) {
								$bounce_text = mb_substr($text, $bounce_pos + strlen($bounce_token), strlen($text), $info['charset']);
								$text = mb_substr($text, 0, $bounce_pos, $info['charset']);
								
								$bounce_text = self::convertEncoding($bounce_text);
								
								$tmpname = ParserFile::makeTempFilename();
								$rfc_attach = new ParserFile();
								$rfc_attach->setTempFile($tmpname,'message/rfc822');
								@file_put_contents($tmpname, $bounce_text);
								$rfc_attach->file_size = filesize($tmpname);
								$rfc_attach->mime_type = 'text/plain';
								$rfc_attach_filename = sprintf("attached_message_%03d.txt",
									++$message_counter_attached
								);
								$message->files[$rfc_attach_filename] = $rfc_attach;
								unset($rfc_attach);
							}
							
							$message->body_encoding = $info['charset'];
							$text = self::convertEncoding($text, $info['charset']);
						}
						
						$message->body .= $text;
						
						unset($text);
						$handled = true;
						break;
					
					case 'text/html':
						@$text = mailparse_msg_extract_part_file($section, $full_filename, NULL);
						
						if(isset($info['charset']) && !empty($info['charset'])) {
							$text = self::convertEncoding($text, $info['charset']);
						}
						
						$message->htmlbody .= $text;
						
						unset($text);
						$handled = true;
						break;
						 
					case 'message/delivery-status':
						@$message_content = mailparse_msg_extract_part_file($section, $full_filename, NULL);
						$message_counter_delivery_status++;

						$tmpname = ParserFile::makeTempFilename();
						$bounce_attach = new ParserFile();
						$bounce_attach->setTempFile($tmpname,'message/delivery-status');
						@file_put_contents($tmpname, $message_content);
						$bounce_attach->file_size = filesize($tmpname);
						$bounce_attach->mime_type = 'message/delivery-status';
						$bounce_attach_filename = sprintf("delivery_status_%03d.txt",
							$message_counter_delivery_status
						);
						$message->files[$bounce_attach_filename] = $bounce_attach;
						unset($bounce_attach);
						$handled = true;
						
						// Skip any nested parts in this message/rfc822 parent
						$ignore_mime_prefixes[] = $st . '.';
						break;

					case 'message/rfc822':
						@$message_content = mailparse_msg_extract_part_file($section, $full_filename, NULL);
						$message_counter_attached++;

						$tmpname = ParserFile::makeTempFilename();
						$rfc_attach = new ParserFile();
						$rfc_attach->setTempFile($tmpname,'message/rfc822');
						@file_put_contents($tmpname,$message_content);
						$rfc_attach->file_size = filesize($tmpname);
						$rfc_attach->mime_type = 'text/plain';
						$rfc_attach_filename = sprintf("attached_message_%03d.txt",
							$message_counter_attached
						);
						$message->files[$rfc_attach_filename] = $rfc_attach;
						unset($rfc_attach);
						$handled = true;

						// Skip any nested parts in this message/rfc822 parent
						$ignore_mime_prefixes[] = $st . '.';
						break;
				}
			}

			// whether or not it has a content-name, we need to add it as an attachment (if not already handled)
			if(!$handled) {
				if (false === strpos(strtolower($info['content-type']),'multipart')) {
					if(!$is_attachments_enabled) {
						break; // skip attachment
					}
					$attach = new ParseFileBuffer($section, $info, $full_filename);
					
					// [TODO] This could be more efficient by not even saving in the first place above:
					// Make sure our attachment is under the max preferred size
					if(filesize($attach->tmpname) > ($attachments_max_size * 1024000)) {
						@unlink($attach->tmpname);
						break;
					}

					// content-name is not necessarily unique...
					if (isset($message->files[$content_filename])) {
						$j=1;
						while (isset($message->files[$content_filename . '(' . $j . ')'])) {
							$j++;
						}
						$content_filename = $content_filename . '(' . $j . ')';
					}
					
					$message->files[$content_filename] = $attach;
				}
			}
		}
		
		// generate the plaintext part (if necessary)
		if(empty($message->body) && !empty($message->htmlbody)) {
			$message->body = DevblocksPlatform::stripHTML($message->htmlbody);
		}

		return $message;
	}

	/**
	 * Enter description here...
	 *
	 * @param string $source
	 * @return $filename
	 */
	static public function saveMimeToFile($source, $path=null) {
		if(empty($path))
			$path = APP_TEMP_PATH . DIRECTORY_SEPARATOR;
		else
			$path = $path . DIRECTORY_SEPARATOR;
		 
		do {
			$unique = sprintf("%s.%04d.msg",
				time(),
				mt_rand(0,9999)
			);
			$filename = $path . $unique;
		} while(file_exists($filename));

		$fp = fopen($filename,'w');

		if($fp) {
			fwrite($fp,$source,strlen($source));
			@fclose($fp);
		}

		return $filename;
	}

	/**
	 * @param CerberusParserMessage $message
	 * @return integer
	 */
	static public function parseMessage(CerberusParserMessage $message, $options=array()) {
		/*
		 * options:
		 * 'no_autoreply'
		 */
		$logger = DevblocksPlatform::getConsoleLog();
		$url_writer = DevblocksPlatform::getUrlService();
		
		$headers =& $message->headers;

		/*
		 * [mdf] Check attached files before creating the ticket because we may need to
		 * overwrite the message-id also store any contents of rfc822 files so we can
		 * include them after the body
		 */
		// [TODO] Refactor
		if(is_array($message->files))
		foreach ($message->files as $filename => $file) { /* @var $file ParserFile */
			switch($file->mime_type) {
				case 'message/rfc822':
					$full_filename = $file->tmpname;
					$mail = mailparse_msg_parse_file($full_filename);
					$struct = mailparse_msg_get_structure($mail);
					$msginfo = mailparse_msg_get_part_data($mail);
					
					$inline_headers = $msginfo['headers'];
					if(isset($headers['from']) && (strtolower(substr($headers['from'], 0, 11))=='postmaster@' || strtolower(substr($headers['from'], 0, 14))=='mailer-daemon@')) {
						$headers['in-reply-to'] = $inline_headers['message-id'];
					}
				break;
			}
		}
		
		// Parse headers into $model
		$model = new CerberusParserModel($message); /* @var $model CerberusParserModel */
		
		if(false == ($validated = $model->validate()))
			return $validated; // false or null
		
		// Pre-parse mail filters
		// Changing the incoming message through a VA
		Event_MailReceivedByApp::trigger($model);
		
		$pre_actions = $model->getPreActions();
		
		// Reject?
		if(isset($pre_actions['reject'])) {
			$logger->warn('Rejecting based on Virtual Attendant filtering.');
			return NULL;
		}
		
		// Filter attachments?
		// [TODO] Encapsulate this somewhere
		if(isset($pre_actions['attachment_filters']) && !empty($message->files)) {
			foreach($message->files as $filename => $file) {
				$matched = false;
				
				foreach($pre_actions['attachment_filters'] as $filter) {
					if($matched)
						continue;
					
					if(!isset($filter['oper']) || !isset($filter['value']))
						continue;
					
					$not = false;
					
					if(substr($filter['oper'],0,1)=='!') {
						$not = true;
						$filter['oper'] = substr($filter['oper'],1);
					}
					
					switch($filter['oper']) {
						case 'is':
							$matched = (0 == strcasecmp($filename, $filter['value']));
							break;
							
						case 'like':
						case 'regexp':
							if($filter['oper'] == 'like')
								$pattern = DevblocksPlatform::strToRegExp($filter['value']);
							else
								$pattern = $filter['value'];
							
							$matched = (@preg_match($pattern, $filename));
							break;
					}
					
					// Did we want a negative match?
					if(!$matched && $not)
						$matched = true;
					
					// Remove a matched attachment
					if($matched) {
						$logger->info(sprintf("Removing attachment '%s' based on Virtual Attendant filtering.", $filename));
						@unlink($file->tmpname);
						unset($message->files[$filename]);
						continue;
					}
				}
			}
		}
		
		// Overloadable
		$enumSpamTraining = '';

		// Is it a worker reply from an external client?  If so, proxy
		
		if($model->isWorkerRelayReply()
			&& $model->isSenderWorker()
			&& null != ($proxy_ticket = $model->getTicketModel())
			&& null != ($proxy_worker = $model->getSenderWorkerModel())
			&& !$proxy_worker->is_disabled) { /* @var $proxy_worker Model_Worker */
			
			$logger->info("[Worker Relay] Handling an external worker relay for " . $model->getSenderAddressModel()->email);

			$is_authenticated = false;

			// If it's a watcher reply
			$relay_auth_disabled = DevblocksPlatform::getPluginSetting('cerberusweb.core', CerberusSettings::RELAY_DISABLE_AUTH, CerberusSettingsDefaults::RELAY_DISABLE_AUTH);
			
			if($relay_auth_disabled) {
				$is_authenticated = true;
				
			} else {
				if(isset($message->headers['in-reply-to']) && $proxy_worker instanceof Model_Worker)
					$is_authenticated = $model->isValidAuthHeader($message->headers['in-reply-to'], $proxy_worker);
			}

			// Compare worker signature, then auth
			if($is_authenticated) {
				$logger->info("[Worker Relay] Worker authentication successful. Proceeding.");

				if(!empty($proxy_ticket)) {
					
					// Log activity as the worker
					CerberusContexts::pushActivityDefaultActor(CerberusContexts::CONTEXT_WORKER, $proxy_worker->id);
					
					$parser_message = $model->getMessage();
					$attachment_file_ids = array();
					
					foreach($parser_message->files as $filename => $file) {
						if(0 == strcasecmp($filename, 'original_message.html'))
							continue;

						// Dupe detection
						$sha1_hash = sha1_file($file->tmpname, false);
						
						if(false == ($file_id = DAO_Attachment::getBySha1Hash($sha1_hash, $filename))) {
							$fields = array(
								DAO_Attachment::DISPLAY_NAME => $filename,
								DAO_Attachment::MIME_TYPE => $file->mime_type,
								DAO_Attachment::STORAGE_SHA1HASH => $sha1_hash,
							);
								
							$file_id = DAO_Attachment::create($fields);
						}

						if($file_id) {
							$attachment_file_ids[] = $file_id;
						}
							
						if(null !== ($fp = fopen($file->getTempFile(), 'rb'))) {
							Storage_Attachments::put($file_id, $fp);
							fclose($fp);
						}
						
						@unlink($file->getTempFile());
					}
					
					// Properties
					$properties = array(
						'ticket_id' => $proxy_ticket->id,
						'message_id' => is_numeric($is_authenticated) ? $is_authenticated : $proxy_ticket->last_message_id,
						'forward_files' => $attachment_file_ids,
						'link_forward_files' => true,
						'worker_id' => $proxy_worker->id,
					);
					 
					// Clean the reply body
					$body = '';
					$lines = DevblocksPlatform::parseCrlfString($message->body, true);
					
					$state = '';
					$comments = array();
					$comment_ptr = null;
					
					if(is_array($lines))
					foreach($lines as $line) {
						
						// Ignore quoted relay comments
						if(preg_match('/[\s\>]*\s*##/', $line))
							continue;

						// Insert worker sig for this bucket
						if(preg_match('/^#sig/', $line, $matches)) {
							$state = '#sig';
							$group = DAO_Group::get($proxy_ticket->group_id);
							$sig = $group->getReplySignature($proxy_ticket->bucket_id, $proxy_worker);
							$body .= $sig . PHP_EOL;
						
						} elseif(preg_match('/^#start (.*)/', $line, $matches)) {
							switch(@$matches[1]) {
								case 'comment':
									$state = '#comment_block';
									$comment_ptr =& $comments[];
									break;
							}
							
						} elseif(preg_match('/^#end/', $line, $matches)) {
							$state = '';
							
						} elseif(preg_match('/^#cut/', $line, $matches)) {
							$state = '#cut';
							
						} elseif(preg_match('/^#watch/', $line, $matches)) {
							$state = '#watch';
							CerberusContexts::addWatchers(CerberusContexts::CONTEXT_TICKET, $proxy_ticket->id, $proxy_worker->id);
							
						} elseif(preg_match('/^#unwatch/', $line, $matches)) {
							$state = '#unwatch';
							CerberusContexts::removeWatchers(CerberusContexts::CONTEXT_TICKET, $proxy_ticket->id, $proxy_worker->id);
							
						} elseif(preg_match('/^#noreply/', $line, $matches)) {
							$state = '#noreply';
							$properties['dont_send'] = 1;
							$properties['dont_keep_copy'] = 1;
							
						} elseif(preg_match('/^#status (.*)/', $line, $matches)) {
							$state = '#status';
							switch(strtolower($matches[1])) {
								case 'o':
								case 'open':
									$properties['closed'] = 0;
									break;
								case 'w':
								case 'waiting':
									$properties['closed'] = 2;
									break;
								case 'c':
								case 'closed':
									$properties['closed'] = 1;
									break;
							}
							
						} elseif(preg_match('/^#reopen (.*)/', $line, $matches)) {
							$state = '#reopen';
							$properties['ticket_reopen'] = $matches[1];
							
						} elseif(preg_match('/^#comment(.*)/', $line, $matches)) {
							$state = '#comment';
							$comment_ptr =& $comments[];
							$comment_ptr = @$matches[1] ? ltrim($matches[1]) : '';
							
						} else {
							
							switch($state) {
								case '#comment':
									if(empty($line)) {
										$state = '';
									} else {
										$comment_ptr .= ' ' . ltrim($line);
									}
									break;
									
								case '#comment_block':
									$comment_ptr .= $line . "\n";
									break;
							}
							
							if(!in_array($state, array('#cut', '#comment', '#comment_block'))) {
								$body .= $line . PHP_EOL;
							}
						}
					}

					if(is_array($comments))
					foreach($comments as $comment) {
						DAO_Comment::create(array(
							DAO_Comment::CREATED => time(),
							DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
							DAO_Comment::OWNER_CONTEXT_ID => $proxy_worker->id,
							DAO_Comment::COMMENT => $comment,
							DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_TICKET,
							DAO_Comment::CONTEXT_ID => $proxy_ticket->id,
						));
					}
					
					$properties['content'] = ltrim($body);
					
					
					CerberusMail::sendTicketMessage($properties);

					// Stop logging activity as the worker
					CerberusContexts::popActivityDefaultActor();
					
					return $proxy_ticket->id;
				}

			} else { // failed worker auth
				// [TODO] Bounce
				$logger->error("[Worker Relay] Worker authentication failed. Ignoring.");
				return false;
			}

		}

		// New Ticket
		if($model->getIsNew()) {
			// Insert a bare minimum record so we can get a ticket ID back
			$fields = array(
				DAO_Ticket::CREATED_DATE => time(),
				DAO_Ticket::UPDATED_DATE => time(),
			);
			$model->setTicketId(DAO_Ticket::create($fields));
			
			if(null == $model->getTicketId()) {
				$logger->error("Problem saving ticket...");
				return false;
			}
			
			// [JAS]: Add requesters to the ticket
			$sender = $model->getSenderAddressModel();
			if(!empty($sender)) {
				DAO_Ticket::createRequester($sender->email, $model->getTicketId());
			}
				
			// Add the other TO/CC addresses to the ticket
			if(DevblocksPlatform::getPluginSetting('cerberusweb.core', CerberusSettings::PARSER_AUTO_REQ, CerberusSettingsDefaults::PARSER_AUTO_REQ)) {
				$destinations = $model->getRecipients();
				
				// [TODO] Multiple values in one insert
				if(is_array($destinations))
				foreach($destinations as $dest) {
					DAO_Ticket::createRequester($dest, $model->getTicketId());
				}
			}
			
			// Routing new tickets
			if(null != ($routing_rules = Model_MailToGroupRule::getMatches(
				$model->getSenderAddressModel(),
				$message
			))) {
				
				// Update our model with the results of the routing rules
				if(is_array($routing_rules))
				foreach($routing_rules as $rule) {
					$rule->run($model);
				}
			}
			
			// Last ditch effort to check for a default group to deliver to
			if(null == $model->getGroupId()) {
				if(null != ($default_group = DAO_Group::getDefaultGroup())) {
					$model->setGroupId($default_group->id);
				}
			}

			// Bounce if we can't set the group id
			if(null == $model->getGroupId()) {
				$logger->error("[Parser] Can't determine a default group to deliver to.");
				return FALSE;
			}

		} // endif ($model->getIsNew())
		 
		$fields = array(
			DAO_Message::TICKET_ID => $model->getTicketId(),
			DAO_Message::CREATED_DATE => $model->getDate(),
			DAO_Message::ADDRESS_ID => $model->getSenderAddressModel()->id,
			DAO_Message::WORKER_ID => $model->isSenderWorker() ? $model->getSenderWorkerModel()->id : 0,
		);
		$model->setMessageId(DAO_Message::create($fields));

		$message_id = $model->getMessageId();
		if(empty($message_id)) {
			$logger->error("Problem saving message to database...");
			return false;
		}
		
		// Save message content
		Storage_MessageContent::put($model->getMessageId(), $message->body);
		
		// Save headers
		if(is_array($headers))
			DAO_MessageHeader::creates($model->getMessageId(), $headers);
		
		// [mdf] Loop through files to insert attachment records in the db, and move temporary files
		foreach ($message->files as $filename => $file) { /* @var $file ParserFile */
			$handled = false;
			
			switch($file->mime_type) {
				case 'message/delivery-status':
					$message_content = file_get_contents($file->tmpname);
					
					$status_code = 0;
					
					if(preg_match('#Diagnostic-Code:(.*);\s*(\d+) (\d\.\d\.\d)(.*)#i', $message_content, $matches)) {
						$status_code = intval($matches[2]);
						
					} elseif(preg_match('#Status:\s*(\d\.\d\.\d)#i', $message_content, $matches)) {
						$status_code = intval(DevblocksPlatform::strAlphaNum($matches[1]));
					}
					
					// Permanent failure
					if($status_code >= 500 && $status_code < 600) {
						$logger->info(sprintf("[Parser] This is a permanent failure delivery-status (%d)", $status_code));
						
						$matches = array();
						
						if(preg_match('#Original-Recipient:\s*(.*)#i', $message_content, $matches)) {
							// Use the original address if provided
						} elseif (preg_match('#Final-Recipient:\s*(.*)#i', $message_content, $matches)) {
							// Otherwise, try to fall back to the final recipient
						}
						
						if(is_array($matches) && isset($matches[1])) {
							$entry = explode(';', trim($matches[1]));
							
							if(2 == count($entry)) {
								// Set this sender to defunct
								if(false != ($bouncer = DAO_Address::lookupAddress(trim($entry[1]), true))) {
									DAO_Address::update($bouncer->id, array(
										DAO_Address::IS_DEFUNCT => 1,
									));
									
									$logger->info(sprintf("[Parser] Setting %s to defunct", $bouncer->email));
									
									// ... and add them as a requester
									DAO_Ticket::createRequester($bouncer->email, $model->getTicketId());
								}
							}
							
							// [TODO] Find the original message-id ?
							
							// Nuke the attachment?
							$handled = true;
						}
					}
					//}
					break;
			}
			
			if(!$handled) {
				$sha1_hash = sha1_file($file->getTempFile(), false);

				// Dupe detection
				if(null == ($file_id = DAO_Attachment::getBySha1Hash($sha1_hash, $filename))) {
					$fields = array(
						DAO_Attachment::DISPLAY_NAME => $filename,
						DAO_Attachment::MIME_TYPE => $file->mime_type,
						DAO_Attachment::STORAGE_SHA1HASH => $sha1_hash,
					);
					$file_id = DAO_Attachment::create($fields);

					// Store the content in the new file
					if(null !== ($fp = fopen($file->getTempFile(), 'rb'))) {
						Storage_Attachments::put($file_id, $fp);
						fclose($fp);
					}
				}
				
				// Link
				if($file_id) {
					$file->parsed_attachment_id = $file_id;
					$file->parsed_attachment_guid = DAO_AttachmentLink::create($file_id, CerberusContexts::CONTEXT_MESSAGE, $model->getMessageId());
				}
				
				// Rewrite any inline content-id images in the HTML part
				if(isset($file->info) && isset($file->info['content-id'])) {
					$inline_cid_url = $url_writer->write('c=files&guid=' . $file->parsed_attachment_guid . '&name=' . urlencode($filename), true);
					$message->htmlbody = str_replace('cid:' . $file->info['content-id'], $inline_cid_url, $message->htmlbody);
				}
			}
			
			// Remove the temp file
			@unlink($file->tmpname);
		}
		
		// Save the HTML part as an 'original_message.html' attachment
		// [TODO] Make attaching the HTML part an optional config option (off by default)
		if(!empty($message->htmlbody)) {
			$sha1_hash = sha1($message->htmlbody, false);
			
			if(null == ($file_id = DAO_Attachment::getBySha1Hash($sha1_hash, 'original_message.html'))) {
				$fields = array(
					DAO_Attachment::DISPLAY_NAME => 'original_message.html',
					DAO_Attachment::MIME_TYPE => 'text/html',
					DAO_Attachment::STORAGE_SHA1HASH => $sha1_hash,
				);
				$file_id = DAO_Attachment::create($fields);
				
				Storage_Attachments::put($file_id, $message->htmlbody);
			}
			
			if(!empty($file_id))
				DAO_AttachmentLink::create($file_id, CerberusContexts::CONTEXT_MESSAGE, $model->getMessageId());
		}
		
		// Pre-load custom fields
		
		$cf_values = array();
		
		if(isset($message->custom_fields) && !empty($message->custom_fields))
		foreach($message->custom_fields as $cf_data) {
			if(!is_array($cf_data))
				continue;
		
			@$cf_id = $cf_data['field_id'];
			@$cf_context = $cf_data['context'];
			@$cf_context_id = $cf_data['context_id'];
			@$cf_val = $cf_data['value'];
			
			// If we're setting fields on the ticket, find the ticket ID
			if($cf_context == CerberusContexts::CONTEXT_TICKET && empty($cf_context_id))
				$cf_context_id = $model->getTicketId();
			
			if((is_array($cf_val) && !empty($cf_val))
				|| (!is_array($cf_val) && 0 != strlen($cf_val))) {
					$cf_values[$cf_id] = $cf_val;
			}
		}
		
		if(!empty($cf_values))
			DAO_CustomFieldValue::formatAndSetFieldValues($cf_context, $cf_context_id, $cf_values);

		// If the sender was previously defunct, remove the flag
		
		$sender = $model->getSenderAddressModel();
		if($sender->is_defunct && $sender->id) {
			DAO_Address::update($sender->id, array(
				DAO_Address::IS_DEFUNCT => 0,
			));
			
			$logger->info(sprintf("[Parser] Setting %s as no longer defunct", $sender->email));
		}
		
		// Finalize our new ticket details (post-message creation)
		/* @var $model CerberusParserModel */
		if($model->getIsNew()) {
			$change_fields = array(
				DAO_Ticket::MASK => CerberusApplication::generateTicketMask(),
				DAO_Ticket::SUBJECT => $model->getSubject(),
				DAO_Ticket::IS_CLOSED => 0,
				DAO_Ticket::FIRST_WROTE_ID => intval($model->getSenderAddressModel()->id),
				DAO_Ticket::LAST_WROTE_ID => intval($model->getSenderAddressModel()->id),
				DAO_Ticket::CREATED_DATE => time(),
				DAO_Ticket::UPDATED_DATE => time(),
				DAO_Ticket::ORG_ID => intval($model->getSenderAddressModel()->contact_org_id),
				DAO_Ticket::LAST_ACTION_CODE => CerberusTicketActionCode::TICKET_OPENED,
				DAO_Ticket::FIRST_MESSAGE_ID => $model->getMessageId(),
				DAO_Ticket::LAST_MESSAGE_ID => $model->getMessageId(),
				DAO_Ticket::GROUP_ID => $model->getGroupId(), // this triggers move rules
			);
			
			// Spam probabilities
			// [TODO] Check headers?
			if(false !== ($spam_data = CerberusBayes::calculateContentSpamProbability($model->getSubject() . ' ' . $message->body))) {
				$change_fields[DAO_Ticket::SPAM_SCORE] = $spam_data['probability'];
				$change_fields[DAO_Ticket::INTERESTING_WORDS] = $spam_data['interesting_words'];
			}
		
			// Save properties
			if(!empty($change_fields))
				DAO_Ticket::update($model->getTicketId(), $change_fields);
				
		} else { // Reply
		
			// Re-open and update our date on new replies
			DAO_Ticket::update($model->getTicketId(),array(
				DAO_Ticket::UPDATED_DATE => time(),
				DAO_Ticket::IS_WAITING => 0,
				DAO_Ticket::IS_CLOSED => 0,
				DAO_Ticket::IS_DELETED => 0,
				DAO_Ticket::LAST_MESSAGE_ID => $model->getMessageId(),
				DAO_Ticket::LAST_WROTE_ID => $model->getSenderAddressModel()->id,
				DAO_Ticket::LAST_ACTION_CODE => CerberusTicketActionCode::TICKET_CUSTOMER_REPLY,
			));
			// [TODO] The TICKET_CUSTOMER_REPLY should be sure of this message address not being a worker
		}
		
		/*
		 * Log activity (ticket.message.inbound)
		 */
		$entry = array(
			//{{actor}} replied to ticket {{target}}
			'message' => 'activities.ticket.message.inbound',
			'variables' => array(
				'target' => $model->getSubject(),
				),
			'urls' => array(
				'target' => sprintf("ctx://%s:%d", CerberusContexts::CONTEXT_TICKET, $model->getTicketId()),
				)
		);
		CerberusContexts::logActivity('ticket.message.inbound', CerberusContexts::CONTEXT_TICKET, $model->getTicketId(), $entry, CerberusContexts::CONTEXT_ADDRESS, $model->getSenderAddressModel()->id);

		// Trigger Mail Received
		Event_MailReceived::trigger($model->getMessageId());
		
		// Trigger Group Mail Received
		Event_MailReceivedByGroup::trigger($model->getMessageId(), $model->getGroupId());
		
		// Trigger Watcher Mail Received
		$context_watchers = CerberusContexts::getWatchers(CerberusContexts::CONTEXT_TICKET, $model->getTicketId());
		
		// Include the owner

		@$ticket_owner_id = $model->getTicketModel()->owner_id;
		
		if(!empty($ticket_owner_id) && !isset($context_watchers[$ticket_owner_id]))
			$context_watchers[$ticket_owner_id] = true;

		if(is_array($context_watchers) && !empty($context_watchers))
		foreach(array_unique(array_keys($context_watchers)) as $watcher_id) {
			Event_MailReceivedByWatcher::trigger($model->getMessageId(), $watcher_id);
		}
		
		@imap_errors(); // Prevent errors from spilling out into STDOUT
		
		return $model->getTicketId();
	}
	
	// [TODO] Phase out in favor of the CerberusUtils class
	static function parseRfcAddress($address_string) {
		return CerberusUtils::parseRfcAddressList($address_string);
	}
	
	static function convertEncoding($text, $charset=null) {
		$charset = strtolower($charset);
		
		// Otherwise, fall back to mbstring's auto-detection
		mb_detect_order('iso-2022-jp-ms, iso-2022-jp, utf-8, iso-8859-1, windows-1252');
		
		// Normalize charsets
		switch($charset) {
			case 'us-ascii':
				$charset = 'ascii';
				break;
				
			case 'win-1252':
				$charset = 'windows-1252';
				break;
				
			case NULL:
				$charset = mb_detect_encoding($text);
				break;
		}
		
		// If we're starting with Windows-1252, convert some special characters
		if(0 == strcasecmp($charset, 'windows-1252')) {
		
			// http://www.toao.net/48-replacing-smart-quotes-and-em-dashes-in-mysql
			$text = str_replace(
				array(chr(145), chr(146), chr(147), chr(148), chr(150), chr(151), chr(133)),
				array("'", "'", '"', '"', '-', '--', '...'),
				$text
			);
		}
		
		if($charset && @mb_check_encoding($text, $charset)) {
			if(false !== ($out = mb_convert_encoding($text, LANG_CHARSET_CODE, $charset)))
				return $out;
			
		} else {
			$has_iconv = extension_loaded('iconv') ? true : false;
			
			// If we can use iconv, do so.
			if($has_iconv && $charset && false !== ($out = iconv($charset, LANG_CHARSET_CODE . '//IGNORE//TRANSLIT', $text)))
				return $out;
			
			
			if(false !== ($charset = mb_detect_encoding($text))) {
				if(false !== ($out = mb_convert_encoding($text, LANG_CHARSET_CODE, $charset)))
					return $out;
				
			} else {
				if(false !== ($out = mb_convert_encoding($text, LANG_CHARSET_CODE)))
					return $out;
			}
		}
		
		return $text;
	}
	
	static function fixQuotePrintableString($input, $encoding=null) {
		$out = '';
		
		// Make a single element array from any !array input
		if(!is_array($input))
			$input = array($input);

		if(is_array($input))
		foreach($input as $str) {
			$out .= !empty($out) ? ' ' : '';
			$parts = imap_mime_header_decode($str);
			
			if(is_array($parts))
			foreach($parts as $part) {
				try {
					$charset = ($part->charset != 'default') ? $part->charset : $encoding;

					if(empty($charset))
						$charset = 'auto';

					$out .= self::convertEncoding($part->text, $charset);
					
				} catch(Exception $e) {}
			}
		}

		// Strip invalid characters in our encoding
		if(!mb_check_encoding($out, LANG_CHARSET_CODE))
			$out = mb_convert_encoding($out, LANG_CHARSET_CODE, LANG_CHARSET_CODE);
		
		return $out;
	}
	
};
