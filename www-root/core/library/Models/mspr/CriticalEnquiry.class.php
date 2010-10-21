<?php

require_once("SupervisedProject.class.php");

class CriticalEnquiry extends SupervisedProject {
	/**
	 * 
	 * @param User $user
	 * @return CriticalEnquiry
	 */
	public static function get($id) {
		global $db;
		if ($id instanceof User) {
			$id = $id->getID();
		}
		$query		= "SELECT * FROM `student_critical_enquiries` WHERE `user_id`=?";
		$result = $db->getRow($query, array($id));
		if ($result) {
			$critical_enquiry = self::fromArray($result);
			return $critical_enquiry;
		}
	} 
	
	/**
	 * Creates new project object from array
	 * @param array $arr
	 * @return CriticalEnquiry
	 */
	public static function fromArray(array $arr) {
		$rejected=($arr['status'] == -1);
		$approved = ($arr['status'] == 1);
			
		return new self($arr['user_id'], $arr['title'], $arr['organization'], $arr['location'], $arr['supervisor'], $arr['comment'],$approved, $rejected);
	} 
	

	/**
	 * Creates a new Critical Enquiry entry OR updates if one already exists. This will reset the approval.
	 * @param unknown_type $user
	 * @param unknown_type $title
	 * @param unknown_type $organization
	 * @param unknown_type $location
	 * @param unknown_type $supervisor
	 */
	public static function create($user_id, $title, $organization, $location, $supervisor) {
		
		global $db;
		$query = "insert into `student_critical_enquiries` 
					(`user_id`, `title`, `organization`,`location`,`supervisor`, `status`)
					value 
					(".$db->qstr($user_id).", ".$db->qstr($title).", ".$db->qstr($organization).", ".$db->qstr($location).", ".$db->qstr($supervisor).", ".$db->qstr(0).")
					on duplicate key update 
					`title`=".$db->qstr($title).", `organization`=".$db->qstr($organization).", `location`=".$db->qstr($location).", `supervisor`=".$db->qstr($supervisor).", `status`=".$db->qstr(0);
		if(!$db->Execute($query)) {
			add_error("Failed to update Critical Enquiry entry.");
			application_log("error", "Unable to update a student_critical_enquiries record. Database said: ".$db->ErrorMsg());
		} else {
			add_success("Successfully updated Critical Enquiry entry.");
		}
	}
	
	public function setStatus($status_code, $comment=null) {
		global $db;
		$query = 	"update `student_critical_enquiries` set 
					`status`=?, `comment`=?
					where `user_id`=?";
		if(!$db->Execute($query, array($status_code, $comment, $this->getUserID()))) {
			add_error("Failed to update Critical Enquiry entry.");
			application_log("error", "Unable to update a student_critical_enquiries record. Database said: ".$db->ErrorMsg());
		} else {
			add_success("Successfully updated Critical Enquiry entry.");
		}
	}
	
	public function approve() {
		$this->setStatus(1);
	}
	
	public function unapprove() {
		$this->setStatus(0);
	}
	
	
	public function reject($comment) {
		$this->setStatus(-1, $comment);
	}
	
}