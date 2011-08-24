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
 * This file is used to add events to the entrada.events table.
 *
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Matt Simpson <matt.simpson@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 *
*/

if ((!defined("PARENT_INCLUDED")) || (!defined("IN_EVENTS"))) {
	exit;
} elseif ((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL);
	exit;
} elseif (!$ENTRADA_ACL->amIAllowed("event", "create", false)) {

	add_error("Your account does not have the permissions required to use this feature of this module.<br /><br />If you believe you are receiving this message in error please contact <a href=\"mailto:".html_encode($AGENT_CONTACTS["administrator"]["email"])."\">".html_encode($AGENT_CONTACTS["administrator"]["name"])."</a> for assistance.");

	echo display_error();

	application_log("error", "Group [".$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["group"]."] and role [".$_SESSION["permissions"][$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["proxy_id"]]["role"]."] does not have access to this module [".$MODULE."]");
} else {
	$HEAD[] = "<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/eventtypes_list.js?release=".html_encode(APPLICATION_VERSION)."\"></script>";
	$HEAD[] = "<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/AutoCompleteList.js?release=".html_encode(APPLICATION_VERSION)."\"></script>";	
	$HEAD[] = "<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/growler/src/Growler.js?release=".html_encode(APPLICATION_VERSION)."\"></script>";
	echo "<script language=\"text/javascript\">var DELETE_IMAGE_URL = '".ENTRADA_URL."/images/action-delete.gif';</script>";
	
	$BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/events?".replace_query(array("section" => "add")), "title" => "Adding Event");

	$PROCESSED["associated_faculty"] = array();
	$PROCESSED["event_audience_type"] = "course";
	$PROCESSED["associated_grad_year"] = "";
	$PROCESSED["associated_group_ids"] = array();
	$PROCESSED["associated_proxy_ids"] = array();
	$PROCESSED["event_types"] = array();

	echo "<h1>Adding Event</h1>\n";
	
	// Error Checking
	switch($STEP) {
		case 2 :
			/**
			 * Required field "event_title" / Event Title.
			 */
			if ((isset($_POST["event_title"])) && ($event_title = clean_input($_POST["event_title"], array("notags", "trim")))) {
				$PROCESSED["event_title"] = $event_title;
			} else {
				add_error("The <strong>Event Title</strong> field is required.");
			}

			/**
			 * Non-required field "associated_faculty" / Associated Faculty (array of proxy ids).
			 * This is actually accomplished after the event is inserted below.
			 */	
			
			if ((isset($_POST["associated_faculty"]))) {
				$associated_faculty = explode(",", $_POST["associated_faculty"]);
				foreach($associated_faculty as $contact_order => $proxy_id) {
					if ($proxy_id = clean_input($proxy_id, array("trim", "int"))) {
						$PROCESSED["associated_faculty"][(int) $contact_order] = $proxy_id;
						$PROCESSED["contact_role"][(int) $contact_order] = $_POST["faculty_role"][(int)$contact_order];
						$PROCESSED["display_role"][$proxy_id] = $_POST["faculty_role"][(int) $contact_order];	
					}
				}
			}

			
			/**
			 * Non-required field "associated_faculty" / Associated Faculty (array of proxy ids).
			 * This is actually accomplished after the event is inserted below.
			 */
			if (isset($_POST["event_audience_type"])) {
				$PROCESSED["event_audience_type"] = clean_input($_POST["event_audience_type"], array("page_url"));

				switch($PROCESSED["event_audience_type"]) {
					case "course" :
						/**
						 * Required field "course" / Course
						 * This data is inserted into the event_audience table as course.
						 */
					break;					
					case "group_id" :
						add_error("The <strong>Group Event</strong> as an <strong>Event Audience</strong> type, has not yet been implemented.");
					break;
					case "proxy_id" :
						/**
						 * Required field "associated_proxy_ids" / Associated Students
						 * This data is inserted into the event_audience table as proxy_id.
						 */
						if ((isset($_POST["associated_student"]))) {
							$associated_proxies = explode(",", $_POST["associated_student"]);
							if ((isset($associated_proxies)) && (is_array($associated_proxies)) && (count($associated_proxies))) {
								foreach($associated_proxies as $proxy_id) {
									if ($proxy_id = clean_input($proxy_id, array("trim", "int"))) {
										$query = "	SELECT a.*
													FROM `".AUTH_DATABASE."`.`user_data` AS a
													LEFT JOIN `".AUTH_DATABASE."`.`user_access` AS b
													ON a.`id` = b.`user_id`
													WHERE a.`id` = ".$db->qstr($proxy_id)."
													AND b.`app_id` = ".$db->qstr(AUTH_APP_ID)."
													AND b.`account_active` = 'true'
													AND (b.`access_starts` = '0' OR b.`access_starts` <= ".$db->qstr(time()).")
													AND (b.`access_expires` = '0' OR b.`access_expires` > ".$db->qstr(time()).")";
										$result	= $db->GetRow($query);
										if ($result) {
											$PROCESSED["associated_proxy_ids"][] = $proxy_id;
										}
									}
								}
								if (!count($PROCESSED["associated_proxy_ids"])) {
									add_error("You have chosen <strong>Individual Student Event</strong> as an <strong>Event Audience</strong> type, but have not selected any individuals.");
								}
							} else {
								add_error("You have chosen <strong>Individual Student Event</strong> as an <strong>Event Audience</strong> type, but have not selected any individuals.");
							}
						}
					break;
					default :
						add_error("Unable to proceed because the <strong>Event Audience</strong> type is unrecognized.");

						application_log("error", "Unrecognized event_audience_type [".$_POST["event_audience_type"]."] encountered.");
					break;
				}
			} else {
				add_error("Unable to proceed because the <strong>Event Audience</strong> type is unrecognized.");

				application_log("error", "The event_audience_type field has not been set.");
			}

			/**
			 * Required field "event_start" / Event Date & Time Start (validated through validate_calendars function).
			 */
			$start_date = validate_calendars("event", true, false);
			if ((isset($start_date["start"])) && ((int) $start_date["start"])) {
				$PROCESSED["event_start"] = (int) $start_date["start"];
			}


			/**
			 * Required fields "eventtype_id" / Event Type
			 */
			if (isset($_POST["eventtype_duration_order"])) {
				$event_types = explode(",", trim($_POST["eventtype_duration_order"]));
				$eventtype_durations = $_POST["duration_segment"];

				if ((is_array($event_types)) && (count($event_types))) {
					foreach($event_types as $order => $eventtype_id) {
						if (($eventtype_id = clean_input($eventtype_id, array("trim", "int"))) && ($duration = clean_input($eventtype_durations[$order], array("trim", "int")))) {
							if (!($duration >= 30)) {
								add_error("Event type <strong>durations</strong> may not be less than 30 minutes.");
							}

							$query = "SELECT `eventtype_title` FROM `events_lu_eventtypes` WHERE `eventtype_id` = ".$db->qstr($eventtype_id);
							$result	= $db->GetRow($query);
							if ($result) {
								$PROCESSED["event_types"][] = array($eventtype_id, $duration, $result["eventtype_title"]);
							} else {
								add_error("One of the <strong>event types</strong> you specified was invalid.");
							}
						} else {
							add_error("One of the <strong>event types</strong> you specified is invalid.");
						}
					}
				}
			} else {
				add_error("The <strong>Event Types</strong> field is required.");
			}

			/**
			 * Non-required field "event_location" / Event Location
			 */
			if ((isset($_POST["event_location"])) && ($event_location = clean_input($_POST["event_location"], array("notags", "trim")))) {
				$PROCESSED["event_location"] = $event_location;
			} else {
				$PROCESSED["event_location"] = "";
			}

			/**
			 * Required field "course_id" / Course
			 */
			if ((isset($_POST["course_id"])) && ($course_id = clean_input($_POST["course_id"], array("int")))) {
				$query	= "	SELECT * FROM `courses` 
							WHERE `course_id` = ".$db->qstr($course_id)."
							AND `course_active` = '1'";
				$result	= $db->GetRow($query);
				if ($result) {
					if ($ENTRADA_ACL->amIAllowed(new EventResource(null, $course_id, $ENTRADA_USER->getActiveOrganisation()), "create")) {
						$PROCESSED["course_id"] = $course_id;
					} else {
						add_error("You do not have permission to add an event for the course you selected. <br /><br />Please re-select the course you would like to place this event into.");
						application_log("error", "A program coordinator attempted to add an event to a course [".$course_id."] they were not the coordinator of.");
					}
				} else {
					add_error("The <strong>Course</strong> you selected does not exist.");
				}
			} else {
				add_error("The <strong>Course</strong> field is a required field.");
			}

			/**
			 * Non-required field "release_date" / Viewable Start (validated through validate_calendars function).
			 * Non-required field "release_until" / Viewable Finish (validated through validate_calendars function).
			 */
			$viewable_date = validate_calendars("viewable", false, false);
			if ((isset($viewable_date["start"])) && ((int) $viewable_date["start"])) {
				$PROCESSED["release_date"] = (int) $viewable_date["start"];
			} else {
				$PROCESSED["release_date"] = 0;
			}
			if ((isset($viewable_date["finish"])) && ((int) $viewable_date["finish"])) {
				$PROCESSED["release_until"] = (int) $viewable_date["finish"];
			} else {
				$PROCESSED["release_until"] = 0;
			}

			if (isset($_POST["post_action"])) {
				switch($_POST["post_action"]) {
					case "content" :
						$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = "content";
					break;
					case "new" :
						$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = "new";
					break;
					case "index" :
					default :
						$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = "index";
					break;
				}
			} else {
				$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] = "content";
			}

			if (!$ERROR) {
				$PROCESSED["updated_date"]	= time();
				$PROCESSED["updated_by"]	= $_SESSION["details"]["id"];

				$PROCESSED["event_finish"] = $PROCESSED["event_start"];
				$PROCESSED["event_duration"] = 0;
				foreach($PROCESSED["event_types"] as $event_type) {
					$PROCESSED["event_finish"] += $event_type[1]*60;
					$PROCESSED["event_duration"] += $event_type[1];
				}

				$PROCESSED["eventtype_id"] = $PROCESSED["event_types"][0][0];
				
				if ($db->AutoExecute("events", $PROCESSED, "INSERT")) {
					if ($EVENT_ID = $db->Insert_Id()) {

						foreach($PROCESSED["event_types"] as $event_type) {
							if (!$db->AutoExecute("event_eventtypes", array("event_id" => $EVENT_ID, "eventtype_id" => $event_type[0], "duration" => $event_type[1]), "INSERT")) {
								add_error("There was an error while trying to save the selected <strong>Event Type</strong> for this event.<br /><br />The system administrator was informed of this error; please try again later.");

								application_log("error", "Unable to insert a new event_eventtype record while adding a new event. Database said: ".$db->ErrorMsg());
							}
						}
						
						switch($PROCESSED["event_audience_type"]) {
							case "course" :	// Audience is limited to the enrollment of the selected course.
								/**
								 * If the audience is to be grabbed via the course enrollment
								 * add it to the event_audience table.
								 */
								if (!$db->AutoExecute("event_audience", array("event_id" => $EVENT_ID, "audience_type" => "course_id", "audience_value" => $PROCESSED["course_id"], "updated_date" => time(), "updated_by" => $_SESSION["details"]["id"]), "INSERT")) {
									add_error("There was an error while trying to attach the selected <strong>Course Audience</strong> to this event.<br /><br />The system administrator was informed of this error; please try again later.");

									application_log("error", "Unable to insert a new event_audience record while adding a new event. Database said: ".$db->ErrorMsg());
								}
							break;
							case "group_id" :
							break;
							case "proxy_id" :
								/**
								 * If there are proxy_ids associated with this event,
								 * add them to the event_audience table.
								 */
								if (count($PROCESSED["associated_proxy_ids"])) {
									foreach($PROCESSED["associated_proxy_ids"] as $proxy_id) {
										if (!$db->AutoExecute("event_audience", array("event_id" => $EVENT_ID, "audience_type" => "proxy_id", "audience_value" => (int) $proxy_id, "updated_date" => time(), "updated_by" => $_SESSION["details"]["id"]), "INSERT")) {
											add_error("There was an error while trying to attach the selected <strong>Proxy ID</strong> to this event.<br /><br />The system administrator was informed of this error; please try again later.");

											application_log("error", "Unable to insert a new event_audience, proxy_id record while adding a new event. Database said: ".$db->ErrorMsg());
										}
									}
								}
							break;
							default :
								application_log("error", "Unrecognized event_audience_type [".$_POST["event_audience_type"]."] encountered, no audience added for event_id [".$EVENT_ID."].");
							break;
						}

						/**
						 * If there are faculty associated with this event, add them
						 * to the event_contacts table.
						 */
						if ((is_array($PROCESSED["associated_faculty"])) && (count($PROCESSED["associated_faculty"]))) {
							foreach($PROCESSED["associated_faculty"] as $contact_order => $proxy_id) {
								if (!$db->AutoExecute("event_contacts", array("event_id" => $EVENT_ID, "proxy_id" => $proxy_id, "contact_role"=>$PROCESSED["contact_role"][$contact_order],"contact_order" => (int) $contact_order, "updated_date" => time(), "updated_by" => $_SESSION["details"]["id"]), "INSERT")) {
									add_error("There was an error while trying to attach an <strong>Associated Faculty</strong> to this event.<br /><br />The system administrator was informed of this error; please try again later.");

									application_log("error", "Unable to insert a new event_contact record while adding a new event. Database said: ".$db->ErrorMsg());
								}
							}
						}

						switch($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"]) {
							case "content" :
								$url	= ENTRADA_URL."/admin/events?section=manage&id=".$EVENT_ID;
								$msg	= "You will now be redirected to the event content page; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
							break;
							case "new" :
								$url	= ENTRADA_URL."/admin/events?section=add";
								$msg	= "You will now be redirected to add another new event; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
							break;
							case "index" :
							default :
								$url	= ENTRADA_URL."/admin/events";
								$msg	= "You will now be redirected to the event index; this will happen <strong>automatically</strong> in 5 seconds or <a href=\"".$url."\" style=\"font-weight: bold\">click here</a> to continue.";
							break;
						}

						$query = "	SELECT b.* FROM `community_courses` AS a 
									LEFT JOIN `community_pages` AS b 
									ON a.`community_id` = b.`community_id` 
									LEFT JOIN `community_page_options` AS c 
									ON b.`community_id` = c.`community_id` 
									WHERE c.`option_title` = 'show_history' 
									AND c.`option_value` = 1 
									AND b.`page_url` = 'course_calendar' 
									AND b.`page_active` = 1 
									AND a.`course_id` = ".$PROCESSED["course_id"];
						$result = $db->GetRow($query);
						
						if($result){
							$COMMUNITY_ID = $result["community_id"];
							$PAGE_ID = $result["cpage_id"];
							communities_log_history($COMMUNITY_ID, $PAGE_ID, $EVENT_ID, "community_history_add_learning_event", 1);
						}
						
						
						
						$SUCCESS++;
						$SUCCESSSTR[] = "You have successfully added <strong>".html_encode($PROCESSED["event_title"])."</strong> to the system.<br /><br />".$msg;
						$ONLOAD[] = "setTimeout('window.location=\\'".$url."\\'', 5000)";

						application_log("success", "New event [".$EVENT_ID."] added to the system.");
					}

				} else {
					add_error("There was a problem inserting this event into the system. The system administrator was informed of this error; please try again later.");

					application_log("error", "There was an error inserting a event. Database said: ".$db->ErrorMsg());
				}
			}

			if ($ERROR) {
				$STEP = 1;
			}
		break;
		case 1 :
		default :
			continue;
		break;
	}

	// Display Content
	switch($STEP) {
		case 2 :
			display_status_messages();
		break;
		case 1 :
		default :
			$HEAD[] = "<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/elementresizer.js\"></script>\n";
//			$ONLOAD[] = "selectEventAudienceOption('".$PROCESSED["event_audience_type"]."')";

			/**
			 * Compiles the full list of faculty members.
			 */
			$FACULTY_LIST = array();
			$query = "	SELECT a.`id` AS `proxy_id`, CONCAT_WS(', ', a.`lastname`, a.`firstname`) AS `fullname`, a.`organisation_id`
						FROM `".AUTH_DATABASE."`.`user_data` AS a
						LEFT JOIN `".AUTH_DATABASE."`.`user_access` AS b
						ON b.`user_id` = a.`id`
						WHERE b.`app_id` = '".AUTH_APP_ID."'
						AND (b.`group` = 'faculty' OR (b.`group` = 'resident' AND b.`role` = 'lecturer'))
						ORDER BY a.`lastname` ASC, a.`firstname` ASC";
			$results = $db->GetAll($query);
			if ($results) {
				foreach($results as $result) {
					$FACULTY_LIST[$result["proxy_id"]] = array('proxy_id'=>$result["proxy_id"], 'fullname'=>$result["fullname"], 'organisation_id'=>$result['organisation_id']);
				}
			}

			/**
			 * Compiles the list of students.
			 */
			$STUDENT_LIST = array();
			$query = "	SELECT a.`id` AS `proxy_id`, b.`role`, CONCAT_WS(', ', a.`lastname`, a.`firstname`) AS `fullname`, a.`organisation_id`
						FROM `".AUTH_DATABASE."`.`user_data` AS a
						LEFT JOIN `".AUTH_DATABASE."`.`user_access` AS b
						ON a.`id` = b.`user_id`
						WHERE b.`app_id` = ".$db->qstr(AUTH_APP_ID)."
						AND b.`account_active` = 'true'
						AND (b.`access_starts` = '0' OR b.`access_starts` <= ".$db->qstr(time()).")
						AND (b.`access_expires` = '0' OR b.`access_expires` > ".$db->qstr(time()).")
						AND b.`group` = 'student'
						AND b.`role` >= '".(date("Y") - ((date("m") < 7) ?  2 : 1))."'
						ORDER BY b.`role` ASC, a.`lastname` ASC, a.`firstname` ASC";
			$results = $db->GetAll($query);
			if ($results) {
				foreach($results as $result) {
					$STUDENT_LIST[$result["proxy_id"]] = array('proxy_id'=>$result["proxy_id"], 'fullname'=>$result["fullname"], 'organisation_id'=>$result['organisation_id']);
				}
			}

			if ($ERROR) {
				echo display_error();
			}

			$query = "SELECT `organisation_id`, `organisation_title` FROM `".AUTH_DATABASE."`.`organisations` ORDER BY `organisation_title` ASC";
			$organisation_results = $db->GetAll($query);
			if ($organisation_results) {
				$organisations = array();
				foreach ($organisation_results as $result) {
					if ($ENTRADA_ACL->amIAllowed('resourceorganisation'.$result["organisation_id"], 'create')) {
						$organisation_categories[$result["organisation_id"]] = array('text' => $result["organisation_title"], 'value' => 'organisation_'.$result["organisation_id"], 'category'=>true);
					}
				}
			}
			?>

			<form action="<?php echo ENTRADA_URL; ?>/admin/events?section=add&amp;step=2" method="post" id="addEventForm">
				<table style="width: 100%" cellspacing="0" cellpadding="2" border="0" summary="Adding Event">
					<colgroup>
						<col style="width: 3%" />
						<col style="width: 20%" />
						<col style="width: 77%" />
					</colgroup>
					<tr>
						<td></td>
						<td><label for="course_id" class="form-required">Select Course</label></td>
						<td>
							<?php
							$query = "	SELECT `course_id`, `course_name`, `course_code`, `course_active`
										FROM `courses` 
										WHERE `organisation_id` = " . $db->qstr($ENTRADA_USER->getActiveOrganisation()) . "
										ORDER BY `course_code`, `course_name` ASC";
							$results = $db->GetAll($query);
							if ($results) {
								?>
								<select id="course_id" name="course_id" style="width: 96%">
									<option value="0">-- Select the course this event belongs to --</option>
									<?php
									foreach($results as $result) {
										if ($ENTRADA_ACL->amIAllowed(new EventResource(null, $result["course_id"], $ENTRADA_USER->getActiveOrganisation()), "create")) {
											echo "<option value=\"".(int) $result["course_id"]."\"".(($PROCESSED["course_id"] == $result["course_id"]) ? " selected=\"selected\"" : "").">".html_encode(($result["course_code"] ? $result["course_code"].": " : "").$result["course_name"])."</option>\n";
										}
									}
									?>
								</select>
								<script type="text/javascript">
									jQuery('#course_id').change(function() {
										var course_id = jQuery('#course_id option:selected').val();
										
										if (course_id) {
											jQuery('#course_id_path').load('<?php echo ENTRADA_RELATIVE; ?>/admin/events?section=api-course-path&id=' + course_id);
										}
									});
								</script>
								<?php
							} else {
								echo display_error("You do not have any courses availabe in the system at this time, please add a course prior to adding learning events.");
							}
							?>
						</td>
					</tr>
					<tr>
						<td colspan="3">&nbsp;</td>
					</tr>					
					<tr>
						<td></td>
						<td style="vertical-align: top"><label for="event_title" class="form-required">Event Title</label></td>
						<td>
							<div id="course_id_path" class="content-small"><?php echo fetch_course_path($PROCESSED["course_id"]); ?></div>
							<input type="text" id="event_title" name="event_title" value="<?php echo html_encode($PROCESSED["event_title"]); ?>" maxlength="255" style="width: 95%; font-size: 150%; padding: 3px" />
						</td>
					</tr>
					<tr>
						<td colspan="3">&nbsp;</td>
					</tr>
					<?php echo generate_calendars("event", "Event Date & Time", true, true, ((isset($PROCESSED["event_start"])) ? $PROCESSED["event_start"] : 0)); ?>
					
					<tr>
						<td></td>
						<td><label for="event_location" class="form-nrequired">Event Location</label></td>
						<td><input type="text" id="event_location" name="event_location" value="<?php echo $PROCESSED["event_location"]; ?>" maxlength="255" style="width: 203px" /></td>
					</tr>
					<tr>
						<td colspan="3">&nbsp;</td>
					</tr>
					<tr>
						<td></td>
						<td style="vertical-align: top"><label for="eventtype_ids" class="form-required">Event Types</label></td>
						<td>
							<?php
							$query = "	SELECT a.* FROM `events_lu_eventtypes` AS a 
										LEFT JOIN `eventtype_organisation` AS c 
										ON a.`eventtype_id` = c.`eventtype_id` 
										LEFT JOIN `".AUTH_DATABASE."`.`organisations` AS b
										ON b.`organisation_id` = c.`organisation_id` 
										WHERE b.`organisation_id` = ".$db->qstr($ENTRADA_USER->getActiveOrganisation())."
										AND a.`eventtype_active` = '1' 
										ORDER BY a.`eventtype_order` ASC";
							$results = $db->GetAll($query);
							if ($results) {
								?>
								<select id="eventtype_ids" name="eventtype_ids" style="width: 270px">
									<option value="-1">-- Select each event segment --</option>
									<?php
									$event_types = array();
									foreach($results as $result) {
										$title = html_encode($result["eventtype_title"]);
										echo "<option value=\"".$result["eventtype_id"]."\">".$title."</option>";
									}
									?>
								</select>
								<?php
							} else {
								echo display_error("No Event Types were found for this Organisation. You will need to add at least one Event Type before continuing.");						
							}
							?>
							<div id="duration_notice" class="content-small"><div style="margin: 5px 0 5px 0"><strong>Note:</strong> Select all of the different segments taking place within this learning event. When you select an event type it will appear below, and allow you to change the order and duration of each segment.</div></div>
							<hr />
							<ol id="duration_container" class="sortableList" style="display: none;">
								<?php
								foreach($PROCESSED["event_types"] as $eventtype) {
									echo "<li id=\"type_".$eventtype[0]."\" class=\"\">".$eventtype[2]."
										<a href=\"#\" onclick=\"$(this).up().remove(); cleanupList(); return false;\" class=\"remove\">
											<img src=\"".ENTRADA_URL."/images/action-delete.gif\">
										</a>
										<span class=\"duration_segment_container\">
											Duration: <input class=\"duration_segment\" name=\"duration_segment[]\" onchange=\"cleanupList();\" value=\"".$eventtype[1]."\"> minutes
										</span>
									</li>";
								}
								?>
							</ol>
							<div id="total_duration" class="content-small">Total time: 0 minutes.</div>
							<input id="eventtype_duration_order" name="eventtype_duration_order" style="display: none;">
						</td>
					</tr>
					<tr>
						<td colspan="3">&nbsp;</td>
					</tr>
					<tr>
						<td></td>
						<td style="vertical-align: top"><label for="faculty_name" class="form-nrequired">Associated Faculty</label></td>
						<td>
							<input type="text" id="faculty_name" name="fullname" size="30" autocomplete="off" style="width: 203px; vertical-align: middle" />
							<?php
							$ONLOAD[] = "faculty_list = new AutoCompleteList({ type: 'faculty', url: '". ENTRADA_RELATIVE ."/api/personnel.api.php?type=faculty', remove_image: '". ENTRADA_RELATIVE ."/images/action-delete.gif'})";
							?>
							<div class="autocomplete" id="faculty_name_auto_complete"></div>
							<input type="hidden" id="associated_faculty" name="associated_faculty" />
							<input type="button" class="button-sm" id="add_associated_faculty" value="Add" style="vertical-align: middle" />
							<span class="content-small">(<strong>Example:</strong> <?php echo html_encode($_SESSION["details"]["lastname"].", ".$_SESSION["details"]["firstname"]); ?>)</span>
							<ul id="faculty_list" class="menu" style="margin-top: 15px">
								<?php
								if (is_array($PROCESSED["associated_faculty"]) && count($PROCESSED["associated_faculty"])) {
									foreach ($PROCESSED["associated_faculty"] as $faculty) {
										if ((array_key_exists($faculty, $FACULTY_LIST)) && is_array($FACULTY_LIST[$faculty])) {
											?>
											<li class="community" id="faculty_<?php echo $FACULTY_LIST[$faculty]["proxy_id"]; ?>" style="cursor: move;margin-bottom:10px;width:350px;"><?php echo $FACULTY_LIST[$faculty]["fullname"]; ?><select name ="faculty_role[]" style="float:right;margin-right:30px;margin-top:-5px;"><option value="teacher" <?php if($PROCESSED["display_role"][$faculty] == "teacher") echo "SELECTED";?>>Teacher</option><option value="tutor" <?php if($PROCESSED["display_role"][$faculty] == "tutor") echo "SELECTED";?>>Tutor</option><option value="ta" <?php if($PROCESSED["display_role"][$faculty] == "ta") echo "SELECTED";?>>Teacher's Assistant</option><option value="auditor" <?php if($PROCESSED["display_role"][$faculty] == "auditor") echo "SELECTED";?>>Auditor</option></select><img src="<?php echo ENTRADA_URL; ?>/images/action-delete.gif" onclick="faculty_list.removeItem('<?php echo $FACULTY_LIST[$faculty]["proxy_id"]; ?>');" class="list-cancel-image" /></li>
											<?php
										}
									}
								}
								?>
							</ul>
							<input type="hidden" id="faculty_ref" name="faculty_ref" value="" />
							<input type="hidden" id="faculty_id" name="faculty_id" value="" />
						</td>
					</tr>

					
					<tr>
						<td></td>
						<td style="vertical-align: top"><label for="faculty_name" class="form-nrequired">Associated Learners</label></td>
						<td>
							<table>
								<tbody>
									<tr>
										<td style="vertical-align: top"><input type="radio" name="event_audience_type" id="event_audience_type_course" value="course" onclick="selectEventAudienceOption('course')" style="vertical-align: middle"<?php echo (($PROCESSED["event_audience_type"] == "course") ? " checked=\"checked\"" : ""); ?> /></td>
										<td colspan="2" style="padding-bottom: 15px">
											<label for="event_audience_type_course" class="radio-group-title">All Enrolled Learners</label>
											<div class="content-small">This event is intended for all learners enrolled in the course.</div>
										</td>
									</tr>
									<tr>
										<td style="vertical-align: top"><input type="radio" name="event_audience_type" id="event_audience_type_custom" value="custom" onclick="selectEventAudienceOption('course')" style="vertical-align: middle"<?php echo (($PROCESSED["event_audience_type"] == "custom") ? " checked=\"checked\"" : ""); ?> /></td>
										<td colspan="2" style="padding-bottom: 15px">
											<label for="event_audience_type_custom" class="radio-group-title">A Custom Audience</label>
											<div class="content-small">This event is intended for a custom selection of learners.</div>
										</td>
									</tr>
								</tbody>
								<tbody id="event_audience_type_custom_options">
									<tr>
										<td></td>
										<td></td>
										<td>
											<select id="audience_type" onchange="showMultiSelect();" style="width: 275px;">
												<option value="">-- Select an audience type --</option>
												<option value="cohorts">Entire Classes of Learners</option>
												<option value="course_groups">Small Groups of Learners</option>
												<option value="students">Individual Learners</option>
											</select>
											<span id="options_loading" style="display:none; vertical-align: middle"><img src="<?php echo ENTRADA_RELATIVE; ?>/images/indicator.gif" width="16" height="16" alt="Please Wait" title="" style="vertical-align: middle" /> Loading ... </span>
											<span id="options_container"></span>

<input type="hidden" name="action" value="filter_edit" />
<input type="hidden" id="filter_edit_type" name="filter_type" value="" />
<input type="hidden" id="multifilter" name="filter" value="" />

											<script type="text/javascript">
											var multiselect = [];
											var id;
											function showMultiSelect() {
												$$('select_multiple_container').invoke('hide');
												id = $F('audience_type');

												if (multiselect[id]) {
													multiselect[id].container.show();
												} else {
													new Ajax.Request('<?php echo ENTRADA_RELATIVE; ?>/admin/events?section=api-audience-selector', {
														evalScripts : true,
														parameters: {options_for: id},
														method: 'GET',
														onLoading: function() {
															$('options_loading').show();
														},
														onSuccess: function(response) {
															$('options_container').insert(response.responseText);
															
															if ($(id + '_options')) {
																$('filter_edit_type').value = id;
																$(id + '_options').addClassName('multiselect-processed');

																multiselect[id] = new Control.SelectMultiple('multifilter', id + '_options', {
																	checkboxSelector: 'table.select_multiple_table tr td input[type=checkbox]',
																	nameSelector: 'table.select_multiple_table tr td.select_multiple_name label',
																	filter: id + '_select_filter',
																	resize: id + '_scroll',
																	afterCheck: function(element) {
																		var tr = $(element.parentNode.parentNode);
																		tr.removeClassName('selected');
																		if (element.checked) {
																			tr.addClassName('selected');
																		}
																	}
																});

																$(id + '_cancel').observe('click',function(event){
																	this.container.hide();
																	$('audience_type').options.selectedIndex = 0;
																	$('audience_type').show();
																	return false;
																}.bindAsEventListener(multiselect[id]));

																$(id + '_close').observe('click',function(event){
																	this.container.hide();
//																	$('filter_edit').submit();
																	return false;
																}.bindAsEventListener(multiselect[id]));

																multiselect[id].container.show();
															}
														},
														onError: function() {
															alert("There was an error retrieving the requested audience. Please try again.");
														},
														onComplete: function() {
															$('options_loading').hide();
														}
													});
												}	
												return false;
											}
											</script>											
										</td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
					<tr>
						<td colspan="3">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="3"><h2>Time Release Options</h2></td>
					</tr>
					<?php echo generate_calendars("viewable", "", true, false, ((isset($PROCESSED["release_date"])) ? $PROCESSED["release_date"] : 0), true, false, ((isset($PROCESSED["release_until"])) ? $PROCESSED["release_until"] : 0)); ?>
					<tr>
						<td colspan="3" style="padding-top: 25px">
							<table style="width: 100%" cellspacing="0" cellpadding="0" border="0">
								<tr>
									<td style="width: 25%; text-align: left">
										<input type="button" class="button" value="Cancel" onclick="window.location='<?php echo ENTRADA_URL; ?>/admin/events'" />
									</td>
									<td style="width: 75%; text-align: right; vertical-align: middle">
										<span class="content-small">After saving:</span>
										<select id="post_action" name="post_action">
											<option value="content"<?php echo (((!isset($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"])) || ($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] == "content")) ? " selected=\"selected\"" : ""); ?>>Add content to event</option>
											<option value="new"<?php echo (($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] == "new") ? " selected=\"selected\"" : ""); ?>>Add another event</option>
											<option value="index"<?php echo (($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["post_action"] == "index") ? " selected=\"selected\"" : ""); ?>>Return to event list</option>
										</select>
										<input type="submit" class="button" value="Save" />
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</form>


			<script type="text/javascript">
//				function selectEventAudienceOption(type) {
//					$$('.event_audience').invoke('hide');
//					$$('.'+type+'_audience').invoke('show');
//					if(type!== 'proxy_id'){
//						checkConflict();
//					}
//				}
				
//				var prevDate = '';
//				var prevTime = '00:00 AM';
//				var t = self.setInterval("checkDifference()", 1500);
					
					
//				Event.observe('event_audience_type_course','change',checkConflict);
//				Event.observe('associated_grad_year','change',checkConflict);
//				Event.observe('associated_organisation_id','change',checkConflict);
//				Event.observe('student_list','change',checkConflict)
//				Event.observe('eventtype_ids','change',checkConflict)
//				//Event.observe('event_start_date','keyup',checkConflict);
					
				
//				function checkDifference(){
//					if($('event_start_date').value !== prevDate){
//						prevDate = $('event_start_date').value;
//						checkConflict();
//					}
//					else if($('event_start_display').innerHTML !== prevTime){
//						prevTime = $('event_start_display').innerHTML;
//						checkConflict();						
//					}
//				}
//				function checkConflict(){
//					new Ajax.Request('<?php echo ENTRADA_URL;?>/api/learning-event-conflicts.api.php',
//					{
//						method:'post',
//						parameters: $("addEventForm").serialize(true),
//						onSuccess: function(transport){
//						var response = transport.responseText || null;
//						if(response !==null){
//							var g = new k.Growler();
//							g.smoke(response,{life:7});
//						}
//						},
//						onFailure: function(){ alert('Unable to check if a conflict exists.') }
//					});
//				}
			</script>
			<?php
		break;
	}
}
?>
