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
	if (!isset($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"]) || !isset($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["so"])) {
		$_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"] = "student";
		$_SESSION[APPLICATION_IDENTIFIER]["assignments"]["so"] = "asc";
	}

	if (isset($_GET["sb"]) && $tmp_sb = clean_input($_GET["sb"],array("trim","notags"))) {
		if (in_array(strtolower($tmp_sb),array("student", "submitted", "grade", "number"))) {
			$_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"] = $tmp_sb;
		}
	}

	if (isset($_GET["so"]) && $tmp_so = clean_input($_GET["so"],array("trim","notags"))) {
		if (in_array(strtolower($tmp_so),array("desc", "asc"))) {
			$_SESSION[APPLICATION_IDENTIFIER]["assignments"]["so"] = $tmp_so;
		}
	}

	/**
	 * Add PlotKit to the beginning of the $HEAD array.
	 */
	array_unshift($HEAD,
		"<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/MochiKit/MochiKit.js\"></script>",
		"<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/PlotKit/excanvas.js\"></script>",
		"<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/PlotKit/Base.js\"></script>",
		"<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/PlotKit/Layout.js\"></script>",
		"<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/PlotKit/Canvas.js\"></script>",
		"<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/PlotKit/SweetCanvas.js\"></script>",
		"<script type=\"text/javascript\" src=\"".ENTRADA_URL."/javascript/PlotKit/EasyPlot.js\"></script>"
		);
		
	if ($COURSE_ID) {
		$query = "SELECT * FROM `courses` 
					WHERE `course_id` = ".$db->qstr($COURSE_ID)."
					AND `course_active` = '1'";
		$course_details	= $db->GetRow($query);
		
		if ($course_details && $ENTRADA_ACL->amIAllowed(new GradebookResource($course_details["course_id"], $course_details["organisation_id"]), "read")) {			

			$query = "  SELECT a.*, b.`cohort`, c.`id` AS `marking_scheme_id`, c.`handler`, c.`description` as `marking_scheme_description`
                        FROM `assignments` AS a
                        JOIN `assessments` AS b
                        ON a.`assessment_id` = b.`assessment_id`
                        LEFT JOIN `assessment_marking_schemes` AS c
                        ON c.`id` = b.`marking_scheme_id`
                        WHERE a.`assignment_id` = ".$db->qstr($ASSIGNMENT_ID)."
                        AND b.`active` = 1
                        AND a.`assignment_active` = '1'";
			$assignment = $db->GetRow($query);
			if ($assignment) {
				if (isset($_GET["action"]) && $_GET["action"] == "import-zip") {
					$assignment_object = Models_Assignment::fetchRowByID($ASSIGNMENT_ID);
					if ($assignment_object) {
						if (isset($_FILES["assignment_archive"])) {
							switch ($_FILES["assignment_archive"]["error"]) {
								case 0 :
									$VALID_MAX_FILESIZE		= 31457280; // 30MB
									if (($file_filesize = (int)trim($_FILES["assignment_archive"]["size"])) <= $VALID_MAX_FILESIZE) {
											$PROCESSED["file_version"] = 1;
											$PROCESSED["file_mimetype"] = strtolower(trim($_FILES["assignment_archive"]["type"]));
											$PROCESSED["file_filesize"] = $file_filesize;
											$PROCESSED["file_filename"] = useable_filename(trim($_FILES["assignment_archive"]["name"]));

										if ((!defined("FILE_STORAGE_PATH")) || (!@is_dir(FILE_STORAGE_PATH)) || (!@is_writable(FILE_STORAGE_PATH))) {
											add_error("There is a problem with the document storage directory on the server; the MEdTech Unit has been informed of this error, please try again later.");

											application_log("error", "The event document storage path [" . FILE_STORAGE_PATH . "] does not exist or is not writable.");
										} else {
											/**
											@TODO: Handle uploading files for assignments with multiple file uploads enabled. As per
											 * line 54 of the download-submission.inc.php (same directory as this), it expects there
											 * to be a zip file containing all the assignment files for each learner when it is
											 * attempting to download their assignment.
											 */
											if((@file_exists($_FILES["assignment_archive"]["tmp_name"])) && (@is_readable($_FILES["assignment_archive"]["tmp_name"]))) {
												$assignments_archive = new ZipArchive();
												$result = $assignments_archive->open($_FILES["assignment_archive"]["tmp_name"]);
												$number_of_files = $assignments_archive->numFiles;
												for ($i = 0; $i < $number_of_files; $i++) {
													if (strpos($assignments_archive->getNameIndex($i), "__MACOSX") !== 0) {
														$extension = pathinfo($assignments_archive->getNameIndex($i), PATHINFO_EXTENSION);
														$email_matches = array();
														preg_match_all("/[\.a-zA-Z0-9-+]+@[\.a-zA-Z0-9-]+/i", $assignments_archive->getNameIndex($i), $email_matches);
														$file = $assignments_archive->getFromIndex($i);
														if (is_array($email_matches) && @count($email_matches)) {
															if (isset($email_matches[0][0]) && ($email = str_replace($extension, "", $email_matches[0][0]))) {
																$email = rtrim($email, ".");
																$user = Models_User::fetchRowByEmail($email);
																if ($user) {
																	$assignment_files = Models_Assignment_File::fetchAllByAssignmentIDProxyID($ASSIGNMENT_ID, $user->getID());
																	if (@count($assignment_files) < $assignment_object->getMaxFileUploads()) {
																		$assignment_file = array();
																		$assignment_file["file_version"] = 1;
																		$assignment_file["file_type"] = "submission";
																		$assignment_file["file_title"] = useable_filename(trim($assignments_archive->getNameIndex($i)));
																		$assignment_file["assignment_id"] = $ASSIGNMENT_ID;
																		$assignment_file["proxy_id"] = $user->getID();
																		$assignment_file["file_active"] = 1;
																		$assignment_file["updated_date"] = time();
																		$assignment_file["updated_by"] = $ENTRADA_USER->getActiveID();
																		$assignment_file = new Models_Assignment_File($assignment_file);

																		if (!$assignment_file->insert()) {
																			add_error("Unable to create assignment file record.");
																		} else {
																			$assignment_file_version_array = $assignment_file->toArray();
																			$last_file_version = Models_Assignment_File_Version::fetchMostRecentByAFileID($assignment_file->getID());

																			if ($last_file_version) {
																				$assignment_file_version_array["file_version"] = $last_file_version->getFileVersion() + 1;
																			} else {
																				$assignment_file_version_array["file_version"] = 1;
																			}

																			$finfo = finfo_open(FILEINFO_MIME);
																			$mimetype = finfo_buffer($finfo, $file);
																			$assignment_file_version_array["file_mimetype"] = $mimetype;
																			$assignment_file_version_array["file_filesize"] = strlen($file);
																			$assignment_file_version_array["file_filename"] = useable_filename(trim($assignments_archive->getNameIndex($i)));
																			$assignment_file_version_array["updated_date"] = time();
																			$assignment_file_version_array["updated_by"] = $ENTRADA_USER->getActiveId();
																			$assignment_file_version = new Models_Assignment_File_Version($assignment_file_version_array);
																			if ($assignment_file_version->insert()) {
																				file_put_contents(FILE_STORAGE_PATH . "/A" . $assignment_file_version->getID(), $file);
																			} else {
																				add_error("Unable to create assignment file version record.");
																			}
																		}
																	} else {
																		add_error("The uploaded zip file contained more files than " . $assignment_object->getMaxFileUploads() . " for some students");
																	}
																}
															}
														}
													}
												}
											} else {
												add_error("There is a problem with the uploaded file; the MEdTech Unit has been informed of this error, please try again later.");

												application_log("error", "The uploaded file [" . $PROCESSED["file_filename"] . "] does not exist or is not readable.");
											}
										}
									}
									break;
								case 1 :
								case 2 :
									add_error("The file that was uploaded is larger than " . readable_size($VALID_MAX_FILESIZE) . ". Please make the file smaller and try again.");
									break;
								case 3 :
									add_error("The file that was uploaded did not complete the upload process or was interrupted; please try again.");
									break;
								case 4 :
									add_error("You did not select a file from your computer to upload. Please select a local file and try again.");
									break;
								case 6 :
								case 7 :
									add_error("Unable to store the new file on the server; the MEdTech Unit has been informed of this error, please try again later.");

									application_log("error", "Community file upload error: " . (($_FILES["filename"]["error"] == 6) ? "Missing a temporary folder." : "Failed to write file to disk."));
									break;
								default :
									application_log("error", "Unrecognized file upload error number [" . $_FILES["filename"]["error"] . "].");
									break;
							}
						}
					} else {
						echo "Assignment with provided ID not found.";
					}
				}


				/**
				 * Sidebar item that will provide another method for sorting, ordering, etc.
				 */
				$sidebar_html  = "Sort columns:\n";
				$sidebar_html .= "<ul class=\"menu none\">\n";
				$sidebar_html .= "	<li><a href=\"".ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("sb" => "number"))."\" title=\"Sort by Student Number &amp; Time\"><img src=\"".ENTRADA_URL."/images/checkbox-".((strtolower($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"]) == "number") ? "on" : "off").".gif\" alt=\"\" /> <span>by student number</span></a></li>\n";
				if (!isset($assignment["anonymous_marking"]) || !$assignment["anonymous_marking"]) {
					$sidebar_html .= "	<li><a href=\"" . ENTRADA_URL . "/admin/gradebook/assignments?" . replace_query(array("sb" => "student")) . "\" title=\"Sort by Student Name &amp; Time\"><img src=\"" . ENTRADA_URL . "/images/checkbox-" . ((strtolower($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"]) == "student") ? "on" : "off") . ".gif\" alt=\"\" /> <span>by student name</span></a></li>\n";
				} elseif ($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"] == "student") {
					$_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"] = "number";
				}
				$sidebar_html .= "	<li><a href=\"".ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("sb" => "grade"))."\" title=\"Sort by Mark\"><img src=\"".ENTRADA_URL."/images/checkbox-".((strtolower($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"]) == "grade") ? "on" : "off").".gif\" alt=\"\" /> <span>by student mark</span></a></li>\n";
				$sidebar_html .= "	<li><a href=\"".ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("sb" => "submitted"))."\" title=\"Sort by Submission Date\"><img src=\"".ENTRADA_URL."/images/checkbox-".((strtolower($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"]) == "submitted") ? "on" : "off").".gif\" alt=\"\" /> <span>by submission date</span></a></li>\n";
				$sidebar_html .= "</ul>\n";
				$sidebar_html .= "Order columns:\n";
				$sidebar_html .= "<ul class=\"menu\">\n";
				$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["so"]) == "asc") ? "on" : "off")."\"><a href=\"".ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("so" => "asc"))."\" title=\"Ascending Order\">in ascending order</a></li>\n";
				$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["so"]) == "desc") ? "on" : "off")."\"><a href=\"".ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("so" => "desc"))."\" title=\"Descending Order\">in descending order</a></li>\n";
				$sidebar_html .= "</ul>\n";

				new_sidebar_item("Sort Results", $sidebar_html, "sort-results", "open");
                $BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/gradebook/assessments?".replace_query(array("section" => "grade", "id" => $COURSE_ID, "step" => false)), "title" => $assignment["assignment_title"]);

				$COHORT = $assignment["cohort"];

				courses_subnavigation($course_details, "gradebook");

				echo "<div class=\"content-small\">";
				if ($COURSE_ID) {
					$curriculum_path = curriculum_hierarchy($COURSE_ID);
					if ((is_array($curriculum_path)) && (count($curriculum_path))) {
						echo implode(" &gt; ", $curriculum_path);
					}
				} else {
					echo "No Associated Course";
				}
				echo "</div>\n";
				?>
				<style type="text/css">
				.sortableList li {
					width: 100%;
				}	
				</style>

				<h1 class="event-title"><?php echo $assignment["assignment_title"]; ?></h1>

				<?php
				$order = strtoupper($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["so"]);
				$orders = array("student" => "desc", "grade" => "desc", "submitted" => "desc");
                
				switch ($_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"]) {
					case "number":
						$by = "a.`number`";
						if ($order == "DESC") {
							$orders["student"] = "asc";
						}
					break;
					case "student":
						$by = "a.`lastname`";
						if ($order == "DESC") {
							$orders["student"] = "asc";
						}
					break;
					case "grade":
						$by = "`grade_value`";
						if ($order == "DESC") {
							$orders["grade"] = "asc";
						}						
					break;
					case "submitted":						
						$by = "`submitted_date`";
						if ($order == "DESC") {
							$orders["submitted"] = "asc";
						}						
					break;
					default:
						$by = "`submitted_date`";
						if ($order == "DESC") {
							$orders["submitted"] = "asc";
						}			

						$_SESSION[APPLICATION_IDENTIFIER]["assignments"]["sb"] = "submitted";
					break;
				}				

				$query = "	SELECT a.`id` AS `proxy_id`, CONCAT_WS(', ', a.`lastname`, a.`firstname`) AS `fullname`, a.`number` as `student_number`, c.`assessment_id`, MAX(b.`updated_date`) AS `submitted_date`, d.`grade_id`, d.`value` AS `grade_value`, f.`handler`, g.`grade_weighting`
							FROM `".AUTH_DATABASE."`.`user_data` AS a
							JOIN `assignment_files` AS b
							ON a.`id` = b.`proxy_id`
							AND b.`file_type` = 'submission'
							AND b.`assignment_id` = ".$db->qstr($ASSIGNMENT_ID)."
							JOIN `assignments` AS c
							ON b.`assignment_id` = c.`assignment_id`
							AND c.`assignment_active` = '1'
							JOIN `assessments` AS e
							ON e.`assessment_id` = c.`assessment_id`
							AND e.`active` = 1
							LEFT JOIN `assessment_grades` AS d 
							ON d.`assessment_id` = e.`assessment_id`
							AND d.`proxy_id` = a.`id`
							JOIN `assessment_marking_schemes` AS f
							ON e.`marking_scheme_id` = f.`id`
							LEFT JOIN `assessment_exceptions` AS g
							ON g.`assessment_id` = d.`assessment_id`
							AND g.`proxy_id` = a.`id`
                            GROUP BY a.`id`
							ORDER BY ".$by." ".$order;			
				$students = $db->GetAll($query);

				$query = "	SELECT * FROM `assessments` AS a
							JOIN `assessment_marking_schemes` AS b
							ON a.`marking_scheme_id` = b.`id`
							WHERE a.`assessment_id` = ".$db->qstr($assignment["assessment_id"])."
							AND a.`active` = 1";
				$assessment = $db->GetRow($query);
				?>

                <div class="pull-right">
                    <?php
                    if (extension_loaded("zip")) {
                        ?>
                        <a href="<?php echo ENTRADA_URL; ?>/admin/gradebook/assignments?section=download-submissions&assignment_id=<?php echo (int) $assignment["assignment_id"]; ?>" class="btn"><i class="icon-download-alt"></i> Download All Submissions</a>
                        <?php
                    }

					if (isset($assessment) && $assessment) {
						?>
						<a href="#upload-submissions-modal" data-toggle="modal" class="btn"><i class="icon-upload"></i> Upload Zip of Submissions</a>
						<?php
					}

                    if ($ENTRADA_ACL->amIAllowed(new CourseContentResource($course_details["course_id"], $course_details["organisation_id"]), "update")) { 
                        ?>
                        <a href="<?php echo ENTRADA_URL; ?>/admin/gradebook/assignments?<?php echo replace_query(array("section" => "edit","assignment_id"=>$assignment["assignment_id"], "step" => false)); ?>" class="btn">Edit Assignment</a>
                        <?php
                    }

                    if (isset($assessment) && $assessment && isset($students) && !(empty($students))) { 
                        ?>
                        <a href="#" id="advanced-options"><button class="btn">Show Options</button></a>
                        <?php
                    }
                    ?>
                </div>
				<div id="upload-submissions-modal" class="modal hide fade">
					<form enctype="multipart/form-data" action="<?php echo ENTRADA_URL."/admin/gradebook/assignments?".replace_query(array("action" => "import-zip")); ?>" method="POST">
						<div class="modal-dialog">
							<div class="modal-content">
								<div class="modal-header">
									<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
									<h4 class="modal-title">Upload Learner Submissions</h4>
								</div>
								<div class="modal-body">
									<div id="upload-submissions-msgs"></div>
									<?php
									echo display_notice("Please ensure each file contained in the uploaded .zip archive contain the learner's <strong>e-mail address</strong> to indicate who it should be attributed to.");
									?>
									<input type="file" name="assignment_archive" />
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
									<button id="upload-submissions" type="submit" class="btn btn-primary">Submit</button>
								</div>
							</div>
						</div>
					</form>
				</div>
				<div class="clearfix"></div>
				<?php				
				$editable = $ENTRADA_ACL->amIAllowed(new GradebookResource($course_details["course_id"], $course_details["organisation_id"]), "update") ? "gradebook_editable" : "gradebook_not_editable";
				if ($students && !empty($students)) {
                    ?>
					<span id="assessment_name" style="display: none;"><?php echo html_encode($assignment["assignment_title"]); ?></span>
                    <div class="row-fluid">
                        <span id="assignment_submissions" class="span12">
                            <h2>Submissions</h2>
                            <div style="margin-bottom: 5px;">
                                <span class="content-small"><strong>Tip: </strong><?php echo $assignment["marking_scheme_description"]; ?></span>
                            </div>
                            
                            <table class="gradebook assignment <?php echo $editable; ?>">                                
                                <tbody>
                                    <?php								
									$comment_colspan = 5;
                                    foreach ($students as $key => $student) {
                                        if (isset($student["grade_id"])) {
                                            $grade_id = $student["grade_id"];
                                        } else {
                                            $grade_id = "";
                                        }

                                        if (isset($student["grade_value"])) {
                                            $grade_value = format_retrieved_grade($student["grade_value"], $assessment);
                                        } else {
                                            $grade_value = "-";
                                        }

                                        if (isset($student["grade_weighting"]) && $student["grade_weighting"]) {
                                            $grade_weighting = $student["grade_weighting"];
                                        } else {
                                            $grade_weighting = $student["grade_weighting"];
                                        }
                                        ?>
                                        <tr id="grades<?php echo $student["proxy_id"]; ?>">
											<td><div class="text-center"><a href="<?php echo ENTRADA_URL; ?>/admin/gradebook/assignments?section=download-submission&id=<?php echo $COURSE_ID; ?>&assignment_id=<?php echo $ASSIGNMENT_ID; ?>&sid=<?php echo $student["proxy_id"]; ?>"><i class="icon-download-alt"></i></a></div></td>
											<td><a href="<?php echo ENTRADA_URL."/profile/gradebook/assignments?section=view&id=" .$COURSE_ID . "&assignment_id=".$ASSIGNMENT_ID."&pid=".$student["proxy_id"]; ?>"><?php echo (!isset($assignment["anonymous_marking"]) || !$assignment["anonymous_marking"] ? $student["fullname"] : "Anonymous Learner"); ?></a></td>
                                            <td><a href="<?php echo ENTRADA_URL."/profile/gradebook/assignments?section=view&id=" .$COURSE_ID . "&assignment_id=".$ASSIGNMENT_ID."&pid=".$student["proxy_id"]; ?>"><?php echo $student["student_number"]; ?></a></td>
                                            <td>
                                                <?php
                                                if (isset($assessment) && $assessment) {
                                                    ?>
                                                    <span class="grade" id="grade_<?php echo $assignment["assessment_id"]; ?>_<?php echo $student["proxy_id"] ?>"
                                                        data-grade-id="<?php echo $grade_id; ?>"
                                                        data-assessment-id="<?php echo $assignment["assessment_id"]; ?>"
                                                        data-proxy-id="<?php echo $student["proxy_id"] ?>"
                                                        style="float:left;"
                                                    ><?php echo $grade_value; ?></span>
                                                    <span class="gradesuffix" <?php echo (($grade_value === "-") ? "style=\"display: none;\"" : "") ?> style="float:left;">
                                                        <?php echo assessment_suffix($assessment); ?>
                                                    </span>
                                                    <span class="gradesuffix" style="float:right;">
                                                        <i class="icon-edit edit_grade" id="edit_grade_<?php echo $assignment["assessment_id"] . "_" . $student["proxy_id"] ?>"></i>
                                                    </span>
                                                    <?php
                                                } else {
                                                    ?>
                                                    No Assessment
                                                    <?php
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo date(DEFAULT_DATE_FORMAT, $student["submitted_date"]); ?></td>
                                            <?php
                                            if ($assessment["marking_scheme_id"] == 3) {
												$comment_colspan = 6;
                                                ?>
												<td id="percentage_<?php echo $assignment["assessment_id"]; ?>_<?php echo $student["proxy_id"] ?>"><?php echo round($student["grade_value"], 2); ?>%</td>
                                                <?php
                                            } 
                                            ?>
                                        </tr>
                                        <tr class="comment-row">
                                            <td colspan="<?php echo $comment_colspan; ?>" style="text-align:right;">												
												<a href="javascript:void(0);" class="view_comments" id="view_comments_<?php echo $student["proxy_id"] ?>">View Comments</a>&nbsp; <span class="leave_comment" id="leave_comment_<?php echo $student["proxy_id"] ?>">Leave Comment</span>
											</td>
										</tr>
										<tr>
											<td colspan="<?php echo $comment_colspan; ?>"> 
												<div style="width: 650px;">
													<ul class="comments" id="comments_<?php echo $student["proxy_id"] ?>">
														<?php 
														$query = "	SELECT a.*, CONCAT_WS(' ', c.`firstname`, c.`lastname`) AS `commenter_fullname`, c.`username` AS `commenter_username` 
																	FROM `assignment_comments` AS a 
																	LEFT JOIN `".AUTH_DATABASE."`.`user_data` AS c
																	ON a.`proxy_id` = c.`id` 
																	WHERE a.`assignment_id` = ".$db->qstr($ASSIGNMENT_ID)." 
                                                                    AND a.`proxy_to_id` = ".$db->qstr($student["proxy_id"])."
																	AND a.`comment_active` = '1'
																	ORDER BY a.`updated_date`";
														$comment_results = $db->GetAll($query);
														if ($comment_results) {
															foreach($comment_results as $result) {
																$comments++;
																?>
																<li>
																	<table style="width:100%;" class="discussions posts">
																		<tr>
																			<td style="border-bottom: none; border-right: none">
																				<?php
																				if (!isset($assignment["anonymous_marking"]) || !$assignment["anonymous_marking"] || $result["proxy_id"] == $ENTRADA_USER->getID()) {
																					?>
																					<span class="content-small">By:</span>
																					<a href="<?php echo ENTRADA_URL . "/people?profile=" . html_encode($result["commenter_username"]); ?>" style="font-size: 10px"><?php echo html_encode($result["commenter_fullname"]); ?></a>
																					<?php
																				} else {
																					?>
																					<span class="content-small">By:</span>
																					<span style="font-size: 10px">Anonymous Commenter</span>
																					<?php
																				}
																				?>
																			</td>
																			<td style="border-bottom: none">
																				<div style="float: left">
																					<span class="content-small"><strong>Commented:</strong> <?php echo date(DEFAULT_DATE_FORMAT, $result["release_date"]); ?></span>
																				</div>
																				<div style="float: right">
																					<?php
																					echo (($result["proxy_id"] == $ENTRADA_USER->getID()) ? " (<a class=\"action\" href=\"".ENTRADA_URL."/profile/gradebook/assignments?section=edit-comment&amp;id=" .$COURSE_ID . "&amp;assignment_id=".$assignment["assignment_id"]."&amp;cid=".$result["acomment_id"]."\">edit</a>)" : "");
																					echo (($result["proxy_id"] == $ENTRADA_USER->getID()) ? " (<a class= \"action delete\" id=\"delete_".$result["acomment_id"]."\" href=\"#delete_".$result["acomment_id"]."\">delete</a>)":"");// href=\"javascript:commentDelete('".$result["acomment_id"]."')\">delete</a>)" : "");
																					?>
																				</div>
																			</td>
																		</tr>
																		<tr>
																			<td colspan="2" class="content" style="border-bottom: 3px solid #EBEBEB;">
																			<a name="comment-<?php echo (int) $result["cscomment_id"]; ?>"></a>
																			<?php
																				echo ((trim($result["comment_title"])) ? "<strong>".html_encode(trim($result["comment_title"])) . "</strong><br />" : "");
																				echo $result["comment_description"];

																				if ($result["release_date"] != $result["updated_date"]) {
																					echo "<div class=\"content-small\" style=\"margin-top: 15px\">\n";
																					if (!isset($assignment["anonymous_marking"]) || !$assignment["anonymous_marking"] || $result["proxy_id"] == $ENTRADA_USER->getID()) {
																					echo "	<strong>Last updated:</strong> ".date(DEFAULT_DATE_FORMAT, $result["updated_date"])." by ".(($result["proxy_id"] == $result["updated_by"]) ? html_encode($result["commenter_fullname"]) : html_encode(get_account_data("firstlast", $result["updated_by"]))).".";
																					} else {
																						echo "	<strong>Last updated:</strong> ".date(DEFAULT_DATE_FORMAT, $result["updated_date"]).".";
																					}
																					echo "</div>\n";
																				}
																			?>
																			</td>
																		</tr>
																	</table>
																</li>
																<?php
															}
															
														} ?>													
														<li style="text-align:right;" class="list-action"><span class="leave_comment" id="leave_comment_<?php echo $student["proxy_id"] ?>">Leave Comment</span></li>
													</ul>													
													<div class="new_comment" id="new_comment_<?php echo $student["proxy_id"] ?>">
														<table class="comment_form" style="width:100%;">
															<tr>
																<td>
																	<label for="new_comment_title_<?php echo $student["proxy_id"] ?>">Comment Title:</label>
																</td>
															</tr>
															<tr>
																<td>
																	<input type="text" id="new_comment_title_<?php echo $student["proxy_id"] ?>" class="new_comment_text"/>
																</td>
															</tr>
															<tr>
																<td>
																	<label for="new_comment_desc_<?php echo $student["proxy_id"] ?>"  class="form-required">Comment Description:</label></td></tr>
															<tr><td>
																	<textarea id="new_comment_desc_<?php echo $student["proxy_id"] ?>" class="new_comment_text" rows="3"></textarea>
																</td>
															</tr>
														</table>
														<input type="button" value="Cancel" id="cancel_comment_<?php echo $student["proxy_id"] ?>" class="cancel_comment btn" style="margin-right:5px;"/><input type="button" class="add_comment btn btn-primary" value="Add Comment" id="add_comment_<?php echo $student["proxy_id"] ?>" style="float:right;"/>
													</div>
												</div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                            </table>					
                        </span>
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                $('.edit_grade').click(function(e) {
                                    var id = e.target.id.substring(5);
                                    $('#'+id).trigger('click');
                                });								
								$(".comments").each(function() {
									var c_id = $(this).attr("id").split("comments_")[1];									
									if ($(this).children().length <= 1) {		
										var no_comment = document.createElement("span");
										$(no_comment).attr("id", "no_comments_" + c_id);
										$(no_comment).text("No Comments");
										$('#view_comments_'+c_id).replaceWith(no_comment);																			
									} 
								});
                            });

                        </script>
                        <span id="gradebook_stats" class="span5" style="margin-top: 14px;">
                            <h2>Statistics</h2>
                            <div id="graph"></div>
							<script type="text/javascript">
								var marking_scheme_id = "<?php echo $assessment["marking_scheme_id"]; ?>";
							</script>
                            <?php 
                            switch($assessment["marking_scheme_id"]) {
                                case 1:
                                case 4:
                                    //pass/fail
                                    $grades = array(0,0,0);
                                    $unentered = 0;

                                    foreach ($students as $key => $student) {
                                        if ($student["grade_value"] == "") {
                                            $unentered++;
                                        } elseif ($student["grade_value"] > 50) {
                                            $grades[0]++;
                                        } else {
                                            $grades[1]++;
                                        }
                                    }

                                    $grade_data = array();
                                    foreach ($grades as $key => $grade) {
                                        $grade_data[] = "[$key, $grade]";
                                    }
                                    ?>
                                    <script type="text/javascript" charset="utf-8">
                                        var data = [<?php echo implode(", ", $grade_data); ?>];
                                        var plotter = PlotKit.EasyPlot(
                                            "pie",
                                            {
                                                "xTicks": [{v:0, label:"<?php echo $assignment["marking_scheme_id"] == 4 ? "Complete" : "Pass" ?>"},
                                                            {v:1, label:"<?php echo $assignment["marking_scheme_id"] == 4 ? "Incomplete" : "Fail" ?>"}]
                                            },
                                            $("graph"),
                                            [data]
                                        );
                                    </script>
                                    <br />
                                    <p>Unentered Grades: <?php echo (int) $unentered; ?></p>
                                    <?php
                                break;
                                case 2:
                                case 3:
                                    // Percentage (numeric interpreted as percentage)
                                    $grades = array(0,0,0,0,0,0,0,0,0,0,0);

                                    $sum = 0;
                                    $entered = 0;
                                    $unentered = 0;

                                    $grade_values = array();
                                    foreach ($students as $key => $student) {
                                        if ($student["grade_value"] == "") {
                                            //$grades[11]++;
                                            $unentered++;
                                        } else {
                                            $sum += $student["grade_value"];
                                            $entered++;
                                            $grade_values[] = $student["grade_value"];

                                            $key = floor($student["grade_value"] / 10);
                                            $grades[$key]++;
                                        }
                                    }

                                    $grade_data = array();
                                    foreach ($grades as $key => $grade) {
                                        $grade_data[] = "[$key, $grade]";
                                    }
                                    sort($grade_values);
                                    ?>
                                    <script type="text/javascript" charset="utf-8">
                                        var data = [<?php echo implode(", ", $grade_data); ?>];
                                        var plotter = PlotKit.EasyPlot(
                                            "bar",
                                            {
                                                "xTicks": [{v:0, label:"0s"},
                                                            {v:1, label:"10s"},
                                                            {v:2, label:"20s"},
                                                            {v:3, label:"30s"},
                                                            {v:4, label:"40s"},
                                                            {v:5, label:"50s"},
                                                            {v:6, label:"60s"},
                                                            {v:7, label:"70s"},
                                                            {v:8, label:"80s"},
                                                            {v:9, label:"90s"},
                                                            {v:10, label:"100"}]
                                            },
                                            $("graph"),
                                            [data]
                                        );
                                    </script>
                                    <br />
                                    <p>Unentered Grades: <?php echo (int) $unentered; ?></p>
                                    <p>Mean grade: <?php echo number_format(($entered > 0 ? $sum / $entered : 0), 0); ?>%</p>
                                    <p>Median grade: <?php echo $grade_values[floor(count($grade_values) / 2)]; ?>%</p>
                                    <?php
                                break;
                                default:
                                    echo "No statistics for this marking scheme.";
                                break;
                            }
                            ?>
                            <button class="btn" onclick="window.location='<?php echo ENTRADA_URL."/admin/".$MODULE."?".replace_query(array("section" => "io", "download" => "csv", "assessment_ids" => $assignment["assessment_id"])); ?>'">Download CSV</button>
                            <button class="btn" onclick="location.reload(true)">Refresh</button>
                            <div style="margin-top: 40px;">
                                <h2>Grade Calculation Exceptions</h2>
                                <p>
                                    You can use the following exception creator to modify the calculations used to create the students final grade in this course. 
                                </p>

                                <label for="student_exceptions" class="form-required">Student Name</label>
                                <select name="student_exceptions" id="student_exceptions" style="width: 210px;" onchange="add_exception(this.options[this.selectedIndex].value, '<?php echo $assignment["assessment_id"]; ?>')">
                                <option value="0">-- Select A Student --</option>
                                    <?php
                                    foreach ($students as $student) {
                                        if (!isset($student["grade_weighting"]) || $student["grade_weighting"] == NULL) {
                                            echo "<option value=\"".$student["proxy_id"]."\">".$student["fullname"]."</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <br /><br /><br />
                                <script type="text/javascript">
                                var updating = false;
                                function delete_exception (proxy_id, assessment_id) {

                                    var anOption = document.createElement('option');
                                    anOption.value = proxy_id;
                                    anOption.innerHTML = $(proxy_id+'_name').innerHTML;
                                    $('student_exceptions').appendChild(anOption);

                                    new Ajax.Updater('exception_container', '<?php echo ENTRADA_URL; ?>/api/assessment-weighting-exception.api.php', 
                                        {
                                            method:	'post',
                                            parameters: 'remove=1&assessment_id='+assessment_id+'&proxy_id='+proxy_id
                                        }
                                    );
                                }

                                function modify_exception (proxy_id, assessment_id) {
                                    if (!updating) {
                                        updating = true;
                                        setTimeout('modify_exception_ajax('+proxy_id+', '+assessment_id+')', 2000);
                                    }

                                }

                                function modify_exception_ajax(proxy_id, assessment_id) {
                                    var grade_weighting = $('student_exception_'+proxy_id).value;
                                    new Ajax.Updater('exception_container', '<?php echo ENTRADA_URL; ?>/api/assessment-weighting-exception.api.php', 
                                        {
                                            method:	'post',
                                            parameters: 'assessment_id='+assessment_id+'&proxy_id='+proxy_id+'&grade_weighting='+grade_weighting,
                                            onComplete: function () {
                                                $('student_exception_'+proxy_id).focus();
                                                updating = false;
                                            }
                                        }
                                    )
                                }

                                function add_exception (proxy_id, assessment_id) {
                                    new Ajax.Updater('exception_container', '<?php echo ENTRADA_URL; ?>/api/assessment-weighting-exception.api.php', 
                                        {
                                            method:	'post',
                                            parameters: 'assessment_id='+assessment_id+'&proxy_id='+proxy_id+'&grade_weighting=0'
                                        }
                                    );
                                    var children = $('student_exceptions').childNodes;
                                    var numchildren = children.length;

                                    for (var i = 0; i < numchildren; i++) {
                                        if (children[i].value == proxy_id) {
                                            $('student_exceptions').removeChild(children[i]);
                                            break;
                                        }
                                    }
                                }
                                </script>
                                <h3>Students with modified weighting:</h3>
                                <ol id="exception_container" class="sortableList">
                                    <?php
                                    $exceptions_exist = false;
                                    foreach ($students as $student) {
                                        if (isset($student["grade_weighting"]) && $student["grade_weighting"] !== NULL) {
                                            $exceptions_exist = true;
                                            echo "<li id=\"proxy_".$student["proxy_id"]."\"><span id=\"".$student["proxy_id"]."_name\">".$student["fullname"]."</span>
                                                <a style=\"cursor: pointer;\" onclick=\"delete_exception('".$student["proxy_id"]."', '".$assignment["assessment_id"]."');\" class=\"remove\">
                                                    <img src=\"".ENTRADA_URL."/images/action-delete.gif\">
                                                </a>
                                                <span class=\"duration_segment_container\">
                                                    Weighting: <input class=\"duration_segment\" id=\"student_exception_".$student["proxy_id"]."\" name=\"student_exception[]\" onkeyup=\"modify_exception('".$student["proxy_id"]."', '".$assessment["assessment_id"]."', this.value);\" value=\"".$student["grade_weighting"]."\">
                                                </span>
                                            </li>";
                                        }
                                    }
                                    if (!$exceptions_exist) {
                                        echo "<div class=\"display-notice\">There are currently no students with custom grade weighting in the system for this assessment.</div>";
                                    }
                                    ?>
                                </ol>
                            </div>
                                <br />
                            <h2>Import Grades</h2>
                            <div id="display-notice-box" class="display-notice">
                                    <strong>Important Notes:</strong>
                                    <br />Format for the CSV should be [Student Number, Grade] with each entry on a separate line (without the brackets). 
                                    <br />Any grades entered will be overwritten if present in the CSV.
                            </div>
                            <form enctype="multipart/form-data" action="<?php echo ENTRADA_URL."/admin/".$MODULE."?".replace_query(array("section" => "csv-upload", "assessment_id" => $assignment["assessment_id"])); ?>" method="POST">
                                <input type="file" name ="file"/><br /><br />
                                <input type="submit" class="btn btn-primary" value ="Import CSV"/>
                            </form>
                        </span>
                    </div>
					<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery('.comments').hide();
						jQuery('.new_comment').hide();
						jQuery('#gradebook_stats').hide();
						jQuery('#advanced-options').click(function() {
							if (jQuery('#gradebook_stats').is(":visible")) {
								jQuery('#assignment_submissions').attr('class','span12');
								jQuery('#gradebook_stats').hide();
								jQuery('#advanced-options').html('<button class="btn">Show Options</button>');
							} else {
								jQuery('#assignment_submissions').attr('class','span7');
								jQuery('#gradebook_stats').show();	
                                jQuery('#advanced-options').html('<button class="btn">Hide Options</button>');
                            }
						});
						
						jQuery('.add_comment').click(function() {
							var id = jQuery(this).attr('id').substring(12);
							//var comment_desc = tinyMCE.get('new_comment_desc_'+id).getContent();
							var comment_desc = jQuery('#new_comment_desc_'+id).val();
							var comment_title = jQuery('#new_comment_title_'+id).val();
							jQuery.ajax({
								type: "POST",
								url: "<?php echo ENTRADA_URL; ?>/api/ajax-comment.api.php",
								data: "comment_description="+comment_desc+"&comment_title="+comment_title+"&uid="+id+"&assignment_id=<?php echo $ASSIGNMENT_ID; ?>&comment_type=assignment"
							})
							.done(function(data, textStatus, jqXHR) {
								try {
									var result = jQuery.parseJSON(data);
									if (result.error) {
										alert(result.error);
									} else {
										jQuery('#new_comment_desc_'+id).val('');
										jQuery('#new_comment_title_'+id).val('');											
										jQuery('#new_comment_'+id).hide();
										alert('Successfully added comment.  Please refresh your page to view it.');
									}
								} catch(e) {									
									jQuery("#comments_" + id + " li:last").before(data);
									jQuery('#comments_'+id).show();
									jQuery('#view_comments_'+id).text('Hide Comments');
									jQuery('#view_comments_'+id).attr('class','hide_comments');
									jQuery('#new_comment_'+id).hide();
									jQuery('#new_comment_desc_'+id).val('');									
									jQuery('#new_comment_title_'+id).val('');										
									if (jQuery('#no_comments_' + id).length > 0) {											
										jQuery('#no_comments_' + id).replaceWith("<a href=\"javascript:void(0);\" class=\"hide_comments\" id=\"view_comments_" + id + "\">Hide Comments</a>");
									} 
								}
							});
						});
						
						jQuery('.delete').live('click',function() {
							id = jQuery(this).attr('id').substring(7);
							jQuery("#dialog-confirm").dialog({
								resizable: false,
								height:180,
								modal: true,
								buttons: {
									'Delete': function() {
										window.location = '<?php echo ENTRADA_URL."/profile/gradebook/assignments"; ?>?section=delete-comment&acomment_id='+id+'&returnto=grade';
										return true;
									},
									Cancel: function() {
										jQuery(this).dialog('close');
									}
								}
								});
						});						
						
						jQuery('.view_comments').live('click',function(e) {
							var id = e.target.id.substring(14);
							jQuery('#comments_'+id).show();
							jQuery('#view_comments_'+id).text('Hide Comments');
							jQuery('#view_comments_'+id).attr('class','hide_comments');
						});	

						jQuery('.hide_comments').live('click',function(e) {
							var id = e.target.id.substring(14);
							jQuery('#comments_'+id).hide();
							jQuery('#view_comments_'+id).text('View Comments');
							jQuery('#view_comments_'+id).attr('class','view_comments');
						});		

						jQuery('.leave_comment').live('click',function(e) {
							var id = e.target.id.substring(14);
							jQuery('#new_comment_'+id).show();
						});	

						jQuery('.cancel_comment').live('click',function(e) {
							var id = e.target.id.substring(15);
							jQuery('#new_comment_'+id).hide();
							jQuery('#new_comment_text_'+id).val('');
						});	
					});
					</script>
					<div id="dialog-confirm" title="Delete?" style="display: none">
						<p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;"></span>This item will be permanently deleted and cannot be recovered. Are you sure you want to delete it?</p>
					</div>
					<style>
					#gradebook_stats{
						width:265px;
					}
					.mceEditor{
						width:100%!important;
					}
					.squeeze{
						width:465px;
						float:left;
					}
					.leave_comment{
						cursor:pointer;
						color:#0000FF;
					}
					ul.comments{
						list-style-type: none;
						padding: 0;
						margin-left: 0;			
					}
					ul.comments li{
						width:100%;
						margin-bottom:5px;
						text-align:left;
					}
					ul.comments li.nocomments{
						border:none;
						background-color:yellow;
						text-align:center;
					}
					.comment_info,.comment_text{
						width:100%;
						float:left;
					}
					.discussions tr td{
						white-space: normal!important;
					}
					.comment_form{
						width:100%;
					}
					.comment_form tr td{
						border:none!important;
					}
					.new_comment_text{
						width:98%!important;
					}
					.clearfix{
						clear:both;
					}
					</style>
				    <?php
                } else {
				    ?>
				    <div class="display-notice">No one has submitted their assignment yet.</div>
				    <?php
                }
			} else {
				$ERROR++;
				$ERRORSTR[] = "In order to edit an assessment's grades you must provide a valid assignment identifier.";

				echo display_error();

				application_log("notice", "Failed to provide a valid assessment identifier when attempting to edit an assessment's grades.");
			}

		} else {
			$ERROR++;
			$ERRORSTR[] = "You don't have permission to edit this gradebook.";

			echo display_error();

			application_log("error", "User tried to edit gradebook without permission.");
		}
	} else {
		$ERROR++;
		$ERRORSTR[] = "In order to edit a course you must provide a valid course identifier.";

		echo display_error();

		application_log("notice", "Failed to provide course identifier when attempting to edit an assessment's grades.");
	}
}
?>