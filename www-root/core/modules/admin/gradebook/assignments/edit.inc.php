<?php
/**
 * Entrada [ http://www.entrada-project.org ]
 *
 * Entrada is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Entrada is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Entrada.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Brandon Thorn <brandon.thorn@queensu.ca>
 * @copyright Copyright 2012 Queen's University. All Rights Reserved.
 *
 */

if ((!defined("PARENT_INCLUDED")) || (!defined("IN_GRADEBOOK"))) {
	exit;
} elseif ((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL);
	exit;
} elseif (!$ENTRADA_ACL->amIAllowed("gradebook", "update", false)) {
	$ONLOAD[]	= "setTimeout('window.location=\\'".ENTRADA_URL."/admin/".$MODULE."\\'', 15000)";

	$ERROR++;
	$ERRORSTR[]	= "Your account does not have the permissions required to use this feature of this module.<br /><br />If you believe you are receiving this message in error please contact <a href=\"mailto:".html_encode($AGENT_CONTACTS["administrator"]["email"])."\">".html_encode($AGENT_CONTACTS["administrator"]["name"])."</a> for assistance.";

	echo display_error();

	application_log("error", "Group [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["group"]."] and role [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["role"]."] does not have access to this module [".$MODULE."]");
} else {

	if(isset($_GET["assignment_id"]) && $tmp_id = clean_input($_GET["assignment_id"], array("trim", "int"))){
		$ASSIGNMENT_ID = $tmp_id;
		$query = "SELECT * FROM `assignment_contacts` WHERE `assignment_id` = ".$db->qstr($ASSIGNMENT_ID)." AND `proxy_id` = ".$db->qstr($ENTRADA_USER->getID());
		$IS_CONTACT = $db->GetRow($query);
		if(!$IS_CONTACT){
			$query = "	SELECT a.`course_id`,a.`organisation_id`
						FROM `courses` a
						JOIN `assignments` b
						ON a.`course_id` = b.`course_id`
						WHERE b.`assignment_id` = ".$db->qstr($ASSIGNMENT_ID)."
						AND b.`assignment_active` = '1'";
			$course_details = $db->GetRow($query);
			if($course_details){
				if($ENTRADA_ACL->amIAllowed(new CourseResource($course_details["course_id"], $course_details["organisation_id"]), "update",false)){
					$IS_CONTACT = true;
				}
			}
		}
	}

	if ($COURSE_ID) {
        $PROCESSED = array();
        $PROCESSED_NOTICE = array();
		if ($ASSIGNMENT_ID) {
            $query = "SELECT * FROM `assignments`
                        WHERE `assignment_id` = ".$db->qstr($ASSIGNMENT_ID)."
                        AND `assignment_active` = '1'";
            $assignment_record = $db->GetRow($query);
            if ($assignment_record) {
                $query = "	SELECT *
                            FROM `assessments`
                            WHERE `assessment_id` = ".$db->qstr($assignment_record["assessment_id"])."
                            AND `active` = 1";
                $assessment_details = $db->getRow($query);
                if (isset($assessment_details) && $assessment_details) {
                    if ($assignment_record["notice_id"]) {
                        $query = "SELECT * FROM `notices` WHERE `notice_id` = ".$db->qstr($assignment_record["notice_id"]);
                        $notice_details = $db->GetRow($query);
                        if ($notice_details) {
                            $notice_enabled = true;
                            $PROCESSED_NOTICE["display_from"] = $notice_details["display_from"];
                            $PROCESSED_NOTICE["display_until"] = $notice_details["display_until"];
                            $search = array(
                                ENTRADA_URL . "/profile/gradebook/assignments?section=view&assignment_id=" . $ASSIGNMENT_ID,
                                html_encode($assignment_record["assignment_title"]),
                                html_encode($course_details["course_code"]),
                                html_encode($course_details["course_name"]),
                                ($assignment_record["due_date"] ? date("l, F jS, Y", $assignment_record["due_date"]) : "No due date provided"),
                                (isset($assignment_record["assignment_description"]) && $assignment_record["assignment_description"] ? nl2br(html_encode($assignment_record["assignment_description"])) : "No assignment description provided")
                            );
                            $replace = array(
                                "%assignment_submission_url%",
                                "%assignment_title%",
                                "%course_code%",
                                "%course_name%",
                                "%due_date%",
                                "%assignment_description%"
                            );
                            $PROCESSED_NOTICE["notice_summary"] = str_ireplace($search, $replace, $notice_details["notice_summary"]);
                            if ($PROCESSED_NOTICE["display_from"] == $assignment_record["release_start"] && $PROCESSED_NOTICE["display_until"] == ($assignment_record["release_start"] + 604800)) {
                                $custom_notice_display = false;
                            } else {
                                $custom_notice_display = true;
                            }
                        } else {
                            $notice_enabled = false;
                        }
                    } else {
                        $notice_enabled = false;
                    }

                    if($IS_CONTACT || $ENTRADA_ACL->amIAllowed(new CourseContentResource($course_details["course_id"], $course_details["organisation_id"]), "update")) {
                        $query = "	SELECT * FROM `courses`
                                    WHERE `course_id` = ".$db->qstr($COURSE_ID)."
                                    AND `course_active` = '1'";
                        $course_details	= $db->GetRow($query);
                        if ($course_details && $ENTRADA_ACL->amIAllowed(new GradebookResource($course_details["course_id"], $course_details["organisation_id"]), "update")) {

                            // Error Checking
                            switch($STEP) {
                                case 2 :
                                    if(isset($_POST["assignment_title"]) && $tmp_title = clean_input($_POST["assignment_title"],array("trim","notags"))){
                                        $PROCESSED["assignment_title"] = $tmp_title;
                                    } else {
                                        $ERROR++;
                                        $ERRORSTR[] = "Assignment Title is a required Field.";
                                    }

                                    if(isset($_POST["assignment_description"]) && $tmp_desc = clean_input($_POST["assignment_description"],array("trim","notags"))){
                                        $PROCESSED["assignment_description"] = $tmp_desc;
                                    } else {
                                        $PROCESSED["assignment_description"] = "";
                                    }

                                    if(isset($_POST["assignment_uploads"]) && $tmp_uploads = clean_input($_POST["assignment_uploads"],array("trim","notags"))){
                                        $PROCESSED["assignment_uploads"] = $tmp_uploads == "allow"?0:1;
                                    } else {
                                        $PROCESSED["assignment_uploads"] = 1;
                                    }

                                    if (isset($_POST["notice_enabled"]) && $_POST["notice_enabled"]) {
                                        $notice_enabled = true;
                                        if ((isset($_POST["notice_summary"])) && ($notice_summary = strip_tags(clean_input($_POST["notice_summary"], "trim"), "<a><br><p>"))) {
                                            $PROCESSED_NOTICE["notice_summary"] = $notice_summary;
                                        } else {
                                            add_error("You must provide a notice summary.");
                                        }
                                        if (isset($_POST["custom_notice_display"]) && $_POST["custom_notice_display"]) {
                                            $custom_notice_display = true;
                                            if (isset($_POST["notice_display_start"]) && ($tmp_date = clean_input($_POST["notice_display_start"], array("trim", "notags")))) {
                                                if (isset($_POST["notice_display_start_time"]) && ($tmp_time = clean_input($_POST["notice_display_start_time"], array("trim", "notags")))) {
                                                    $PROCESSED_NOTICE["display_from"] = strtotime($tmp_date . " " . $tmp_time);
                                                    if (!$PROCESSED_NOTICE["display_from"]) {
                                                        add_error("The custom notice display start date you have entered is not valid. Please re-enter the <strong>Notice Release Start</strong> to continue.");
                                                    }
                                                } else {
                                                    add_error("You chose to enter a custom notice display start date, but never entered a time for the <strong>Notice Release Start</strong>. Please enter a time to continue.");
                                                }
                                            } else {
                                                add_error("You chose to enter a custom notice display start date, but never entered a <strong>Notice Release Start</strong>. Please enter a date to continue.");
                                            }
                                            if (isset($_POST["notice_display_finish"]) && ($tmp_date = clean_input($_POST["notice_display_finish"], array("trim", "notags")))) {
                                                if (isset($_POST["notice_display_finish_time"]) && ($tmp_time = clean_input($_POST["notice_display_finish_time"], array("trim", "notags")))) {
                                                    $PROCESSED_NOTICE["display_until"] = strtotime($tmp_date . " " . $tmp_time);
                                                    if (!$PROCESSED_NOTICE["display_until"]) {
                                                        add_error("The custom notice display finish date you have entered is not valid. Please re-enter the <strong>Notice Release Finish</strong> to continue.");
                                                    }
                                                } else {
                                                    add_error("You chose to enter a custom notice display finish date, but never entered a time for the <strong>Notice Release Finish</strong>. Please enter a time to continue.");
                                                }
                                            } else {
                                                add_error("You chose to enter a custom notice display finish date, but never entered a <strong>Notice Release Finish</strong>. Please enter a date to continue.");
                                            }
                                        } else {
                                            $custom_notice_display = false;
                                            $PROCESSED_NOTICE["display_from"] = ($PROCESSED["release_date"] ? $PROCESSED["release_date"] : time());
                                            $PROCESSED_NOTICE["display_until"] = $PROCESSED_NOTICE["display_from"] + 604800; //One week in seconds
                                        }
                                    } else {
                                        $notice_enabled = false;
                                        $PROCESSED["notice_id"] = 0;
                                    }

                                    /**
                                     * Required field "event_start" / Event Date & Time Start (validated through validate_calendars function).
                                     */
                                    $release_date = validate_calendars("viewable", false, false,true);
                                    $due_date = validate_calendars("due", false, false,true);

                                    if ((isset($release_date["start"])) && ((int) $release_date["start"])) {
                                        $PROCESSED["release_date"] = (int) $release_date["start"];
                                    } else {
                                        $PROCESSED["release_date"] = 0;
                                    }

                                    if ((isset($release_date["finish"])) && ((int) $release_date["finish"])) {
                                        $PROCESSED["release_until"] = (int) $release_date["finish"];
                                    } else {
                                        $PROCESSED["release_until"] = 0;
                                    }

                                    if ((isset($due_date["finish"])) && ((int) $due_date["finish"])) {
                                        $PROCESSED["due_date"] = (int) $due_date["finish"];
                                    } else {
                                        $PROCESSED["due_date"] = 0;
                                    }

                                    if (isset($_POST["post_action"])) {
                                        if (@in_array($_POST["post_action"], array("new", "index", "parent", "grade"))) {
                                            $_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = $_POST["post_action"];
                                        } else {
                                            $_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = "index";
                                        }
                                    } else {
                                        $_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = "index";
                                    }
                                    if(!$ERROR){

                                        $PROCESSED["updated_date"]	= time();
                                        $PROCESSED["updated_by"]	= $ENTRADA_USER->getID();
                                        $PROCESSED["course_id"]		= $COURSE_ID;

                                        if ($db->AutoExecute("assignments", $PROCESSED, "UPDATE","`assignment_id` = ".$db->qstr($ASSIGNMENT_ID))) {
                                            if ($notice_enabled) {
                                                $PROCESSED_NOTICE["target"] = "updated";
                                                $PROCESSED_NOTICE["organisation_id"] = $course_details["organisation_id"];
                                                $PROCESSED_NOTICE["updated_date"] = time();
                                                $PROCESSED_NOTICE["updated_by"] = $ENTRADA_USER->getID();

                                                $PROCESSED_NOTICE_AUDIENCE = array();
                                                $search = array(
                                                    "%assignment_submission_url%",
                                                    "%assignment_title%",
                                                    "%course_code%",
                                                    "%course_name%",
                                                    "%due_date%",
                                                    "%assignment_description%"
                                                );
                                                $replace = array(
                                                    ENTRADA_URL . "/profile/gradebook/assignments?section=view&assignment_id=" . $ASSIGNMENT_ID,
                                                    html_encode($PROCESSED["assignment_title"]),
                                                    html_encode($course_details["course_code"]),
                                                    html_encode($course_details["course_name"]),
                                                    ($PROCESSED["due_date"] ? date("l, F jS, Y", $PROCESSED["due_date"]) : "No due date provided"),
                                                    (isset($PROCESSED["assignment_description"]) && $PROCESSED["assignment_description"] ? nl2br(html_encode($PROCESSED["assignment_description"])) : "No assignment description provided")
                                                );
                                                $PROCESSED_NOTICE["notice_summary"] = str_ireplace($search, $replace, $PROCESSED_NOTICE["notice_summary"]);
                                                $query = "SELECT * FROM `groups` WHERE `group_id` = ".$db->qstr($assessment_details["cohort"]);
                                                $assessment_group = $db->GetRow($query);
                                                if ($assessment_group) {
                                                    $PROCESSED_NOTICE_AUDIENCE["audience_type"] = $assessment_group["group_type"];
                                                    $PROCESSED_NOTICE_AUDIENCE["audience_value"] = $assessment_group["group_id"];
                                                    $PROCESSED_NOTICE_AUDIENCE["updated_by"] = $ENTRADA_USER->getID();
                                                    $PROCESSED_NOTICE_AUDIENCE["updated_date"] = time();
                                                }
                                                if ($assignment_record["notice_id"] && $notice_details) {
                                                    if ($db->AutoExecute("notices", $PROCESSED_NOTICE, "UPDATE", "`notice_id` = ".$db->qstr($notice_details["notice_id"]))) {
                                                        $query = "DELETE FROM `notice_audience` WHERE `notice_id` = ".$db->qstr($notice_details["notice_id"]);
                                                        if ($db->Execute($query)) {
                                                            $PROCESSED_NOTICE_AUDIENCE["notice_id"] = $notice_details["notice_id"];
                                                            if (!$db->AutoExecute("notice_audience", $PROCESSED_NOTICE_AUDIENCE, "INSERT")) {
                                                                application_log("error", "An error was encountered while attempting to create a `notice_audience` record for a new assignment [".$ASSIGNMENT_ID."] notice [".$NOTICE_ID."]. DB Said: ".$db->ErrorMsg());
                                                            }
                                                        }
                                                    } else {
                                                        application_log("error", "An error was encountered while attempting to create a `notice` record for a new assignment [".$ASSIGNMENT_ID."]. DB Said: ".$db->ErrorMsg());
                                                    }
                                                } else {
                                                    $PROCESSED_NOTICE["created_by"] = $ENTRADA_USER->getID();
                                                    if ($db->AutoExecute("notices", $PROCESSED_NOTICE, "INSERT") && $NOTICE_ID = $db->Insert_Id()) {
                                                        $query = "UPDATE `assignments` SET `notice_id` = ".$db->qstr($NOTICE_ID)." WHERE `assignment_id` = ".$db->qstr($ASSIGNMENT_ID);
                                                        if (!$db->Execute($query)) {
                                                            application_log("error", "An error was encountered while attempting to set the `notice_id` field for a new assignment [".$ASSIGNMENT_ID."] after creating a notice [".$NOTICE_ID."] for it. DB Said: ".$db->ErrorMsg());
                                                        }
                                                        $PROCESSED_NOTICE_AUDIENCE["notice_id"] = $NOTICE_ID;
                                                        if (!$db->AutoExecute("notice_audience", $PROCESSED_NOTICE_AUDIENCE, "INSERT")) {
                                                            application_log("error", "An error was encountered while attempting to create a `notice_audience` record for a new assignment [".$ASSIGNMENT_ID."] notice [".$NOTICE_ID."]. DB Said: ".$db->ErrorMsg());
                                                        }
                                                    } else {
                                                        application_log("error", "An error was encountered while attempting to create a `notice` record for a new assignment [".$ASSIGNMENT_ID."]. DB Said: ".$db->ErrorMsg());
                                                    }
                                                }
                                            } elseif (isset($notice_details) && $notice_details) {
                                                $query = "DELETE FROM `notice` WHERE `notice_id` = ".$db->qstr($notice_details["notice_id"]);
                                                $db->Execute($query);
                                                $query = "DELETE FROM `notice_audience` WHERE `notice_id` = ".$db->qstr($notice_details["notice_id"]);
                                                $db->Execute($query);
                                            }

                                            $query = "DELETE FROM `assignment_contacts` WHERE `assignment_id` = ".$db->qstr($ASSIGNMENT_ID);
                                            if($db->Execute($query)) {
                                                $PROCESSED["assignment_id"] = $ASSIGNMENT_ID;
                                                $PROCESSED["proxy_id"] = $ENTRADA_USER->getID();
                                                $PROCESSED["contact_order"] = 0;
                                                $PROCESSED["updated_date"]	= time();
                                                $PROCESSED["updated_by"] = $ENTRADA_USER->getID();
                                                if ($db->AutoExecute("assignment_contacts", $PROCESSED, "INSERT")) {
                                                    if ((isset($_POST["associated_director"])) && ($associated_directors = explode(",", $_POST["associated_director"])) && (@is_array($associated_directors)) && (@count($associated_directors))) {
                                                        $order = 0;
                                                        foreach($associated_directors as $proxy_id) {
                                                            if ($proxy_id = clean_input($proxy_id, array("trim", "int"))) {
                                                                if($proxy_id != $ENTRADA_USER->getID()){
                                                                    if (!$db->AutoExecute("assignment_contacts", array("assignment_id" => $ASSIGNMENT_ID, "proxy_id" => $proxy_id, "contact_order" => $order+1, "updated_date"=>time(),"updated_by"=>$ENTRADA_USER->getID()), "INSERT")) {
                                                                        add_error("There was an error when trying to insert a &quot;" . $module_singular_name . " Director&quot; into the system. The system administrator was informed of this error; please try again later.");

                                                                        application_log("error", "Unable to insert a new course_contact to the database when updating an event. Database said: ".$db->ErrorMsg());
                                                                    } else {
                                                                        $order++;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                    application_log("success", "Successfully added assignment ID [".$ASSIGNMENT_ID."]");
                                                } else {
                                                    application_log("error", "Unable to fetch the newly inserted assignment identifier for this assignment.");
                                                }
                                            } else {
                                                application_log("error", "Unable to update assignment contacts.");
                                            }
                                        } else {
                                            echo 'failed';
                                            application_log("error", "Unable to fetch the newly inserted assignment identifier for this assignment.");
                                        }

                                        switch($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"]) {
                                            case "grade" :
                                                $url = ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("step" => false, "section" => "grade", "assignment_id" => $ASSIGNMENT_ID,"id"=>$COURSE_ID));
                                                $msg = "You will now be redirected to the <strong>Grade Assignment</strong> page for \"<strong>".$assessment_details["name"] . "</strong>\"; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
                                            break;
                                            case "new" :
                                                $url = ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("step" => false, "section" => "add","id"=>$COURSE_ID));
                                                $msg = "You will now be redirected to another <strong>Add Assignment</strong> page for the ". $course_details["course_name"] . " gradebook; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
                                            break;
                                            case "parent" :
                                                $url = ENTRADA_URL."/admin/".$MODULE;
                                                $msg = "You will now be redirected to the <strong>Gradebook</strong> index; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
                                            break;
                                            case "index" :
                                            default :
                                                $url = ENTRADA_URL."/admin/gradebook?".replace_query(array("step" => false, "section" => "view", "assignment_id" => false));
                                                $msg = "You will now be redirected to the <strong>assignment index</strong> page for ". $course_details["course_name"] . "; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
                                            break;
                                        }
                                        $SUCCESS++;
                                        $SUCCESSSTR[] 	= $msg;
                                        $ONLOAD[]		= "setTimeout('window.location=\\'".$url."\\'', 5000)";
                                    }
                                    if ($ERROR) {
                                        $STEP = 1;
                                    }
                                break;
                                case 1 :
                                default :
                                    $PROCESSED["assignment_id"] = $assignment_record["assignment_id"];
                                    $PROCESSED["assignment_title"] = $assignment_record["assignment_title"];
                                    $PROCESSED["assignment_description"] = $assignment_record["assignment_description"];
                                    $PROCESSED["assignment_uploads"] = $assignment_record["assignment_uploads"];
                                    $PROCESSED["release_date"] = $assignment_record["release_date"];
                                    $PROCESSED["release_until"] = $assignment_record["release_until"];
                                    $PROCESSED["due_date"] = $assignment_record["due_date"];
                                break;
                            }
                            $BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("section" => "grade", "id" => $COURSE_ID, "assignment_id"=>$PROCESSED["assignment_id"],"step" => false)), "title" => $PROCESSED["assignment_title"]);
                            $BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/".$MODULE."?".replace_query(array("section" => "edit", "id" => $COURSE_ID, "step" => false)), "title" => "Edit Assignment Drop Box");
                            // Display Content
                            switch ($STEP) {
                                case 2 :
                                    if ($SUCCESS) {
                                        echo display_success();
                                    }
                                    if ($NOTICE) {
                                        echo display_notice();
                                    }
                                    if ($ERROR) {
                                        echo display_error();
                                    }
                                    break;
                                case 1 :
                                default :
                                    $assignment_directors = array();
                                    $query	= "	SELECT `".AUTH_DATABASE."`.`user_data`.`id` AS `proxy_id`, CONCAT_WS(', ', `".AUTH_DATABASE."`.`user_data`.`lastname`, `".AUTH_DATABASE."`.`user_data`.`firstname`) AS `fullname`, `".AUTH_DATABASE."`.`organisations`.`organisation_id`
                                                FROM `".AUTH_DATABASE."`.`user_data`
                                                LEFT JOIN `".AUTH_DATABASE."`.`user_access`
                                                ON `".AUTH_DATABASE."`.`user_access`.`user_id` = `".AUTH_DATABASE."`.`user_data`.`id`
                                                LEFT JOIN `".AUTH_DATABASE."`.`organisations`
                                                ON `".AUTH_DATABASE."`.`user_data`.`organisation_id` = `".AUTH_DATABASE."`.`organisations`.`organisation_id`
                                                WHERE (`".AUTH_DATABASE."`.`user_access`.`group` = 'faculty' OR
                                                `".AUTH_DATABASE."`.`user_access`.`group` = 'staff' OR
                                                (`".AUTH_DATABASE."`.`user_access`.`group` = 'resident' AND `".AUTH_DATABASE."`.`user_access`.`role` = 'lecturer')
                                                OR `".AUTH_DATABASE."`.`user_access`.`group` = 'medtech')
                                                AND `".AUTH_DATABASE."`.`user_access`.`app_id` = '".AUTH_APP_ID."'
                                                AND `".AUTH_DATABASE."`.`user_access`.`account_active` = 'true'
                                                AND `".AUTH_DATABASE."`.`user_access`.`organisation_id` = " . $db->qstr($ENTRADA_USER->getActiveOrganisation()) . "
                                                ORDER BY `fullname` ASC";
                                    $results = ((USE_CACHE) ? $db->CacheGetAll(AUTH_CACHE_TIMEOUT, $query) : $db->GetAll($query));
                                    if ($results) {
                                        foreach($results as $result) {
                                            $assignment_directors[$result["proxy_id"]] = array('proxy_id'=>$result["proxy_id"], 'fullname'=>$result["fullname"], 'organisation_id'=>$result['organisation_id']);
                                        }
                                        $DIRECTOR_LIST = $assignment_directors;
                                    }


                                    /**
                                     * Non-required field "associated_faculty" / Associated Faculty (array of proxy ids).
                                     * This is actually accomplished after the event is inserted below.
                                     */
                                    if ((isset($_POST["associated_director"]))) {
                                        $associated_director = explode(',', $_POST["associated_director"]);
                                        foreach($associated_director as $contact_order => $proxy_id) {
                                            if ($proxy_id = clean_input($proxy_id, array("trim", "int"))) {
                                                $chosen_course_directors[(int) $contact_order] = $proxy_id;
                                            }
                                        }
                                    } else {
                                        $query = "SELECT * FROM `assignment_contacts` WHERE `assignment_id` = ".$db->qstr($ASSIGNMENT_ID)." ORDER BY `contact_order` ASC";
                                        $results = $db->GetAll($query);
                                        if ($results) {
                                            foreach ($results as $result) {
                                                $chosen_course_directors[$result["contact_order"]] = $result["proxy_id"];
                                            }
                                        }
                                    }

                                    /**
                                     * Load the rich text editor.
                                     */
                                    load_rte("minimal");
                                    $HEAD[] = "<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/jquery/jquery.timepicker.js\"></script>\n";
                                    ?>
                                    <h1>Edit Assignment Drop Box</h1>
                                    <?php
                                    if ($ERROR) {
                                        echo display_error();
                                    }
                                    ?>
                                        <script type="text/javascript">
                                            jQuery(document).ready(function() {
                                                jQuery('.datepicker').datepicker({
                                                    dateFormat: 'yy-mm-dd'
                                                });
                                                jQuery(".timepicker").timepicker({
                                                    showPeriodLabels: false
                                                });
                                                jQuery('.add-on').on('click', function() {
                                                    if (jQuery(this).siblings('input').is(':enabled')) {
                                                        jQuery(this).siblings('input').focus();
                                                    }
                                                });
                                            });

                                            var sortables = new Array();
                                            function updateOrder(type) {
                                                $('associated_'+type).value = Sortable.sequence(type+'_list');
                                            }

                                            function addItem(type) {
                                                if (($(type+'_id') != null) && ($(type+'_id').value != '') && ($(type+'_'+$(type+'_id').value) == null)) {
                                                    var li = new Element('li', {'class':'community', 'id':type+'_'+$(type+'_id').value, 'style':'cursor: move;'}).update($(type+'_name').value);
                                                    $(type+'_name').value = '';
                                                    li.insert({bottom: '<img src=\"<?php echo ENTRADA_URL; ?>/images/action-delete.gif\" class=\"list-cancel-image\" onclick=\"removeItem(\''+$(type+'_id').value+'\', \''+type+'\')\" />'});
                                                    $(type+'_id').value	= '';
                                                    $(type+'_list').appendChild(li);
                                                    sortables[type] = Sortable.destroy($(type+'_list'));
                                                    Sortable.create(type+'_list', {onUpdate : function(){updateOrder(type);}});
                                                    updateOrder(type);
                                                } else if ($(type+'_'+$(type+'_id').value) != null) {
                                                    alert('Important: Each user may only be added once.');
                                                    $(type+'_id').value = '';
                                                    $(type+'_name').value = '';
                                                    return false;
                                                } else if ($(type+'_name').value != '' && $(type+'_name').value != null) {
                                                    alert('Important: When you see the correct name pop-up in the list as you type, make sure you select the name with your mouse, do not press the Enter button.');
                                                    return false;
                                                } else {
                                                    return false;
                                                }
                                            }

                                            function addItemNoError(type) {
                                                if (($(type+'_id') != null) && ($(type+'_id').value != '') && ($(type+'_'+$(type+'_id').value) == null)) {
                                                    addItem(type);
                                                }
                                            }

                                            function copyItem(type) {
                                                if (($(type+'_name') != null) && ($(type+'_ref') != null)) {
                                                    $(type+'_ref').value = $(type+'_name').value;
                                                }

                                                return true;
                                            }

                                            function checkItem(type) {
                                                if (($(type+'_name') != null) && ($(type+'_ref') != null) && ($(type+'_id') != null)) {
                                                    if ($(type+'_name').value != $(type+'_ref').value) {
                                                        $(type+'_id').value = '';
                                                    }
                                                }

                                                return true;
                                            }

                                            function removeItem(id, type) {
                                                if ($(type+'_'+id)) {
                                                    $(type+'_'+id).remove();
                                                    Sortable.destroy($(type+'_list'));
                                                    Sortable.create(type+'_list', {onUpdate : function (type) {updateOrder(type)}});
                                                    updateOrder(type);
                                                }
                                            }

                                            function selectItem(id, type) {
                                                if ((id != null) && ($(type+'_id') != null)) {
                                                    $(type+'_id').value = id;
                                                }
                                            }

                                            function loadCurriculumPeriods(ctype_id) {
                                                var updater = new Ajax.Updater('curriculum_type_periods', '<?php echo ENTRADA_URL."/api/curriculum_type_periods.api.php"; ?>',{
                                                    method:'post',
                                                    parameters: {
                                                        'ctype_id': ctype_id
                                                    },
                                                    onFailure: function(transport){
                                                        $('curriculum_type_periods').update(new Element('div', {'class':'display-error'}).update('No Periods were found for this Curriculum Category.'));
                                                    }
                                                });
                                            }
                                    </script>
                                    <form action="<?php echo ENTRADA_URL; ?>/admin/gradebook/assignments?<?php echo replace_query(array("step" => 2)); ?>" method="post" class="form-horizontal">
                                        <h2>Drop Box Details</h2>
                                        <div class="control-group">
                                            <label class="control-label form-required">Assignment Name:</label>
                                            <div class="controls">
                                                <input type="text" name="assignment_title" value="<?php echo (isset($PROCESSED["assignment_title"]) && $PROCESSED["assignment_title"] ? $PROCESSED["assignment_title"] : "");?>"/>
                                            </div>
                                        </div>
                                        <div class="control-group">
                                            <label class="control-label form-nrequired">Assignment Contacts:</label>
                                            <div class="controls">
                                                <input type="text" id="director_name" name="fullname" size="30" autocomplete="off" style="width: 203px; vertical-align: middle" onkeyup="checkItem('director')" onblur="addItemNoError('director')" />
                                                <script type="text/javascript">
                                                    $('director_name').observe('keypress', function(event){
                                                        if (event.keyCode == Event.KEY_RETURN) {
                                                            addItem('director');
                                                            Event.stop(event);
                                                        }
                                                    });
                                                </script>
                                                <?php
                                                $ONLOAD[] = "Sortable.create('director_list', {onUpdate : function() {updateOrder('director')}})";
                                                $ONLOAD[] = "$('associated_director').value = Sortable.sequence('director_list')";
                                                ?>
                                                <div class="autocomplete" id="director_name_auto_complete"></div><script type="text/javascript">new Ajax.Autocompleter('director_name', 'director_name_auto_complete', '<?php echo ENTRADA_RELATIVE; ?>/api/personnel.api.php?type=facultyorstaff', {frequency: 0.2, minChars: 2, afterUpdateElement: function (text, li) {selectItem(li.id, 'director'); copyItem('director');}});</script>
                                                <input type="hidden" id="associated_director" name="associated_director" />
                                                <input type="button" class="btn" onclick="addItem('director');" value="Add" style="vertical-align: middle" />
                                                <span class="content-small">(<strong>Example:</strong> <?php echo html_encode($_SESSION["details"]["lastname"].", ".$_SESSION["details"]["firstname"]); ?>)</span>
                                                <span class="content-small"><br><strong>Tip:</strong> You will automatically be added as a contact</span>
                                                <ul id="director_list" class="menu" style="margin-top: 15px">
                                                    <?php
                                                    if (is_array($chosen_course_directors) && count($chosen_course_directors)) {
                                                        foreach ($chosen_course_directors as $director) {
                                                            if ((array_key_exists($director, $DIRECTOR_LIST)) && is_array($DIRECTOR_LIST[$director])) {
                                                                ?>
                                                                <li class="community" id="director_<?php echo $DIRECTOR_LIST[$director]["proxy_id"]; ?>" style="cursor: move;"><?php echo $DIRECTOR_LIST[$director]["fullname"]; ?><img src="<?php echo ENTRADA_URL; ?>/images/action-delete.gif" class="list-cancel-image" onclick="removeItem('<?php echo $DIRECTOR_LIST[$director]["proxy_id"]; ?>', 'director');"/></li>
                                                            <?php
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </ul>
                                                <input type="hidden" id="director_ref" name="director_ref" value="" />
                                                <input type="hidden" id="director_id" name="director_id" value="" />
                                            </div>
                                        </div>
                                        <div class="control-group">
                                            <label class="control-label form-nrequired">Assignment Description:</label>
                                            <div class="controls">
                                                <textarea id="assignment_description" name="assignment_description" style="width: 100%; height: 150px" cols="70" rows="10" class="expandable"><?php echo html_encode(trim(strip_selected_tags($PROCESSED["assignment_description"], array("font")))); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="control-group">
                                            <label class="control-label form-nrequired">Allow Revisions:</label>
                                            <div class="controls">
                                                <table>
                                                    <tbody>
                                                    <tr>
                                                        <td style="vertical-align: top"><input type="radio" name="assignment_uploads" id="assignment_uploads_allow" value="allow" style="vertical-align: middle"<?php echo (!isset($PROCESSED["assignment_uploads"]) || !$PROCESSED["assignment_uploads"] ? " checked=\"checked\"" : ""); ?>></td>
                                                        <td colspan="2" style="padding-bottom: 15px">
                                                            <label for="assignment_uploads_allow" class="radio-group-title">Allow Submission Revision</label>
                                                            <div class="content-small">Allow students to upload a newer version of their assignment after they have already made their submission as long as its still before the due date.</div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="vertical-align: top"><input type="radio" name="assignment_uploads" id="assignment_uploads_deny" value="deny" style="vertical-align: middle;"<?php echo (isset($PROCESSED["assignment_uploads"]) && $PROCESSED["assignment_uploads"] ? " checked=\"checked\"" : ""); ?>></td>
                                                        <td colspan="2" style="padding-bottom: 15px">
                                                            <label for="assignment_uploads_deny" class="radio-group-title">Do Not Allow Submission Revision</label>
                                                            <div class="content-small">Do not allow students to upload a newer version of their assignment after initial upload.</div>
                                                        </td>
                                                    </tr>

                                                    </tbody>
                                                </table>
                                            </div>
                                        </div><div class="control-group">
                                            <label class="control-label form-nrequired" for="assignment_notice">Assignment Notice:</label>
                                            <div class="controls">
                                                <input type="checkbox" name="notice_enabled" value="1" id="assignment_notice" onclick="jQuery('#notice-dates').toggle(this.checked)" <?php echo (isset($notice_enabled) && $notice_enabled ? "checked=\"checked\" " : "" ); ?>/> <label for="assignment_notice" class="pad-above-small content-small">Create a dashboard notice for this assignment</label>
                                            </div>
                                        </div>
                                        <div class="control-group" id="notice-dates"<?php echo (isset($notice_enabled) && $notice_enabled ? "" : " style=\"display: none;\"" ); ?>>

                                            <label for="notice_summary" class="form-required control-label">Notice Summary:</label>
                                            <div class="controls">
                                                <textarea id="notice_summary" name="notice_summary" rows="10"><?php echo (isset($PROCESSED_NOTICE["notice_summary"]) ? html_encode(trim($PROCESSED_NOTICE["notice_summary"])) : $translate->_("assignment_notice")); ?></textarea>
                                            </div>
                                            <div class="content-small controls space-below large">
                                                <strong>Available Variables:</strong> %assignment_submission_url%, %assignment_title%, %course_code%, %course_name%, %due_date%, %assignment_title%, %assignment_description%
                                            </div>
                                            <label class="control-label form-nrequired">Notice Release:</label>
                                            <div class="controls">
                                                <div class="row-fluid">
                                                    <span class="span1">
                                                        <input type="radio" value="0" name="custom_notice_display" id="notice_display_default" onclick="jQuery('#custom_notice_display_date').hide()" <?php echo (!isset($custom_notice_display) || !$custom_notice_display ? "checked=\"checked\" " : ""); ?>/>
                                                    </span>
                                                    <label class="span11" for="notice_display_default">Release notice on <strong>Viewable Start</strong> (immediately if no date set), for one week</label>
                                                </div>
                                                <div class="row-fluid">
                                                    <span class="span1">
                                                        <input type="radio" value="1" name="custom_notice_display" id="custom_notice_display" onclick="jQuery('#custom_notice_display_date').show()" <?php echo (isset($custom_notice_display) && $custom_notice_display ? "checked=\"checked\" " : ""); ?>/>
                                                    </span>
                                                    <label class="span11" for="custom_notice_display">Release notice on a custom defined date for a specified period of time</label>
                                                </div>
                                                <div id="custom_notice_display_date"<?php echo (!isset($custom_notice_display) || !$custom_notice_display ? " style=\"display: none;\"" : "" ); ?>>
                                                    <div class="row-fluid">
                                                        <label class="span3 offset1" for="notice_display_start">Notice Display Start: </label>
                                                        <span class="span8">
                                                            <div class="input-append">
                                                                <input type="text" class="input-small datepicker" value="<?php echo (isset($PROCESSED_NOTICE["display_from"]) && $PROCESSED_NOTICE["display_from"] ? date("Y-m-d", $PROCESSED_NOTICE["display_from"]) : ""); ?>" name="notice_display_start" id="notice_display_start" />
                                                                <span class="add-on pointer"><i class="icon-calendar"></i></span>
                                                            </div>
                                                            <div class="input-append">
                                                                <input type="text" class="input-mini timepicker" value="<?php echo (isset($PROCESSED_NOTICE["display_from"]) && $PROCESSED_NOTICE["display_from"] ? date("H:i", $PROCESSED_NOTICE["display_from"]) : ""); ?>" name="notice_display_start_time" id="notice_display_start_time" />
                                                                <span class="add-on pointer inpage-add-on"><i class="icon-time"></i></span>
                                                            </div>
                                                        </span>
                                                    </div>
                                                    <div class="row-fluid">
                                                        <label class="span3 offset1" for="notice_display_finish">Notice Display Finish: </label>
                                                        <span class="span8">
                                                            <div class="input-append">
                                                                <input type="text" class="input-small datepicker" value="<?php echo (isset($PROCESSED_NOTICE["display_until"]) && $PROCESSED_NOTICE["display_until"] ? date("Y-m-d",  $PROCESSED_NOTICE["display_until"]) : ""); ?>" name="notice_display_finish" id="notice_display_finish" />
                                                                <span class="add-on pointer"><i class="icon-calendar"></i></span>
                                                            </div>
                                                            <div class="input-append">
                                                                <input type="text" class="input-mini timepicker" value="<?php echo (isset($PROCESSED_NOTICE["display_until"]) && $PROCESSED_NOTICE["display_until"] ? date("H:i", $PROCESSED_NOTICE["display_until"]) : ""); ?>" name="notice_display_finish_time" id="notice_display_finish_time" />
                                                                <span class="add-on pointer inpage-add-on"><i class="icon-time"></i></span>
                                                            </div>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="control-group">
                                            <table>
                                                <?php echo generate_calendars("viewable", "", true, false, ((isset($PROCESSED["release_date"])) ? $PROCESSED["release_date"] : 0), true, false, ((isset($PROCESSED["release_until"])) ? $PROCESSED["release_until"] : 0)); ?>
                                                <?php echo generate_calendars("due", "Assignment", false, false, 0, true, false, ((isset($PROCESSED["due_date"])) ? $PROCESSED["due_date"] : 0), true, false, "", " Due Date"); ?>
                                            </table>
                                        </div>
                                        <div style="padding-top: 25px">
                                            <table style="width: 100%" cellspacing="0" cellpadding="0" border="0">
                                                <tr>
                                                    <td style="width: 25%; text-align: left">
                                                        <input type="button" class="btn" value="Cancel" onclick="window.location='<?php echo ENTRADA_URL; ?>/admin/gradebook?<?php echo replace_query(array("step" => false, "section" => "view", "assessment_id" => false)); ?>'" />
                                                    </td>
                                                    <td style="width: 75%; text-align: right; vertical-align: middle">
                                                        <span class="content-small">After saving:</span>
                                                        <select id="post_action" name="post_action">
                                                            <option value="grade"<?php echo (($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] == "grade") ? " selected=\"selected\"" : ""); ?>>Grade assessment</option>
                                                            <option value="new"<?php echo (($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] == "new") ? " selected=\"selected\"" : ""); ?>>Add another assessment</option>
                                                            <option value="index"<?php echo (($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] == "index") ? " selected=\"selected\"" : ""); ?>>Return to assessment list</option>
                                                            <option value="parent"<?php echo (($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] == "parent") ? " selected=\"selected\"" : ""); ?>>Return to all gradebooks list</option>
                                                        </select>
                                                        <input type="submit" class="btn btn-primary" value="Save" />
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </form>
                                    <?php
                                break;
                            }
                        } else {
                            $ERROR++;
                            $ERRORSTR[] = "In order to add an assignment to a gradebook you must provide a valid course identifier. The provided ID does not exist in this system.";

                            echo display_error();

                            application_log("notice", "Failed to provide a valid course identifier when attempting to add an assignment");
                        }
                    } else {
                        $ERROR++;
                        $ERRORSTR[] = "In order to edit an assignment you must be assigned as an 'Assignment Contact'. You do not have access to edit this assignment.";

                        echo display_error();

                        application_log("notice", "Not an Assignment Contact for the specified assignment.");
                    }
                } else {
                    $ERROR++;
                    $ERRORSTR[] = "In order to edit an assignment you must provide a valid assignment identifier. The provided ID does not exist in this system.";

                    echo display_error();

                    application_log("notice", "Failed to provide assignment identifier when attempting to edit an assignment");
                }
            } else {
                $ERROR++;
                $ERRORSTR[] = "In order to edit an assignment you must provide a valid assignment identifier. The provided ID does not exist in this system.";

                echo display_error();

                application_log("notice", "Failed to provide a valid assignment identifier when attempting to edit an assignment");
            }
        } else {
            $ERROR++;
            $ERRORSTR[] = "In order to edit an assignment you must provide a valid assignment identifier.";

            echo display_error();

            application_log("notice", "Failed to provide assignment identifier when attempting to edit an assignment");
        }
	} else {
		$ERROR++;
		$ERRORSTR[] = "In order to edit an assignment you must provide a valid course identifier. The provided ID does not exist in this system.";

		echo display_error();

		application_log("notice", "Failed to provide course identifier when attempting to edit an assignment");
	}
}