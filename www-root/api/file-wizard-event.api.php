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
 * Loads the Learning Event file wizard when a teacher / director wants to add /
 * edit a file on the Manage Events > Content page.
 * 
 * @author Organisation: Queen's University
 * @author Unit: School of Medicine
 * @author Developer: Matt Simpson <matt.simpson@queensu.ca>
 * @author Developer: James Ellis <james.ellis@queensu.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 * 
*/

@set_include_path(implode(PATH_SEPARATOR, array(
    dirname(__FILE__) . "/../core",
    dirname(__FILE__) . "/../core/includes",
    dirname(__FILE__) . "/../core/library",
    dirname(__FILE__) . "/../core/library/vendor",
    get_include_path(),
)));

/**
 * Include the Entrada init code.
 */
require_once("init.inc.php");

ob_start("on_checkout");

if((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	echo "<div id=\"scripts-on-open\" style=\"display: none;\">\n";
	echo "alert('It appears as though your session has expired; you will now be taken back to the login page.');\n";
	echo "if(window.opener) {\n";
	echo "	window.opener.location = '".ENTRADA_URL.((isset($_SERVER["REQUEST_URI"])) ? "?url=".rawurlencode(clean_input($_SERVER["REQUEST_URI"], array("nows", "url"))) : "")."';\n";
	echo "	top.window.close();\n";
	echo "} else {\n";
	echo "	window.location = '".ENTRADA_URL.((isset($_SERVER["REQUEST_URI"])) ? "?url=".rawurlencode(clean_input($_SERVER["REQUEST_URI"], array("nows", "url"))) : "")."';\n";
	echo "}\n";
	echo "</div>\n";
	exit;
} else {

	$ACTION				= "add";
	$EVENT_ID			= 0;
	$EFILE_ID			= 0;
	$JS_INITSTEP		= 1;

	if(isset($_GET["action"])) {
		$ACTION	= trim($_GET["action"]);
	}

	if((isset($_GET["step"])) && ((int) trim($_GET["step"]))) {
		$STEP = (int) trim($_GET["step"]);
	}

	if((isset($_GET["id"])) && ((int) trim($_GET["id"]))) {
		$EVENT_ID	= (int) trim($_GET["id"]);
	}

	if((isset($_GET["fid"])) && ((int) trim($_GET["fid"]))) {
		$EFILE_ID = (int) trim($_GET["fid"]);
	}

	$modal_onload = array();

	if($EVENT_ID) {
		$query	= "	SELECT a.*, b.`organisation_id`
					FROM `events` AS a
					LEFT JOIN `courses` AS b
					ON b.`course_id` = a.`course_id`
					WHERE a.`event_id` = ".$db->qstr($EVENT_ID);
		$result	= $db->GetRow($query);
		if($result) {
			$access_allowed = false;
			if (!$ENTRADA_ACL->amIAllowed(new EventContentResource($EVENT_ID, $result["course_id"], $result["organisation_id"]), "update")) {
				$query = "SELECT * FROM `events` WHERE `parent_id` = ".$db->qstr($EVENT_ID);
				if ($sessions = $db->GetAll($query)) {
					foreach ($sessions as $session) {
						if ($ENTRADA_ACL->amIAllowed(new EventContentResource($session["event_id"], $result["course_id"], $result["organisation_id"]), "update")) {
							$access_allowed = true;
						}
					}
				}
			} else {
				$access_allowed = true;
			}
			if (!$access_allowed) {
				$modal_onload[]	= "closeWizard()";

				$ERROR++;
				$ERRORSTR[]	= "Your account does not have the permissions required to use this feature of this module. If you believe you are receiving this message in error please contact us for assistance.";

				echo display_error();

				application_log("error", "Group [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["group"]."] and role [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["role"]."] does not have access to the file wizard.");
			} else {
                $recurring_events_query = "
                    SELECT * FROM `events`
                    WHERE `recurring_id` = (
                        SELECT `recurring_id`
                        FROM `events`
                        WHERE `event_id` = ".$db->qstr($EVENT_ID)."
                    )
                    AND `event_id` != ".$db->qstr($EVENT_ID);
                $all_recurring_events = $db->GetAll($recurring_events_query);
                if ($all_recurring_events) {
                    if ($EFILE_ID && $ACTION === 'edit') {
                        $recurring_events = array();
                        $original_file_info = $db->GetRow("SELECT * FROM `event_files` WHERE `efile_id` = ".$db->qstr($EFILE_ID));
                        
                        foreach ($all_recurring_events as $recurring_event) {
                            if ($db->GetOne("SELECT `efile_id` FROM `event_files`
                                    WHERE `event_id`=".$db->qstr($recurring_event['event_id'])."
                                    AND `file_name`=".$db->qstr($original_file_info['file_name'])."
                                    AND `updated_date`=".$db->qstr($original_file_info['updated_date']))) {
                                $recurring_events[] = $recurring_event;
                            }
                        }
                    } else {
                        $recurring_events = $all_recurring_events;
                    }
                }
                
                if ($recurring_events) {
                    ?>
                    <script type="text/javascript">
                        maxSteps = 4;
                    </script>
                    <?php
                }

				switch($ACTION) {
					case "edit" :
						/**
						 * Edit file form.
						 */

						if ($EFILE_ID) {
							$query	= "SELECT * FROM `event_files` WHERE `event_id` = ".$db->qstr($EVENT_ID)." AND `efile_id` = ".$db->qstr($EFILE_ID);
							$result	= $db->GetRow($query);
							if ($result) {
								// Error Checking
								switch($STEP) {
									case 2 :
										/**
										 * In this error checking we are working backwards along the internal javascript
										 * steps timeline. This is so the JS_INITSTEP variable is set to the lowest page
										 * number that contains errors.
										 */

										$PROCESSED["event_id"] = $EVENT_ID;

										/**
										 * Step 3 Error Checking
										 */
										if((isset($_POST["update_file"])) && ($_POST["update_file"] == "yes")) {
											if(isset($_FILES["filename"])) {
												switch($_FILES["filename"]["error"]) {
													case 0 :
                                                        $finfo = new finfo(FILEINFO_MIME);
                                                        $type = $finfo->file($_FILES["filename"]["tmp_name"]);
                                                        $type_array = explode(";", $type);
                                                        $mimetype = $type_array[0];
                                                        $PROCESSED["file_type"]		= strtolower(trim($_FILES["filename"]["type"]));
                                                        switch($PROCESSED["file_type"]) {
                                                            case "application/x-forcedownload":
                                                            case "application/octet-stream":
                                                            case "\"application/octet-stream\"":
                                                            case "application/download":
                                                            case "application/force-download":
                                                                $PROCESSED["file_type"] = $mimetype;
                                                                break;
                                                        }

														$PROCESSED["file_size"]	= (int) trim($_FILES["filename"]["size"]);
														$PROCESSED["file_name"]	= useable_filename(trim($_FILES["filename"]["name"]));
													break;
													case 1 :
													case 2 :
														$ERROR++;
														$ERRORSTR[]		= "q5";
														$JS_INITSTEP	= 3;
													break;
													case 3 :
														$modal_onload[]		= "alert('The file that uploaded did not complete the upload process or was interupted. Please try again.')";

														$ERROR++;
														$ERRORSTR[]		= "q5";
														$JS_INITSTEP	= 3;
													break;
													case 4 :
														$modal_onload[]		= "alert('You did not select a file on your computer to upload. Please select a local file.')";

														$ERROR++;
														$ERRORSTR[]		= "q5";
														$JS_INITSTEP	= 3;
													break;
													case 6 :
													case 7 :
														$modal_onload[]		= "alert('Unable to store the new file on the server. We have been informed of this error, please try again later.')";

														$ERROR++;
														$ERRORSTR[]		= "q5";
														$JS_INITSTEP	= 3;

														application_log("error", "File upload error: ".(($_FILES["filename"]["error"] == 6) ? "Missing a temporary folder." : "Failed to write file to disk."));
													break;
													default :
														application_log("error", "Unrecognized file upload error number [".$_FILES["filename"]["error"]."].");
													break;
												}
											} else {
												$modal_onload[]		= "alert('To upload a file to this event you must select a file to upload from your computer.')";

												$ERROR++;
												$ERRORSTR[]		= "q5";
												$JS_INITSTEP	= 3;
											}
										}

										if ((isset($_POST["file_title"])) && ($tmp_input = clean_input($_POST["file_title"], array("trim", "notags")))) {
											$PROCESSED["file_title"]	= $tmp_input;
										} else {
											$PROCESSED["file_title"]	= $PROCESSED["file_name"];
										}

										if((isset($_POST["file_notes"])) && ($tmp_input = clean_input($_POST["file_notes"], array("trim", "notags")))) {
											$PROCESSED["file_notes"]	= $tmp_input;
										} else {
											$ERROR++;
											$ERRORSTR[]		= "q7";
											$JS_INITSTEP	= 3;
										}

										/**
										 * Step 2 Error Checking
										 * Because this unsets the $ERRORSTR array, only do this if there is not already an error.
										 * PITA, I know.
										 */
										if(!$ERROR) {
											if((isset($_POST["timedrelease"])) && ($_POST["timedrelease"] == "yes")) {
												$timed_release		= validate_calendars("valid", false, false);

												if($ERROR) {
													$modal_onload[]		= "alert('".addslashes($ERRORSTR[0])."')";

													$ERROR			= 0;
													$ERRORSTR		= array();

													$ERROR++;
													$ERRORSTR[]		= "q4";
													$JS_INITSTEP	= 2;
												}

												if((isset($timed_release["start"])) && ((int) $timed_release["start"])) {
													$PROCESSED["release_date"]	= (int) $timed_release["start"];
												} else {
													$PROCESSED["release_date"]	= 0;
												}

												if((isset($timed_release["finish"])) && ((int) $timed_release["finish"])) {
													$PROCESSED["release_until"] = (int) $timed_release["finish"];
												} else {
													$PROCESSED["release_until"] = 0;
												}
											} else {
												$PROCESSED["release_date"]	= 0;
												$PROCESSED["release_until"]	= 0;
											}
										}

										/**
										 * Step 1 Error Checking
										 */
										if((isset($_POST["file_category"])) && (@array_key_exists(trim($_POST["file_category"]), $RESOURCE_CATEGORIES["event"]))) {
											$PROCESSED["file_category"] = trim($_POST["file_category"]);
										} else {
											$ERROR++;
											$ERRORSTR[]		= "q1";
											$JS_INITSTEP	= 1;
										}

										if((isset($_POST["access_method"])) && ($_POST["access_method"] == 1)) {
											$PROCESSED["access_method"] = 1;
										} else {
											$PROCESSED["access_method"] = 0;
										}

										if((isset($_POST["required"])) && ($_POST["required"] == "yes")) {
											$PROCESSED["required"] = 1;
										} else {
											$PROCESSED["required"] = 0;
										}

										if((isset($_POST["timeframe"])) && (@array_key_exists(trim($_POST["timeframe"]), $RESOURCE_TIMEFRAMES["event"]))) {
											$PROCESSED["timeframe"] = trim($_POST["timeframe"]);
										} else {
											$ERROR++;
											$ERRORSTR[]		= "q3";
											$JS_INITSTEP	= 1;
										}

										$PROCESSED["updated_date"]	= time();
										$PROCESSED["updated_by"]	= $ENTRADA_USER->getID();

										if (!$ERROR) {
											if ((isset($_POST["update_file"])) && ($_POST["update_file"] == "yes")) {
												$query	= "
														SELECT *
														FROM `event_files`
														WHERE `event_id` = ".$db->qstr($EVENT_ID)."
														AND `file_name` = ".$db->qstr($PROCESSED["file_name"])."
														AND `efile_id` <> ".$db->qstr($EFILE_ID);
												$result	= $db->GetRow($query);
												if ($result) {
													$modal_onload[]		= "alert('A file named ".addslashes($PROCESSED["file_name"])." already exists in this teaching event.\\n\\nIf this is an updated version, please delete the old file before adding this one.')";

													$ERROR++;
													$ERRORSTR[]		= "q5";
													$JS_INITSTEP	= 3;
												} else {
													if (!DEMO_MODE) {
														if ($db->AutoExecute("event_files", $PROCESSED, "UPDATE", "efile_id = ".$db->qstr($EFILE_ID)." AND event_id = ".$db->qstr($EVENT_ID))) {
															last_updated("event", $EVENT_ID);
	
															if ((is_dir(FILE_STORAGE_PATH)) && (is_writable(FILE_STORAGE_PATH))) {
																if (file_exists(FILE_STORAGE_PATH."/".$EFILE_ID)) {
																	application_log("notice", "File ID [".$EFILE_ID."] already existed and was overwritten with newer file.");
																}
	
																if (move_uploaded_file($_FILES["filename"]["tmp_name"], FILE_STORAGE_PATH."/".$EFILE_ID)) {
																	application_log("success", "File ID ".$EFILE_ID." was successfully added to the database and filesystem for event [".$EVENT_ID."].");
																} else {
																	$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";
	
																	$ERROR++;
																	$ERRORSTR[]		= "q5";
																	$JS_INITSTEP	= 3;
	
																	application_log("error", "The move_uploaded_file function failed to move temporary file over to final location.");
																}
															} else {
																$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";
	
																$ERROR++;
																$ERRORSTR[]		= "q5";
																$JS_INITSTEP	= 3;
	
																application_log("error", "Either the FILE_STORAGE_PATH doesn't exist on the server or is not writable by PHP.");
															}
														} else {
															$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";
	
															$ERROR++;
															$ERRORSTR[]		= "q5";
															$JS_INITSTEP	= 3;
	
															application_log("error", "Unable to insert the file into the database for event ID [".$EVENT_ID."]. Database said: ".$db->ErrorMsg());
														}
													} else {
														// In Demo Mode so no need to do anything other than to update the fields in the database with the new file information
														// since the demo file already exists on the server
														switch($PROCESSED["file_category"]) {
															case "lecture_notes":
																$PROCESSED["file_type"] = filetype(DEMO_NOTES);
																$PROCESSED["file_size"] = filesize(DEMO_NOTES);
																$PROCESSED["file_name"] = basename(DEMO_NOTES);
																$DEMO_FILE = DEMO_NOTES;
																break;
															case "lecture_slides":
																$PROCESSED["file_type"] = filetype(DEMO_SLIDES);
																$PROCESSED["file_size"] = filesize(DEMO_SLIDES);
																$PROCESSED["file_name"] = basename(DEMO_SLIDES);
																$DEMO_FILE = DEMO_SLIDES;
																break;
															case "podcast":
																$PROCESSED["file_type"] = filetype(DEMO_PODCAST);
																$PROCESSED["file_size"] = filesize(DEMO_PODCAST);
																$PROCESSED["file_name"] = basename(DEMO_PODCAST);
																$DEMO_FILE = DEMO_PODCAST;
																break;
															case "other":
															default:
																$PROCESSED["file_type"] = filetype(DEMO_FILE);
																$PROCESSED["file_size"] = filesize(DEMO_FILE);
																$PROCESSED["file_name"] = basename(DEMO_FILE);
																$DEMO_FILE = DEMO_FILE;
																break;
														}
														if ($db->AutoExecute("event_files", $PROCESSED, "UPDATE", "efile_id = ".$db->qstr($EFILE_ID)." AND event_id = ".$db->qstr($EVENT_ID))) {
															application_log("success", "File ID ".$EFILE_ID." was successfully added to the database and filesystem for course [".$EVENT_ID."].");
														} else {
															$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";

															$ERROR++;
															$ERRORSTR[]		= "q5";
															$JS_INITSTEP	= 3;

															application_log("error", "The move_uploaded_file function failed to move temporary file over to final location.");
														}
													}
												}
											} else {
												if($db->AutoExecute("event_files", $PROCESSED, "UPDATE", "efile_id = ".$db->qstr($EFILE_ID)." AND event_id = ".$db->qstr($EVENT_ID))) {
													last_updated("event", $EVENT_ID);

													application_log("success", "File ID ".$EFILE_ID." was successfully update to the database and filesystem for event [".$EVENT_ID."].");
												} else {
													$modal_onload[]		= "alert('This update was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";

													$ERROR++;
													$ERRORSTR[]		= "q5";
													$JS_INITSTEP	= 3;

													application_log("error", "Unable to update this record in the database for event ID [".$EVENT_ID."] and file ID [".$EFILE_ID."]. Database said: ".$db->ErrorMsg());
												}
											}
										}
                                        
                                        //Recurring events
                                        if (!$ERROR && $recurring_events && isset($_POST['recurring_event_ids']) && is_array($_POST['recurring_event_ids'])) {
                                            $source_file_path = FILE_STORAGE_PATH."/".$EFILE_ID;
                                            $original_event_start = (int)$db->GetOne("SELECT `event_start` FROM `events` WHERE `event_id`=".$db->qstr($EVENT_ID));

                                            $updating_recurring_events = array();
                                            foreach ($recurring_events as $temp_event) {
                                                if (in_array($temp_event['event_id'], $_POST['recurring_event_ids'])) {
                                                    $updating_recurring_events[] = $temp_event;
                                                }
                                            }

                                            foreach ($updating_recurring_events as $recurring_event) {
                                                $PROCESSED_RECURRING_EVENT = $PROCESSED;
                                                $PROCESSED_RECURRING_EVENT['event_id'] = $recurring_event['event_id'];
                                                if (isset($PROCESSED['release_date']) && $PROCESSED['release_date']) {
                                                    $PROCESSED_RECURRING_EVENT['release_date'] = (int)$recurring_event['event_start'] + ((int)$PROCESSED['release_date'] - $original_event_start);
                                                }
                                                if (isset($PROCESSED['release_until']) && $PROCESSED['release_until']) {
                                                    $PROCESSED_RECURRING_EVENT['release_until'] = (int)$recurring_event['event_start'] + ((int)$PROCESSED['release_until'] - $original_event_start);
                                                }
                                                
                                                $recurring_file_query = "SELECT `efile_id`
                                                                         FROM `event_files` 
                                                                         WHERE `event_id` = ".$db->qstr($recurring_event['event_id'])."
                                                                         AND `file_name` = ".$db->qstr($original_file_info['file_name'])."
                                                                         AND `updated_date` = ".$db->qstr($original_file_info['updated_date']);
                                                $recurring_file_id = $db->GetOne($recurring_file_query);
                                                if (!$recurring_file_id) {
                                                    application_log("notice", "Synced file not found for recurring event id {$recurring_event['event_id']}.");
                                                    continue;
                                                }
                                                
                                                $duplicate_name_query = "SELECT `efile_id`
                                                                         FROM `event_files`
                                                                         WHERE `event_id` = ".$db->qstr($recurring_event['event_id'])."
                                                                         AND `file_name` = ".$db->qstr($PROCESSED['file_name'])."
                                                                         AND `efile_id` <> ".$db->qstr($recurring_file_id);
                                                if ($db->GetOne($duplicate_name_query)) {
                                                    application_log("error", "Can't sync file id $recurring_file_id in recurring event id {$recurring_event['event_id']} because a file already exists in that event with the new file name.");
                                                    continue;
                                                }

                                                if ($db->AutoExecute("event_files", $PROCESSED_RECURRING_EVENT, "UPDATE", "`efile_id`=".$db->qstr($recurring_file_id))) {
                                                    last_updated("event", $recurring_event['event_id']);

                                                    if ((isset($_POST["update_file"])) && ($_POST["update_file"] == "yes")) {
                                                        if ((is_dir(FILE_STORAGE_PATH)) && (is_writable(FILE_STORAGE_PATH))) {
                                                            if (file_exists(FILE_STORAGE_PATH."/".$EFILE_ID)) {
                                                                application_log("notice", "File ID [".$EFILE_ID."] already existed and was overwritten with newer file.");
                                                            }

                                                            if (copy(FILE_STORAGE_PATH."/".$EFILE_ID, FILE_STORAGE_PATH."/".$recurring_file_id)) {
                                                                application_log("success", "File ID ".$recurring_file_id." was successfully updated in the database and filesystem for related recurring event [{$recurring_event['event_id']}].");
                                                            } else {
                                                                $modal_onload[]	= "alert('The updated file was not successfully saved for a related recurring event. The MEdTech Unit has been informed of this error, please try again later.')";
                                                                application_log("error", "The copy function failed to copy file over to final location for related recurring event.");
                                                            }
                                                        } else {
                                                            $modal_onload[]	= "alert('The updated file was not successfully saved for a related recurring event. The MEdTech Unit has been informed of this error, please try again later.')";
                                                            application_log("error", "Either the FILE_STORAGE_PATH doesn't exist on the server or is not writable by PHP.");
                                                        }
                                                    }
                                                } else {
                                                    $modal_onload[]	= "alert('The updated file was not successfully saved for a related recurring event. The MEdTech Unit has been informed of this error, please try again later.')";
                                                    application_log("error", "Unable to update the file into the database for related recurring event ID [{$recurring_event['event_id']}]. Database said: ".$db->ErrorMsg());
                                                }
                                            }
                                        }

										if($ERROR) {
											$STEP = 1;
										}
									break;
									case 1 :
									default :
										/**
										 * Since this is the first step, simply fill $PROCESSED with the $result value.
										 */

										$PROCESSED = $result;

										if(((int) $PROCESSED["release_date"]) || ((int) $PROCESSED["release_until"])) {
											$show_timed_release	= true;
										} else {
											$show_timed_release = false;
										}
									break;
								}

								// Display Edit Step
								switch($STEP) {
									case 2 :
										$modal_onload[] = "parentReload()";
										?>
										<div class="modal-dialog" id="file-edit-wizard-<?php echo $EFILE_ID; ?>">
											<div id="wizard">
												<h3 class="border-below">File Wizard <span class="content-small space-left large"><strong>Editing</strong> <?php echo html_encode($PROCESSED["file_title"]); ?></span></h3>
												<div id="body">
													<h2>File Updated Successfully</h2>
		
													<div class="display-success">
														<?php if(!DEMO_MODE) { ?>
															You have successfully updated <strong><?php echo html_encode($PROCESSED["file_title"]); ?></strong> in this event.
														<?php
														} else { ?>
															You are in demo mode therefore <strong><?php echo html_encode($PROCESSED["file_title"]); ?></strong> as been replaced with our default demo file and linked to this event.
														<?php } ?>
													</div>
		
													To <strong>re-edit this file</strong> or <strong>close this window</strong> please use the buttons below.
												</div>
												<div id="footer">
													<input type="button" class="btn" value="Close" onclick="closeWizard()" style="float: left; margin: 4px 0px 4px 10px" />
													<input type="button" class="btn btn-primary" value="Re-Edit File" onclick="restartWizard('<?php echo ENTRADA_URL; ?>/api/file-wizard-event.api.php?action=edit&amp;id=<?php echo $EVENT_ID; ?>&amp;fid=<?php echo $EFILE_ID; ?>')" style="float: right; margin: 4px 10px 4px 0px" />
												</div>
											</div>
										</div>
										<?php
										history_log($EVENT_ID, "updated $PROCESSED[file_title] file.");
									break;
									case 1 :
									default :
										$modal_onload[] = "initStep(".$JS_INITSTEP.")";

										if(((isset($_POST["timedrelease"])) && ($_POST["timedrelease"] == "yes")) || ((isset($show_timed_release)) && ($show_timed_release))) {
											$modal_onload[] = "timedRelease('block')";
										} else {
											$modal_onload[] = "timedRelease('none')";
										}

										if((isset($_POST["update_file"])) && ($_POST["update_file"] == "yes")) {
											$modal_onload[] = "updateFile('block')";
										} else {
											$modal_onload[] = "updateFile('none')";
										}
										?>
										<div  class="modal-dialog" id="file-edit-wizard-<?php echo $EFILE_ID; ?>">
											<div id="wizard">
												<form id="wizard-form" target="upload-frame" action="<?php echo ENTRADA_URL; ?>/api/file-wizard-event.api.php?action=edit&amp;id=<?php echo $EVENT_ID; ?>&amp;fid=<?php echo $EFILE_ID; ?>&amp;step=2" method="post" enctype="multipart/form-data" style="display: inline">
												<input type="hidden" name="MAX_UPLOAD_FILESIZE" value="<?php echo MAX_UPLOAD_FILESIZE; ?>" />
												<h3 class="border-below">File Wizard <span class="content-small space-left large"><strong>Editing</strong> <?php echo html_encode($PROCESSED["file_title"]); ?></span></h3>
												<div id="body">
													<h2 id="step-title"></h2>
                                            
                                                    <?php if ($recurring_events) { ?>
                                                    <div id="step1" style="display: none">
                                                        <div class="wizard-question">
                                                            <div style="font-size: 14px">Select the recurring events you would like apply these changes to.</div>

                                                            <?php foreach ($recurring_events as $recurring_event) { ?>
                                                            <div style="padding-left: 65px">
                                                                <label class="checkbox">
                                                                    <input type="checkbox" id="recurring_event_<?php echo $recurring_event["event_id"] ?>" class="recurring_events" name="recurring_event_ids[]" value="<?php echo $recurring_event["event_id"]; ?>"<?php echo (!isset($_POST["recurring_event_ids"]) || in_array($recurring_event["event_id"], $_POST["recurring_event_ids"]) ? " checked=\"checked\"" : ""); ?> />
                                                                    <?php echo html_encode($recurring_event["event_title"]); ?>
                                                                    on <?php echo html_encode(date(DEFAULT_DATE_FORMAT, $recurring_event["event_start"])); ?>
                                                                </label>
                                                            </div>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                    <?php } ?>
                                                    
													<div id="step<?php echo $recurring_events ? 2 : 1; ?>" style="display: none">
														<div id="q1" class="wizard-question<?php echo ((in_array("q1", $ERRORSTR)) ? " display-error" : ""); ?>">
															<div style="font-size: 14px">What type of file is this document?</div>
															<div style="padding-left: 65px">
																<?php
																if(@count($RESOURCE_CATEGORIES["event"])) {
																	foreach($RESOURCE_CATEGORIES["event"] as $key => $value) {
																		echo "<input type=\"radio\" id=\"file_category_".$key."\" name=\"file_category\" value=\"".$key."\" style=\"vertical-align: middle\"".((isset($PROCESSED["file_category"])) ? (($PROCESSED["file_category"] == $key) ? " checked=\"checked\"" : "") : (($key == "other") ? " checked=\"checked\"" : ""))." /> <label for=\"file_category_".$key."\">".$value."</label><br />";
																	}
																}
																?>
															</div>
														</div>
	
														<div id="q1b" class="wizard-question<?php echo ((in_array("q1b", $ERRORSTR)) ? " display-error" : ""); ?>">
															<div style="font-size: 14px">How do you want people to view this file?</div>
															<div style="padding-left: 65px">
																<input type="radio" id="access_method_0" name="access_method" value="0" style="vertical-align: middle"<?php echo (((!isset($PROCESSED["access_method"])) || ((isset($PROCESSED["access_method"])) && (!(int) $PROCESSED["access_method"]))) ? " checked=\"checked\"" : ""); ?> /> <label for="access_method_0">Download it to their computer first, then open it.</label><br />
																<input type="radio" id="access_method_1" name="access_method" value="1" style="vertical-align: middle"<?php echo (((isset($PROCESSED["access_method"])) && ((int) $PROCESSED["access_method"])) ? " checked=\"checked\"" : ""); ?> /> <label for="access_method_1">Attempt to view it directly in the web-browser.</label><br />
															</div>
														</div>
	
														<div id="q2" class="wizard-question<?php echo ((in_array("q2", $ERRORSTR)) ? " display-error" : ""); ?>">
															<div style="font-size: 14px">Should viewing this file be considered optional or required?</div>
															<div style="padding-left: 65px">
																<input type="radio" id="required_no" name="required" value="no"<?php echo (((!isset($PROCESSED["required"])) || (!$PROCESSED["required"])) ? " checked=\"checked\"" : ""); ?> /> <label for="required_no">optional</label><br />
																<input type="radio" id="required_yes" name="required" value="yes"<?php echo (($PROCESSED["required"] == 1) ? " checked=\"checked\"" : ""); ?> /> <label for="required_yes">required</label><br />
															</div>
														</div>
	
														<div id="q3" class="wizard-question<?php echo ((in_array("q3", $ERRORSTR)) ? " display-error" : ""); ?>">
															<div style="font-size: 14px">When should this resource be used by the learner?</div>
															<div style="padding-left: 65px">
																<?php
																if(@count($RESOURCE_TIMEFRAMES["event"])) {
																	foreach($RESOURCE_TIMEFRAMES["event"] as $key => $value) {
																		echo "<input type=\"radio\" id=\"timeframe_".$key."\" name=\"timeframe\" value=\"".$key."\" style=\"vertical-align: middle\"".((isset($PROCESSED["timeframe"])) ? (($PROCESSED["timeframe"] == $key) ? " checked=\"checked\"" : "") : (($key == "none") ? " checked=\"checked\"" : ""))." /> <label for=\"timeframe_".$key."\">".$value."</label><br />";
																	}
																}
																?>
															</div>
														</div>
													</div>
	
													<div id="step<?php echo $recurring_events ? 3 : 2; ?>" style="display: none">
														<div id="q4" class="wizard-question<?php echo ((in_array("q4", $ERRORSTR)) ? " display-error" : ""); ?>">
															<div style="font-size: 14px">Would you like to add timed release dates to this file?</div>
															<div style="padding-left: 65px">
																<input type="radio" id="timedrelease_no" name="timedrelease" value="no" onclick="timedRelease('none')"<?php echo ((((!isset($_POST["timedrelease"])) || ($_POST["timedrelease"] == "no")) && ((!isset($show_timed_release)) || (!$show_timed_release))) ? " checked=\"checked\"" : ""); ?> /> <label for="timedrelease_no">No, this file is accessible any time.</label><br />
																<input type="radio" id="timedrelease_yes" name="timedrelease" value="yes" onclick="timedRelease('block')"<?php echo ((((isset($_POST["timedrelease"])) && ($_POST["timedrelease"] == "yes")) || ((isset($show_timed_release)) && ($show_timed_release))) ? " checked=\"checked\"" : ""); ?> /> <label for="timedrelease_yes">Yes, this file has timed release information.</label><br />
															</div>
	
															<div id="timed-release-info" style="display: none">
																<br />
																By checking the box on the left, you will enable the ability to select release / revoke dates and times for this file.
																<br /><br />
																<table style="width: 100%" cellspacing="0" cellpadding="4" border="0" summary="Timed Release Information">
																<colgroup>
																	<col style="width: 3%" />
																	<col style="width: 30%" />
																	<col style="width: 67%" />
																</colgroup>
																<?php echo generate_calendars("valid", "Accessible", true, false, ((isset($PROCESSED["release_date"])) ? $PROCESSED["release_date"] : 0), true, false, ((isset($PROCESSED["release_until"])) ? $PROCESSED["release_until"] : 0), true, true); ?>
																</table>
															</div>
														</div>
													</div>
	
													<div id="step<?php echo $recurring_events ? 4 : 3; ?>" style="display: none">
														<div id="q5" class="wizard-question<?php echo ((in_array("q5", $ERRORSTR)) ? " display-error" : ""); ?>">
															<div style="font-size: 14px">Would you like to replace the current file with a new one?</div>
															<div style="padding-left: 65px">
																<input type="radio" id="update_file_no" name="update_file" value="no" onclick="updateFile('none')"<?php echo (((!isset($_POST["update_file"])) || ($_POST["update_file"] == "no")) ? " checked=\"checked\"" : ""); ?> /> <label for="update_file_no">No, I do not wish to <span style="font-style: oblique">replace</span> current file.</label><br />
																<input type="radio" id="update_file_yes" name="update_file" value="yes" onclick="updateFile('block')"<?php echo (((isset($_POST["update_file"])) && ($_POST["update_file"] == "yes")) ? " checked=\"checked\"" : ""); ?> /> <label for="update_file_yes">Yes, I would like to <span style="font-style: oblique">replace</span> the existing file.</label><br />
															</div>
															<div id="upload-new-file" style="display: none">
																<br />
																<div style="font-size: 14px">Please select the new file to upload from your computer:</div>
																<div style="padding-left: 65px; padding-right: 10px; padding-top: 10px">
																	<input type="file" id="filename" name="filename" value="" size="25" onchange="grabFilename()" /><br />
																</div>
															</div>
														</div>
														<div id="q6" class="wizard-question<?php echo ((in_array("q6", $ERRORSTR)) ? " display-error" : ""); ?>">
															<div style="font-size: 14px">You can <span style="font-style: oblique">optionally</span> provide a different title for this file.</div>
															<div style="padding-left: 65px; padding-right: 10px; padding-top: 10px">
																<label for="file_title" class="form-required">File Title:</label> <span class="content-small"><strong>Example:</strong> Video Of Procedure 1</span><br />
																<input type="text" id="file_title" name="file_title" value="<?php echo ((isset($PROCESSED["file_title"])) ? html_encode($PROCESSED["file_title"]) : ""); ?>" maxlength="128" style="width: 350px;" />
															</div>
														</div>
														<div id="q7" class="wizard-question<?php echo ((in_array("q7", $ERRORSTR)) ? " display-error" : ""); ?>">
															<div style="font-size: 14px">You <span style="font-style: oblique">must</span> provide a description for this file as well.</div>
															<div style="padding-left: 65px; padding-right: 10px; padding-top: 10px">
																<label for="file_notes" class="form-required">File Description:</label><br />
																<textarea id="file_notes" name="file_notes" style="width: 350px; height: 75px"><?php echo ((isset($PROCESSED["file_notes"])) ? html_encode($PROCESSED["file_notes"]) : ""); ?></textarea>
															</div>
														</div>
													</div>
												</div>
												<div id="footer">
													<input type="button" class="btn" value="Close" onclick="closeWizard()" style="float: left; margin: 4px 0px 4px 10px" />
													<input type="button" class="btn btn-primary" id="next-button" value="Next Step" onclick="nextStep()" style="float: right; margin: 4px 10px 4px 0px"  />
													<input type="button" class="btn" id="back-button" value="Previous Step" onclick="prevStep()" style="display: none; float: right; margin: 4px 10px 4px 0px" />
												</div>
												<div id="uploading-window" style="width: 100%; height: 100%;">
													<div style="display: table; width: 100%; height: 100%; _position: relative; overflow: hidden">
														<div style=" _position: absolute; _top: 50%;display: table-cell; vertical-align: middle;">
															<div style="_position: relative; _top: -50%; width: 100%; text-align: center">
																<span style="color: #003366; font-size: 18px; font-weight: bold">
																	<img src="<?php echo ENTRADA_URL; ?>/images/loading.gif" width="32" height="32" alt="File Saving" title="Please wait while changes are being saved." style="vertical-align: middle" /> Please Wait: changes are being saved.
																</span>
																<br /><br />
																This can take time depending on your connection speed and the filesize.
															</div>
														</div>
													</div>
												</div>
												</form>
											</div>
										</div>
										<?php
									break;
								}
							} else {
								$ERROR++;
								$ERRORSTR[] = "The provided file identifier does not exist in the provided event.";

								echo display_error();

								application_log("error", "File wizard was accessed with a file id that was not found in the database.");
							}
						} else {
							$ERROR++;
							$ERRORSTR[] = "You must provide a file identifier when using the file wizard.";

							echo display_error();

							application_log("error", "File wizard was accessed without any file id.");
						}
					break;
					case "add" :
					default :
						/**
						 * Add file form.
						 */

						// Error Checking
						switch($STEP) {
							case 2 :
								/**
								 * In this error checking we are working backwards along the internal javascript
								 * steps timeline. This is so the JS_INITSTEP variable is set to the lowest page
								 * number that contains errors.
								 */

								$PROCESSED["event_id"] = $EVENT_ID;
/*
								if (!isset($_POST["copyright_check"]) || !$_POST["copyright_check"]) {
									$ERROR++;
									$ERRORSTR[] = "q8";
								}
*/
								
								/**
								 * Step 3 Error Checking
								 */
								if(isset($_FILES["filename"])) {
									switch($_FILES["filename"]["error"]) {
										case 0 :
                                            $finfo = new finfo(FILEINFO_MIME);
                                            $type = $finfo->file($_FILES["filename"]["tmp_name"]);
                                            $type_array = explode(";", $type);
                                            $mimetype = $type_array[0];
                                            $PROCESSED["file_type"]		= strtolower(trim($_FILES["filename"]["type"]));
                                            switch($PROCESSED["file_type"]) {
                                                case "application/x-forcedownload":
                                                case "application/octet-stream":
                                                case "\"application/octet-stream\"":
                                                case "application/download":
                                                case "application/force-download":
                                                    $PROCESSED["file_type"] = $mimetype;
                                                    break;
                                            }
											$PROCESSED["file_size"]		= (int) trim($_FILES["filename"]["size"]);
											$PROCESSED["file_name"]		= useable_filename(trim($_FILES["filename"]["name"]));

											if((isset($_POST["file_title"])) && (trim($_POST["file_title"]))) {
												$PROCESSED["file_title"]	= trim($_POST["file_title"]);
											} else {
												$PROCESSED["file_title"]	= $PROCESSED["file_name"];
											}
										break;
										case 1 :
										case 2 :
											$ERROR++;
											$ERRORSTR[]		= "q5";
											$JS_INITSTEP	= 3;
										break;
										case 3 :
											$modal_onload[]		= "alert('The file that uploaded did not complete the upload process or was interupted. Please try again.')";

											$ERROR++;
											$ERRORSTR[]		= "q5";
											$JS_INITSTEP	= 3;
										break;
										case 4 :
											$modal_onload[]		= "alert('You did not select a file on your computer to upload. Please select a local file.')";

											$ERROR++;
											$ERRORSTR[]		= "q5";
											$JS_INITSTEP	= 3;
										break;
										case 6 :
										case 7 :
											$modal_onload[]		= "alert('Unable to store the new file on the server. We have been informed of this error, please try again later.')";

											$ERROR++;
											$ERRORSTR[]		= "q5";
											$JS_INITSTEP	= 3;

											application_log("error", "File upload error: ".(($_FILES["filename"]["error"] == 6) ? "Missing a temporary folder." : "Failed to write file to disk."));
										break;
										default :
											application_log("error", "Unrecognized file upload error number [".$_FILES["filename"]["error"]."].");
										break;
									}
								} else {
									$modal_onload[]		= "alert('To upload a file to this event you must select a file to upload from your computer.')";

									$ERROR++;
									$ERRORSTR[]		= "q5";
									$JS_INITSTEP	= 3;
								}

								if((isset($_POST["file_notes"])) && (trim($_POST["file_notes"]))) {
									$PROCESSED["file_notes"]	= trim($_POST["file_notes"]);
								} else {
									$ERROR++;
									$ERRORSTR[]		= "q7";
									$JS_INITSTEP	= 3;
								}

								/**
								 * Step 2 Error Checking
								 * Because this unsets the $ERRORSTR array, only do this if there is not already an error.
								 * PITA, I know.
								 */
								if(!$ERROR) {
									if((isset($_POST["timedrelease"])) && ($_POST["timedrelease"] == "yes")) {
										$timed_release = validate_calendars("valid", false, false);

										if($ERROR) {
											$modal_onload[]		= "alert('".addslashes($ERRORSTR[0])."')";

											$ERROR			= 0;
											$ERRORSTR		= array();

											$ERROR++;
											$ERRORSTR[]		= "q4";
											$JS_INITSTEP	= 2;
										}

										if((isset($timed_release["start"])) && ((int) $timed_release["start"])) {
											$PROCESSED["release_date"]	= (int) $timed_release["start"];
										}

										if((isset($timed_release["finish"])) && ((int) $timed_release["finish"])) {
											$PROCESSED["release_until"]	= (int) $timed_release["finish"];
										}
									}
								}

								/**
								 * Step 1 Error Checking
								 */
								if((isset($_POST["file_category"])) && (@array_key_exists(trim($_POST["file_category"]), $RESOURCE_CATEGORIES["event"]))) {
									$PROCESSED["file_category"] = trim($_POST["file_category"]);
								} else {
									$ERROR++;
									$ERRORSTR[]		= "q1";
									$JS_INITSTEP	= 1;
								}

								if((isset($_POST["access_method"])) && ($_POST["access_method"] == 1)) {
									$PROCESSED["access_method"] = 1;
								} else {
									$PROCESSED["access_method"] = 0;
								}

								if((isset($_POST["required"])) && ($_POST["required"] == "yes")) {
									$PROCESSED["required"] = 1;
								} else {
									$PROCESSED["required"] = 0;
								}

								if((isset($_POST["timeframe"])) && (@array_key_exists(trim($_POST["timeframe"]), $RESOURCE_TIMEFRAMES["event"]))) {
									$PROCESSED["timeframe"] = trim($_POST["timeframe"]);
								} else {
									$ERROR++;
									$ERRORSTR[]		= "q3";
									$JS_INITSTEP	= 1;
								}

								$PROCESSED["updated_date"]		= time();
								$PROCESSED["updated_by"]		= $ENTRADA_USER->getID();

								if(!$ERROR) {
									$query	= "
											SELECT *
											FROM `event_files`
											WHERE `event_id` = ".$db->qstr($EVENT_ID)."
											AND `file_name` = ".$db->qstr($PROCESSED["file_name"]);
									$result	= $db->GetRow($query);
									if($result) {
										$modal_onload[]		= "alert('A file named ".addslashes($PROCESSED["file_name"])." already exists in this teaching event.\\n\\nIf this is an updated version, please delete the old file before adding this one.')";

										$ERROR++;
										$ERRORSTR[]		= "q5";
										$JS_INITSTEP	= 3;
									} else {
										if (!DEMO_MODE) {
											if (($db->AutoExecute("event_files", $PROCESSED, "INSERT")) && ($EFILE_ID = $db->Insert_Id())) {
												last_updated("event", $EVENT_ID);
	
												if ((is_dir(FILE_STORAGE_PATH)) && (is_writable(FILE_STORAGE_PATH))) {
													if(file_exists(FILE_STORAGE_PATH."/".$EFILE_ID)) {
														application_log("notice", "File ID [".$EFILE_ID."] already existed and was overwritten with newer file.");
													}
	
													if (move_uploaded_file($_FILES["filename"]["tmp_name"], FILE_STORAGE_PATH."/".$EFILE_ID)) {
														application_log("success", "File ID ".$EFILE_ID." was successfully added to the database and filesystem for event [".$EVENT_ID."].");
													} else {
														$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";
	
														$ERROR++;
														$ERRORSTR[]		= "q5";
														$JS_INITSTEP	= 3;
	
														application_log("error", "The move_uploaded_file function failed to move temporary file over to final location.");
													}
												} else {
													$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";
	
													$ERROR++;
													$ERRORSTR[]		= "q5";
													$JS_INITSTEP	= 3;
	
													application_log("error", "Either the FILE_STORAGE_PATH doesn't exist on the server or is not writable by PHP.");
												}
											} else {
												$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";
	
												$ERROR++;
												$ERRORSTR[]		= "q5";
												$JS_INITSTEP	= 3;
	
												application_log("error", "Unable to insert the file into the database for event ID [".$EVENT_ID."]. Database said: ".$db->ErrorMsg());
											}
										} else {
											switch($PROCESSED["file_category"]) {
												case "lecture_notes":
													$PROCESSED["file_type"] = filetype(DEMO_NOTES);
													$PROCESSED["file_size"] = filesize(DEMO_NOTES);
													$PROCESSED["file_name"] = basename(DEMO_NOTES);
													$DEMO_FILE = DEMO_NOTES;
													break;
												case "lecture_slides":
													$PROCESSED["file_type"] = filetype(DEMO_SLIDES);
													$PROCESSED["file_size"] = filesize(DEMO_SLIDES);
													$PROCESSED["file_name"] = basename(DEMO_SLIDES);
													$DEMO_FILE = DEMO_SLIDES;
													break;
												case "podcast":
													$PROCESSED["file_type"] = filetype(DEMO_PODCAST);
													$PROCESSED["file_size"] = filesize(DEMO_PODCAST);
													$PROCESSED["file_name"] = basename(DEMO_PODCAST);
													$DEMO_FILE = DEMO_PODCAST;
													break;
												case "other":
												default:
													$PROCESSED["file_type"] = filetype(DEMO_FILE);
													$PROCESSED["file_size"] = filesize(DEMO_FILE);
													$PROCESSED["file_name"] = basename(DEMO_FILE);
													$DEMO_FILE = DEMO_FILE;
													break;
											}
											
											if(($db->AutoExecute("event_files", $PROCESSED, "INSERT")) && ($EFILE_ID = $db->Insert_Id())) {
												last_updated("event", $EVENT_ID);
	
												if((is_dir(FILE_STORAGE_PATH)) && (is_writable(FILE_STORAGE_PATH))) {
													if(file_exists(FILE_STORAGE_PATH."/".$EFILE_ID)) {
														application_log("notice", "File ID [".$EFILE_ID."] already existed and was overwritten with newer file.");
													}
													
													if(copy($DEMO_FILE, FILE_STORAGE_PATH."/".$EFILE_ID)) {
														application_log("success", "Success, however, since this is the Entrada demo site we do not allow uploading of files. Instead we've linked the information you just entered to a file that already exists on the demo server for demonstration purposes.");
													} else {
														$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";
	
														$ERROR++;
														$ERRORSTR[]		= "q5";
														$JS_INITSTEP	= 3;
	
														application_log("error", "The move_uploaded_file function failed to move temporary file over to final location.");
													}
												} else {
													$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";
	
													$ERROR++;
													$ERRORSTR[]		= "q5";
													$JS_INITSTEP	= 3;
	
													application_log("error", "Either the FILE_STORAGE_PATH doesn't exist on the server or is not writable by PHP.");
												}
											} else {
												$modal_onload[]		= "alert('The new file was not successfully saved. The MEdTech Unit has been informed of this error, please try again later.')";
	
												$ERROR++;
												$ERRORSTR[]		= "q5";
												$JS_INITSTEP	= 3;
	
												application_log("error", "Unable to insert the file into the database for event ID [".$EVENT_ID."]. Database said: ".$db->ErrorMsg());
											}
										}
									}
								}
                                
                                //Recurring events
                                if (!$ERROR && $recurring_events && isset($_POST['recurring_event_ids']) && is_array($_POST['recurring_event_ids'])) {
                                    $source_file_path = FILE_STORAGE_PATH."/".$EFILE_ID;
                                    $original_event_start = (int)$db->GetOne("SELECT `event_start` FROM `events` WHERE `event_id`=".$db->qstr($EVENT_ID));
                                    
                                    $updating_recurring_events = array();
                                    foreach ($recurring_events as $temp_event) {
                                        if (in_array($temp_event['event_id'], $_POST['recurring_event_ids'])) {
                                            $updating_recurring_events[] = $temp_event;
                                        }
                                    }
                                    
                                    foreach ($updating_recurring_events as $recurring_event) {
                                        $PROCESSED_RECURRING_EVENT = $PROCESSED;
                                        $PROCESSED_RECURRING_EVENT['event_id'] = $recurring_event['event_id'];
                                        if (isset($PROCESSED['release_date']) && $PROCESSED['release_date']) {
                                            $PROCESSED_RECURRING_EVENT['release_date'] = (int)$recurring_event['event_start'] + ((int)$PROCESSED['release_date'] - $original_event_start);
                                        }
                                        if (isset($PROCESSED['release_until']) && $PROCESSED['release_until']) {
                                            $PROCESSED_RECURRING_EVENT['release_until'] = (int)$recurring_event['event_start'] + ((int)$PROCESSED['release_until'] - $original_event_start);
                                        }
                                                
                                        $duplicate_name_query = "SELECT `efile_id`
                                                                 FROM `event_files`
                                                                 WHERE `event_id` = ".$db->qstr($recurring_event['event_id'])."
                                                                 AND `file_name` = ".$db->qstr($PROCESSED['file_name']);
                                        if ($db->GetOne($duplicate_name_query)) {
                                            application_log("error", "Can't sync file in recurring event id {$recurring_event['event_id']} because a file already exists in that event with the new file name.");
                                            continue;
                                        }
                                        
                                        if ($db->AutoExecute("event_files", $PROCESSED_RECURRING_EVENT, "INSERT") && ($EFILE_ID = $db->Insert_Id())) {
                                            last_updated("event", $recurring_event['event_id']);

                                            if ((is_dir(FILE_STORAGE_PATH)) && (is_writable(FILE_STORAGE_PATH))) {
                                                if(file_exists(FILE_STORAGE_PATH."/".$EFILE_ID)) {
                                                    application_log("notice", "File ID [".$EFILE_ID."] already existed and was overwritten with newer file.");
                                                }

                                                if (copy($source_file_path, FILE_STORAGE_PATH."/".$EFILE_ID)) {
                                                    application_log("success", "File ID ".$EFILE_ID." was successfully added to the database and filesystem for related recurring event [{$recurring_event['event_id']}].");
                                                } else {
                                                    $modal_onload[]	= "alert('The new file was not successfully saved for a related recurring event. The MEdTech Unit has been informed of this error, please try again later.')";
                                                    application_log("error", "The move_uploaded_file function failed to move temporary file over to final location for related recurring event.");
                                                }
                                            } else {
                                                $modal_onload[]	= "alert('The new file was not successfully saved for a related recurring event. The MEdTech Unit has been informed of this error, please try again later.')";
                                                application_log("error", "Either the FILE_STORAGE_PATH doesn't exist on the server or is not writable by PHP.");
                                            }
                                        } else {
                                            $modal_onload[]	= "alert('The new file was not successfully saved for a related recurring event. The MEdTech Unit has been informed of this error, please try again later.')";
                                            application_log("error", "Unable to insert the file into the database for related recurring event ID [{$recurring_event['event_id']}]. Database said: ".$db->ErrorMsg());
                                        }
                                    }
                                }

								if($ERROR) {
									$STEP = 1;
								}
							break;
							case 1 :
							default :
								continue;
							break;
						}

						// Display Add Step
						switch($STEP) {
							case 2 :
								$modal_onload[] = "parentReload()";
								?>
								<div class="modal-dialog" id="file-add-wizard">
									<div id="wizard">
										<h3 class="border-below">File Wizard <span class="content-small space-left large"><strong>Adding</strong> new event file</span></h3>
										<div id="body">
											<h2>File Added Successfully</h2>
		
											<div class="display-success">
												<?php if(!DEMO_MODE) { ?>
													You have successfully added <strong><?php echo html_encode($PROCESSED["file_title"]); ?></strong> to this event.
												<?php
												} else { ?>
													You are in demo mode therefore <strong><?php echo html_encode($PROCESSED["file_title"]); ?></strong> as been replaced with our default demo file and linked to this event.
												<?php } ?>
											</div>
		
											To <strong>add another file</strong> or <strong>close this window</strong> please use the buttons below.
										</div>
										<div id="footer">
											<input type="button" class="btn" value="Close" onclick="closeWizard()" style="float: left; margin: 4px 0px 4px 10px" />
											<input type="button" class="btn btn-primary" value="Add Another File" onclick="restartWizard('<?php echo ENTRADA_URL; ?>/api/file-wizard-event.api.php?id=<?php echo $EVENT_ID; ?>&amp;action=add')" style="float: right; margin: 4px 10px 4px 0px" />
										</div>
									</div>
								</div>
								<?php
								history_log($EVENT_ID, "added $PROCESSED[file_title] file.");
							break;
							case 1 :
							default :
								$modal_onload[] = "initStep(".$JS_INITSTEP.")";
/*

								if ($JS_INITSTEP == 3) {
									$modal_onload[] = "allowSubmit(0)";
								}
*/

								if((isset($_POST["timedrelease"])) && ($_POST["timedrelease"] == "yes")) {
									$modal_onload[] = "timedRelease('block')";
								} else {
									$modal_onload[] = "timedRelease('none')";
								}
								?>
								<div class="modal-dialog" id="file-add-wizard">
									<div id="wizard">
										<form id="wizard-form" target="upload-frame" action="<?php echo ENTRADA_URL; ?>/api/file-wizard-event.api.php?action=add&amp;id=<?php echo $EVENT_ID; ?>&amp;step=2" method="post" enctype="multipart/form-data" style="display: inline">
										<input type="hidden" name="MAX_UPLOAD_FILESIZE" value="<?php echo MAX_UPLOAD_FILESIZE; ?>" />
										<h3 class="border-below">File Wizard <span class="content-small space-left large"><strong>Adding</strong> new event file</span></h3>
										<div id="body">
											<h2 id="step-title"></h2>
                                            
                                            <?php if ($recurring_events) { ?>
                                            <div id="step1" style="display:none">
                                                <div class="wizard-question">
                                                    <div style="font-size: 14px">Select the recurring events you would like to copy this file to.</div>

                                                    <?php foreach ($recurring_events as $recurring_event) { ?>
                                                    <div style="padding-left: 65px">
                                                        <label class="checkbox">
                                                            <input type="checkbox" id="recurring_event_<?php echo $recurring_event["event_id"] ?>" class="recurring_events" name="recurring_event_ids[]" value="<?php echo $recurring_event["event_id"]; ?>"<?php echo (!isset($_POST["recurring_event_ids"]) || in_array($recurring_event["event_id"], $_POST["recurring_event_ids"]) ? " checked=\"checked\"" : ""); ?> />
                                                            <?php echo html_encode($recurring_event["event_title"]); ?>
                                                            on <?php echo html_encode(date(DEFAULT_DATE_FORMAT, $recurring_event["event_start"])); ?>
                                                        </label>
                                                    </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <?php } ?>
                                            
											<div id="step<?php echo $recurring_events ? 2 : 1; ?>" style="display: none">
												<div id="q1" class="wizard-question<?php echo ((in_array("q1", $ERRORSTR)) ? " display-error" : ""); ?>">
													<div style="font-size: 14px">What type of file are you adding?</div>
													<div style="padding-left: 65px">
														<?php
														if(@count($RESOURCE_CATEGORIES["event"])) {
															foreach($RESOURCE_CATEGORIES["event"] as $key => $value) {
																echo "<input type=\"radio\" id=\"file_category_".$key."\" name=\"file_category\" value=\"".$key."\" style=\"vertical-align: middle\"".((isset($PROCESSED["file_category"])) ? (($PROCESSED["file_category"] == $key) ? " checked=\"checked\"" : "") : (($key == "other") ? " checked=\"checked\"" : ""))." /> <label for=\"file_category_".$key."\">".$value."</label><br />";
															}
														}
														?>
													</div>
												</div>
	
												<div id="q1b" class="wizard-question<?php echo ((in_array("q1b", $ERRORSTR)) ? " display-error" : ""); ?>">
													<div style="font-size: 14px">How do you want people to view this file?</div>
													<div style="padding-left: 65px">
														<input type="radio" id="access_method_0" name="access_method" value="0" style="vertical-align: middle"<?php echo (((!isset($PROCESSED["access_method"])) || ((isset($PROCESSED["access_method"])) && (!(int) $PROCESSED["access_method"]))) ? " checked=\"checked\"" : ""); ?> /> <label for="access_method_0">Download it to their computer first, then open it.</label><br />
														<input type="radio" id="access_method_1" name="access_method" value="1" style="vertical-align: middle"<?php echo (((isset($PROCESSED["access_method"])) && ((int) $PROCESSED["access_method"])) ? " checked=\"checked\"" : ""); ?> /> <label for="access_method_1">Attempt to view it directly in the web-browser.</label><br />
													</div>
												</div>
	
												<div id="q2" class="wizard-question<?php echo ((in_array("q2", $ERRORSTR)) ? " display-error" : ""); ?>">
													<div style="font-size: 14px">Should viewing this file be considered optional or required?</div>
													<div style="padding-left: 65px">
														<input type="radio" id="required_no" name="required" value="no"<?php echo (((!isset($PROCESSED["required"])) || (!$PROCESSED["required"])) ? " checked=\"checked\"" : ""); ?> /> <label for="required_no">optional</label><br />
														<input type="radio" id="required_yes" name="required" value="yes"<?php echo (($PROCESSED["required"] == 1) ? " checked=\"checked\"" : ""); ?> /> <label for="required_yes">required</label><br />
													</div>
												</div>
	
												<div id="q3" class="wizard-question<?php echo ((in_array("q3", $ERRORSTR)) ? " display-error" : ""); ?>">
													<div style="font-size: 14px">When should this resource be used by the learner?</div>
													<div style="padding-left: 65px">
														<?php
														if(@count($RESOURCE_TIMEFRAMES["event"])) {
															foreach($RESOURCE_TIMEFRAMES["event"] as $key => $value) {
																echo "<input type=\"radio\" id=\"timeframe_".$key."\" name=\"timeframe\" value=\"".$key."\" style=\"vertical-align: middle\"".((isset($PROCESSED["timeframe"])) ? (($PROCESSED["timeframe"] == $key) ? " checked=\"checked\"" : "") : (($key == "none") ? " checked=\"checked\"" : ""))." /> <label for=\"timeframe_".$key."\">".$value."</label><br />";
															}
														}
														?>
													</div>
												</div>
											</div>
	
											<div id="step<?php echo $recurring_events ? 3 : 2; ?>" style="display: none">
												<div id="q4" class="wizard-question<?php echo ((in_array("q4", $ERRORSTR)) ? " display-error" : ""); ?>">
													<div style="font-size: 14px">Would you like to add timed release dates to this file?</div>
													<div style="padding-left: 65px">
														<input type="radio" id="timedrelease_no" name="timedrelease" value="no" onclick="timedRelease('none')"<?php echo (((!isset($_POST["timedrelease"])) || ($_POST["timedrelease"] == "no")) ? " checked=\"checked\"" : ""); ?> /> <label for="timedrelease_no">No, this file is accessible any time.</label><br />
														<input type="radio" id="timedrelease_yes" name="timedrelease" value="yes" onclick="timedRelease('block')"<?php echo (((isset($_POST["timedrelease"])) && ($_POST["timedrelease"] == "yes")) ? " checked=\"checked\"" : ""); ?> /> <label for="timedrelease_yes">Yes, this file has timed release information.</label><br />
													</div>
	
													<div id="timed-release-info" style="display: none">
														<br />
														By checking the box on the left, you will enable the ability to select release / revoke dates and times for this file.
														<br /><br />
														<table style="width: 100%" cellspacing="0" cellpadding="4" border="0" summary="Timed Release Information">
														<colgroup>
															<col style="width: 3%" />
															<col style="width: 30%" />
															<col style="width: 67%" />
														</colgroup>
														<?php echo generate_calendars("valid", "Accessible", true, false, ((isset($PROCESSED["release_date"])) ? $PROCESSED["release_date"] : 0), true, false, ((isset($PROCESSED["release_until"])) ? $PROCESSED["release_until"] : 0), true, true); ?>
														</table>
													</div>
												</div>
											</div>
	
											<div id="step<?php echo $recurring_events ? 4 : 3; ?>" style="display: none">
												<div id="q5" class="wizard-question<?php echo ((in_array("q5", $ERRORSTR)) ? " display-error" : ""); ?>">
													<div style="font-size: 14px">Please select the file to upload from your computer:</div>
													<div style="padding-left: 65px; padding-right: 10px; padding-top: 10px">
														<input type="file" id="filename" name="filename" value="" size="25" onchange="grabFilename()" /><br /><br />
														<?php
														if((isset($PROCESSED["file_name"])) && (!in_array("q5", $ERRORSTR))) {
															echo "<div class=\"display-notice\" style=\"margin-bottom: 0px\">Since there was an error in your previous request, you will need to re-select the local file from your computer in order to upload it. We apologize for the inconvenience; however, this is a security precaution.</div>";
														} else {
															echo "<span class=\"content-small\"><strong>Notice:</strong> The maximum filesize of this file must be less than ".readable_size(MAX_UPLOAD_FILESIZE).". If this file is larger than ".readable_size(MAX_UPLOAD_FILESIZE).", you will need to either compress it or split the file up into smaller files, otherwise the upload will fail.</span>";
														}
														?>
													</div>
												</div>
												<div id="q6" class="wizard-question<?php echo ((in_array("q6", $ERRORSTR)) ? " display-error" : ""); ?>">
													<div style="font-size: 14px">You can <span style="font-style: oblique">optionally</span> provide a different title for this file.</div>
													<div style="padding-left: 65px; padding-right: 10px; padding-top: 10px">
														<label for="file_title" class="form-required">File Title:</label> <span class="content-small"><strong>Example:</strong> Video Of Procedure 1</span><br />
														<input type="text" id="file_title" name="file_title" value="<?php echo ((isset($PROCESSED["file_title"])) ? html_encode($PROCESSED["file_title"]) : ""); ?>" maxlength="128" style="width: 350px;" />
													</div>
												</div>
												<div id="q7" class="wizard-question<?php echo ((in_array("q7", $ERRORSTR)) ? " display-error" : ""); ?>">
													<div style="font-size: 14px">You <span style="font-style: oblique">must</span> provide a description for this file as well.</div>
													<div style="padding-left: 65px; padding-right: 10px; padding-top: 10px">
														<label for="file_notes" class="form-required">File Description:</label><br />
														<textarea id="file_notes" name="file_notes" style="width: 350px; height: 75px"><?php echo ((isset($PROCESSED["file_notes"])) ? html_encode($PROCESSED["file_notes"]) : ""); ?></textarea>
													</div>
												</div>
	<!--
												<div id="q8" class="wizard-question<?php echo ((in_array("q8", $ERRORSTR)) ? " display-error" : ""); ?>">
													<div style="font-size: 14px">Before continuing, you <span style="font-style: oblique">must</span> confirm that you have permission to upload this file.</div>
													<div style="padding-left: 65px; padding-right: 10px; padding-top: 10px">
														<input type="checkbox" value="1" id="copyright_check" name="copyright_check" onclick="allowSubmit(this.checked)" />
														<label for="file_notes" class="form-required">I assert that there are no copyright violations in the selected materials.</label>
													</div>
												</div>
	-->
											</div>
										</div>
										<div id="footer">
											<input type="button" class="btn" value="Close" onclick="closeWizard()" style="float: left; margin: 4px 0px 4px 10px" />
											<input type="button" class="btn btn-primary" id="next-button" value="Next Step" onclick="nextStep()" style="float: right; margin: 4px 10px 4px 0px" />
											<input type="button" class="btn" id="back-button" value="Previous Step" onclick="prevStep()" style="display: none; float: right; margin: 4px 10px 4px 0px" />
										</div>
										</form>
										<div id="uploading-window" style="width: 100%; height: 100%;">
											<div style="display: table; width: 100%; height: 100%; _position: relative; overflow: hidden">
												<div style=" _position: absolute; _top: 50%; display: table-cell; vertical-align: middle;">
													<div style="_position: relative; _top: -50%; width: 100%; text-align: center">
														<span style="color: #003366; font-size: 18px; font-weight: bold">
															<img src="<?php echo ENTRADA_URL; ?>/images/loading.gif" width="32" height="32" alt="File Uploading" title="Please wait while this file is being uploaded." style="vertical-align: middle" /> Please Wait: this file is being uploaded.
														</span>
														<br /><br />
														This can take time depending on your connection speed and the filesize.
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
								<?php
							break;
						}
					break;
				}
				?>
				<div id="scripts-on-open" style="display: none;">
				<?php
					foreach ($modal_onload as $string) {
						echo $string.";\n";
					}
				?>
				</div>
				<?php
			}
		} else {
			$ERROR++;
			$ERRORSTR[] = "The provided event identifier does not exist in this system.";

			echo display_error();

			application_log("error", "File wizard was accessed without a valid event id.");
		}
	} else {
		$ERROR++;
		$ERRORSTR[] = "You must provide an event identifier when using the file wizard.";

		echo display_error();

		application_log("error", "File wizard was accessed without any event id.");
	}
}