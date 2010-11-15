<?php

/**
 * TODO This class should be expanded to include more than just the comments
 * 
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Jonathan Fingland <jonathan.fingland@quensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 */

require_once("Models/utility/Editable.interface.php");

class ClinicalPerformanceEvaluation implements Editable {
	private $comment;
	private $id;
	private $source;
	private $user_id;
	
	public function getUser() {
		return User::get($this->user_id);
	}
	
	public function getComment() {
		return $this->comment;
	}
	
	public function getSource() {
		return $this->source;
	}
	
	public function getID() {
		return $this->id;
	}
	
	function __construct($user_id, $id, $comment,$source) {
		$this->user_id = $user_id;
		$this->id = $id;
		$this->comment = $comment;
		$this->source = $source;
	}
	
	public static function create($user_id, $comment,$source) {
		global $db;
	
		$query = "insert into `student_clineval_comments` (`user_id`, `comment`,`source`) value (".$db->qstr($user_id).", ".$db->qstr($comment).", ".$db->qstr($source).")";
		if(!$db->Execute($query)) {
			add_error("Failed to create new clinical performance evaluation.");
			application_log("error", "Unable to update a student_clineval_comment record. Database said: ".$db->ErrorMsg());
		} else {
			add_success("Successfully added new clinical performance evaluation.");
			$insert_id = $db->Insert_ID();
			return self::get($insert_id); 
		}
	}
	
	public static function get($id) {
		global $db;
		$query		= "SELECT * FROM `student_clineval_comments` WHERE `id` = ".$db->qstr($id);
		$result = $db->getRow($query);
		if ($result) {
			$clineval =  self::fromArray($result);
			return $clineval;
		}
	}  
	
	public function delete() {
		global $db;
		$query = "DELETE FROM `student_clineval_comments` where `id`=".$db->qstr($this->id);
		if(!$db->Execute($query)) {
			add_error("Failed to remove clinical performance evaluation from database.");
			application_log("error", "Unable to delete a student_clineval_comment record. Database said: ".$db->ErrorMsg());
		} else {
			add_success("Successfully removed clinical performance evaluation.");
		}		
	}
	
	public static function fromArray(array $arr) {
		return new self($arr['user_id'], $arr['id'], $arr['comment'], $arr['source']);	
	}
	
	public function update($comment, $source) {
		global $db;
		$query = "Update `student_clineval_comments` set `comment`=?, `source`=? where `id`=?";
		if(!$db->Execute($query, array($comment, $source, $this->id))) {
			add_error("Failed to update a clinical performance evaluation comment.");
			application_log("error", "Unable to update a student_clineval_comment record. Database said: ".$db->ErrorMsg());
		} else {
			add_success("Successfully updated clinical performance evaluation.");
		}
	}
}