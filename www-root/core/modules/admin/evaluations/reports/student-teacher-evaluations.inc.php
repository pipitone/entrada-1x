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
 * @author Organisation: University of Calgary
 * @author Unit: Undergraduate Medical Education
 * @author Developer: Doug Hall <hall@ucalgary.ca>
 * @copyright Copyright 2010 Queen's University. All Rights Reserved.
 *
*/

if ((!defined("PARENT_INCLUDED")) || (!defined("IN_EVALUATIONS"))) {
	exit;
} elseif ((!isset($_SESSION["isAuthorized"])) || (!$_SESSION["isAuthorized"])) {
	header("Location: ".ENTRADA_URL);
	exit;
} elseif (!$ENTRADA_ACL->amIAllowed("evaluation", "update", false)) {
	$ONLOAD[]	= "setTimeout('window.location=\\'".ENTRADA_URL."/admin/".$MODULE."\\'', 15000)";

	$ERROR++;
	$ERRORSTR[]	= "Your account does not have the permissions required to use this feature of this module.<br /><br />If you believe you are receiving this message in error please contact <a href=\"mailto:".html_encode($AGENT_CONTACTS["administrator"]["email"])."\">".html_encode($AGENT_CONTACTS["administrator"]["name"])."</a> for assistance.";

	echo display_error();

	application_log("error", "Group [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["group"]."] and role [".$_SESSION["permissions"][$ENTRADA_USER->getAccessId()]["role"]."] does not have access to this module [".$MODULE."]");
} else {
	switch ($STEP) {
		case 2:
			$BREADCRUMB[] = array("url" => ENTRADA_URL."/admin/evaluations/reports?section=student-teacher-evaluations".replace_query(array("step" => 1)), "title" => "Students' Teacher Evaluations" );

			if (isset($_GET["id"]) && ($tmp_input = clean_input($_GET["id"], array("trim", "int")))) {
				$evaluation_id = $tmp_input;
			} else {
				$evaluation_id = 0;
			}

			$query = "	SELECT e.*, u.`id` `teacher_id`, u.`organisation_id`, CONCAT(`lastname`,', ',`firstname`) `name`, u.`prefix` `code`, t.`etarget_id`
						FROM `evaluations` e
						INNER JOIN `evaluation_targets` t
						ON e.`evaluation_id` = t.`evaluation_id`
						INNER JOIN `evaluations_lu_targets` elt
						ON t.`target_id` = elt.`target_id`
						LEFT JOIN `".AUTH_DATABASE."`.`user_data` u
						ON t.`target_value` = u.`id`
						WHERE e.`evaluation_id` = ".$db->qstr($evaluation_id)."
						AND elt.`target_shortname` = 'teacher'
						AND elt.`target_active` = 1";
			$results = $db->CacheGetAll(LONG_CACHE_TIMEOUT, $query);
			if ($results && $evaluation_id) {

				$single = count($results) == 1;

				$evaluators_list = Models_Evaluation::getEvaluators($evaluation_id);
				$evaluators = count($evaluators_list);

				echo "<h1>".$results[0]["evaluation_title"]."</h1>";

				echo "<h2 title=\"Evaluation Details Section\">Evaluation Details</h2>\n";
				echo "<div id=\"evaluation-details-section\" class=\"section-holder\">\n";
				echo "	<table style=\"width: 100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" summary=\"Detailed Evaluation Information\">\n";
				echo "	<colgroup>\n";
				echo "		<col style=\"width: 25%\" />\n";
				echo "		<col style=\"width: 75%\" />\n";
				echo "	</colgroup>\n";
				echo "	<tbody>\n";
				echo "		<tr>\n";
				echo "			<td>Description:</td>\n";
				echo "			<td>".(($results[0]["evaluation_description"]) ? $results[0]["evaluation_description"] : " ")."</td>\n";
				echo "		</tr>\n";
				echo "		<tr><td colspan=\"2\">&nbsp;</td></tr>\n";
				echo "		<tr>\n";
				echo "			<td>Evaluation Type:</td>\n";
				echo "			<td>Student's Teachers Evaluations</td>\n";
				echo "		</tr>\n";
				echo "		<tr><td colspan=\"2\">&nbsp;</td></tr>\n";
				echo "		<tr>\n";
				echo "			<td>Evaluation Start Date:</td>\n";
				echo "			<td>".date(DEFAULT_DATE_FORMAT, $results[0]["evaluation_start"])."</td>\n";
				echo "		</tr>\n";
				echo "		<tr>\n";
				echo "			<td>Evaluation Finish Date:</td>\n";
				echo "			<td>".date(DEFAULT_DATE_FORMAT, $results[0]["evaluation_finish"])."</td>\n";
				echo "		</tr>\n";
				if ($results[0]["min_submittable"] <> 1 or $results[0]["max_submittable"] <> 1) {
					echo "	<tr><td colspan=\"2\">&nbsp;</td></tr>\n";
					echo "	<tr>\n";
					echo "		<td>Submittable:</td>\n";
					echo "		<td><table>
									<tr><td>".$results[0]["min_submittable"]."</td><td align='right'>minimum<td style=\"width: 25%\"  />
									<td>".$results[0]["max_submittable"]."</td><td align='right'>maximum</td></tr>
								</table></td>\n";
					echo "	</tr>\n";
				}
				echo "</table>";
				echo "</div>";
				?>
				<a name="teacher-evaluation-section"></a>
				<h2 title="Evaluated Courses Section">Teachers Evaluated in this Evaluation</h2>

				<div id="evaluated-teachers-section" class="section-holder">
					<form name="frmReport" action="<?php echo ENTRADA_URL; ?>/admin/evaluations/reports?section=reports" method="post">
						<table class="tableList" cellspacing="0" cellpadding="1" summary="List of Evaluated Teachers">
							<colgroup>
								<col class="modified" />
								<col class="title" />
								<col class="general" />
								<col class="general" />
								<col class="date" />
							</colgroup>
							<thead>
								<tr>
									<td class="modified">&nbsp;</td>
									<td class="title">Teacher</td>
									<td class="general">In Progress</td>
									<td class="general">Complete</td>
									<td class="date">Updated</td>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<td>&nbsp;</td>
									<td colspan="4" style="text-align: right; padding-top: 15px">
										<input type="submit" class="btn btn-primary" value="Create Report<?php echo $single ? "" : "s"; ?>" />
									</td>
								</tr>
							</tfoot>
							<tbody>
							<?php
							foreach ($results as $result) {
								$query = "	SELECT MAX(p.`updated_date`) FROM `evaluation_progress` p
											INNER JOIN `evaluation_targets` t ON p.`etarget_id` = t.`etarget_id`
											WHERE t.`evaluation_id` = ".$db->qstr($result["evaluation_id"])."
											AND t.`target_value` = ".$db->qstr($result["teacher_id"])." AND t.`target_active` = 1
											AND p.`progress_value` <> 'cancelled'";
								$updated = $db->GetOne($query);

									$query = "	SELECT COUNT(p.`eprogress_id`) FROM `evaluation_progress` p
												INNER JOIN `evaluation_targets` t ON p.`etarget_id` = t.`etarget_id`
												WHERE t.`evaluation_id` = ".$db->qstr($result["evaluation_id"])."
												AND t.`target_value` = ".$db->qstr($result["teacher_id"])." AND t.`target_active` = 1
												AND p.`progress_value` = 'inprogress'";
									$progress = $db->GetOne($query);

									$query = "	SELECT COUNT(p.`eprogress_id`) FROM `evaluation_progress` p
												INNER JOIN `evaluation_targets` t ON p.`etarget_id` = t.`etarget_id`
												WHERE t.`evaluation_id` = ".$db->qstr($result["evaluation_id"])."
												AND t.`target_value` = ".$db->qstr($result["teacher_id"])." AND t.`target_active` = 1
												AND p.`progress_value` = 'complete'";
									$completed = $db->GetOne($query);

									$url = ENTRADA_URL."/admin/evaluations/reports?section=reports&amp;evaluation=s:".$result["etarget_id"];
									echo "<tr>\n";
									echo "	<td class=\"modified\"><input type=\"checkbox\" name=\"checked[]\" value=\"s:".$result["etarget_id"]."\"".($single ? "checked=\"checked\"" : "")." /></td>\n";
									echo "	<td class=\"title\"><a href=\"".$url."\">".html_encode($result["name"])."</a></td>\n";
									echo "	<td class=\"general\"><a href=\"".$url."\">".($evaluators ? round($progress / $evaluators * 100)."%&nbsp;&nbsp;&nbsp;(".$progress."/".$evaluators.")" : "")."</a></td>\n";
									echo "	<td class=\"general\"><a href=\"".$url."\">".($evaluators ? round($completed / $evaluators * 100)."%&nbsp;&nbsp;&nbsp;(".$completed."/".$evaluators.")" : "")."</a></td>\n";
									echo "	<td class=\"date\"><a href=\"".$url."\">".($updated ? date(DEFAULT_DATE_FORMAT, $updated) : "")."</a></td>\n";
									echo "</tr>\n";
							}
							?>
							</tbody>
						</table>
					</form>
				</div>
				<?php
			} else {
				header("Location: ".ENTRADA_URL."/admin/evaluations/reports");
				exit;
			}
		break;
		case 1:
		default:
			$BREADCRUMB[]	= array("url" => "", "title" => "Students' Teachers Evaluations" );

			/**
			 * Update requested column to sort by.
			 * Valid: director, name
			 */
		    if (isset($_GET["sb"])) {
				if (@in_array(trim($_GET["sb"]), array("title", "evaluation_start", "evaluation_finish"))) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]	= trim($_GET["sb"]);
				}

				$_SERVER["QUERY_STRING"] = replace_query(array("sb" => false));
			} else {
				if (!isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"])) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"] = "title";
				}
			}

			/**
			 * Update requested order to sort by.
			 * Valid: asc, desc
			 */
			if (isset($_GET["so"])) {
				$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"] = ((strtolower($_GET["so"]) == "desc") ? "desc" : "asc");

				$_SERVER["QUERY_STRING"] = replace_query(array("so" => false));
			} else {
				if (!isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"])) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"] = "asc";
				}
			}

			/**
			 * Update requsted number of rows per page.
			 * Valid: any integer really.
			 */
			if ((isset($_GET["pp"])) && ((int) trim($_GET["pp"]))) {
				$integer = (int) trim($_GET["pp"]);

				if (($integer > 0) && ($integer <= 250)) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"] = $integer;
				}

				$_SERVER["QUERY_STRING"] = replace_query(array("pp" => false));
			} else {
				if (!isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"])) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"] = DEFAULT_ROWS_PER_PAGE;
				}
			}

			/**
			 * Update requested column to sort by.
			 * Valid: title, start, finish
			 */
			if(isset($_GET["sb"])) {
				if(in_array(trim($_GET["sb"]), array("title" , "evaluation_start", "evaluation_finish"))) {
					if (trim($_GET["sb"]) == "title") {
						$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]	= "title";
					} elseif (trim($_GET["sb"]) == "evaluation_start") {
						$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]	= "evaluation_start";
					} elseif (trim($_GET["sb"]) == "evaluation_finish") {
						$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]	= "evaluation_finish";
					}
				}
			} else {
				if(!isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"])) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]	= "title";
				}
			}

			/**
			 * Update requested order to sort by.
			 * Valid: asc, desc
			 */
			if(isset($_GET["so"])) {
				$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"] = ((strtolower($_GET["so"]) == "desc") ? "DESC" : "ASC");
			} else {
				if(!isset($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"])) {
					$_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"] = "ASC";
				}
			}
	
			$report_evaluations = array(
					"duration_start" => 0,
					"duration_end" => 0,
					"total_rows" => 0,
					"total_pages" => 0,
					"page_current" => 0,
					"page_previous" => 0,
					"page_next" => 0,
					"evaluations" => array()
				);

			/**
			 * Provide the queries with the columns to order by.
			 */
			switch ($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]) {
				case "title" :
					$sort_by = "e.`evaluation_title` ".strtoupper($_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["so"]).", e.`evaluation_start` ASC";
				break;
				case "evaluation_start" :
					$sort_by = "e.`evaluation_start` ".strtoupper($_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["so"]).", e.`evaluation_title` ASC";
				break;
				case "evaluation_finish" :
					$sort_by = "e.`evaluation_finish` ".strtoupper($_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["so"]).", e.`evaluation_title` ASC";
				break;
				default:
					$sort_by = "e.`evaluation_title` ".strtoupper($_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["so"]).", e.`evaluation_start` ASC";
				break;
			}

			$query = "	SELECT e.`evaluation_id`, e.`evaluation_title`, e.`evaluation_description`, e.`evaluation_start`,
						e.`evaluation_finish`, e.`min_submittable`,  count(distinct(u.`id`)) `targets`
						FROM `evaluations` e 
						INNER JOIN `evaluation_evaluators` ev ON e.`evaluation_id` = ev.`evaluation_id`
						INNER JOIN `evaluation_targets` t ON e.`evaluation_id` = t.`evaluation_id`
						INNER JOIN `evaluations_lu_targets` elt ON t.`target_id` = elt.`target_id`
						LEFT JOIN `".AUTH_DATABASE."`.`user_data` u ON t.`target_value` = u.`id`
						INNER JOIN `".AUTH_DATABASE."`.`user_access` a
						ON ev.`evaluator_value` = a.`user_id`
						WHERE elt.`target_shortname` = 'teacher'
						AND elt.`target_active` = 1 
						AND a.`app_id` = ".$db->qstr(AUTH_APP_ID)."
						AND a.`account_active` = 'true'
						GROUP BY `evaluation_id`";
			$results	= $db->GetAll($query);
				
			$query_evaluations = "	SELECT e.`evaluation_id`, e.`evaluation_title`, e.`evaluation_description`, e.`evaluation_start`,
									e.`evaluation_finish`, e.`min_submittable`,  count(distinct(u.`id`)) `targets`
									FROM `evaluations` e 
									INNER JOIN `evaluation_evaluators` ev ON e.`evaluation_id` = ev.`evaluation_id`
									INNER JOIN `evaluation_targets` t ON e.`evaluation_id` = t.`evaluation_id`
									INNER JOIN `evaluations_lu_targets` elt ON t.`target_id` = elt.`target_id`
									LEFT JOIN `".AUTH_DATABASE."`.`user_data` u ON t.`target_value` = u.`id`
									INNER JOIN `".AUTH_DATABASE."`.`user_access` a
									ON ev.`evaluator_value` = a.`user_id`
									WHERE elt.`target_shortname` = 'teacher'
									AND elt.`target_active` = 1 
									AND a.`app_id` = ".$db->qstr(AUTH_APP_ID)."
									AND a.`account_active` = 'true'
				                    GROUP BY e.`evaluation_id`
				                    ORDER BY %s
				                    LIMIT %s, %s";

			/**
			 * Get the total number of results using the generated queries above and calculate the total number
			 * of pages that are available based on the results per page preferences.
			 */
			$result_count = count($results);

			if ($result_count) {
				$report_evaluations["total_rows"] = (int) $result_count;

				if ($report_evaluations["total_rows"] <= $_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["pp"]) {
					$report_evaluations["total_pages"] = 1;
				} elseif (($report_evaluations["total_rows"] % $_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["pp"]) == 0) {
					$report_evaluations["total_pages"] = (int) ($report_evaluations["total_rows"] / $_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["pp"]);
				} else {
					$report_evaluations["total_pages"] = (int) ($report_evaluations["total_rows"] / $_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["pp"]) + 1;
				}
			} else {
				$report_evaluations["total_rows"] = 0;
				$report_evaluations["total_pages"] = 1;
			}
			/**
			 * Check if pv variable is set and see if it's a valid page, other wise page 1 it is.
			 */
			if (isset($_GET["pv"])) {
				$report_evaluations["page_current"] = (int) trim($_GET["pv"]);

				if (($report_evaluations["page_current"] < 1) || ($report_evaluations["page_current"] > $report_evaluations["total_pages"])) {
					$report_evaluations["page_current"] = 1;
				}
			} else {
				$report_evaluations["page_current"] = 1;
			}

			$report_evaluations["page_previous"] = (($report_evaluations["page_current"] > 1) ? ($report_evaluations["page_current"] - 1) : false);
			$report_evaluations["page_next"] = (($report_evaluations["page_current"] < $report_evaluations["total_pages"]) ? ($report_evaluations["page_current"] + 1) : false);

			/**
			 * Provides the first parameter of MySQLs LIMIT statement by calculating which row to start results from.
			 */
			$limit_parameter = (int) (($_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["pp"] * $report_evaluations["page_current"]) - $_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["pp"]);

			/**
			 * Provide the previous query so we can have previous / next event links on the details page.
			 */
			$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["evaluations"]["previous_query"]["query"] = $query_evaluations;
			$_SESSION[APPLICATION_IDENTIFIER]["tmp"]["evaluations"]["previous_query"]["total_rows"] = $report_evaluations["total_rows"];

			$query_evaluations = sprintf($query_evaluations, $sort_by, $limit_parameter, $_SESSION[APPLICATION_IDENTIFIER]["evaluations"]["pp"]);
			$report_evaluations["evaluations"] = $db->GetAll($query_evaluations);

			echo "<h1>Students' Teacher	 Evaluations</h1>";

			if ($report_evaluations["total_pages"] > 1) {
				echo "<div class=\"fright\" style=\"margin-bottom: 10px\">\n";
				echo "<form action=\"".ENTRADA_URL."/admin/evaluations/reports\" method=\"get\" id=\"pageSelector\">\n";
				echo "<input type=\"hidden\" name=\"section\" value=\"student-teacher-evaluations\" />\n";
				echo "<span style=\"width: 20px; vertical-align: middle; margin-right: 3px; text-align: left\">\n";
				if ($report_evaluations["page_previous"]) {
					echo "<a href=\"".ENTRADA_URL."/admin/evaluations/reports?".replace_query(array("pv" => $report_evaluations["page_previous"]))."\"><img src=\"".ENTRADA_URL."/images/record-previous-on.gif\" border=\"0\" width=\"11\" height=\"11\" alt=\"Back to page ".$report_evaluations["page_previous"].".\" title=\"Back to page ".$report_evaluations["page_previous"].".\" style=\"vertical-align: middle\" /></a>\n";
				} else {
					echo "<img src=\"".ENTRADA_URL."/images/record-previous-off.gif\" width=\"11\" height=\"11\" alt=\"\" title=\"\" style=\"vertical-align: middle\" />";
				}
				echo "</span>";
				echo "<span style=\"vertical-align: middle\">\n";
				echo "<select name=\"pv\" onchange=\"$('pageSelector').submit();\"".(($report_evaluations["total_pages"] <= 1) ? " disabled=\"disabled\"" : "").">\n";
				for($i = 1; $i <= $report_evaluations["total_pages"]; $i++) {
					echo "<option value=\"".$i."\"".(($i == $report_evaluations["page_current"]) ? " selected=\"selected\"" : "").">".(($i == $report_evaluations["page_current"]) ? " Viewing" : "Jump To")." Page ".$i."</option>\n";
				}
				echo "</select>\n";
				echo "</span>\n";
				echo "<span style=\"width: 20px; vertical-align: middle; margin-left: 3px; text-align: right\">\n";
				if ($report_evaluations["page_current"] < $report_evaluations["total_pages"]) {
					echo "<a href=\"".ENTRADA_URL."/admin/evaluations/reports?".replace_query(array("pv" => $report_evaluations["page_next"]))."\"><img src=\"".ENTRADA_URL."/images/record-next-on.gif\" border=\"0\" width=\"11\" height=\"11\" alt=\"Forward to page ".$report_evaluations["page_next"].".\" title=\"Forward to page ".$report_evaluations["page_next"].".\" style=\"vertical-align: middle\" /></a>";
				} else {
					echo "<img src=\"".ENTRADA_URL."/images/record-next-off.gif\" width=\"11\" height=\"11\" alt=\"\" title=\"\" style=\"vertical-align: middle\" />";
				}
				echo "</span>\n";
				echo "</form>\n";
				echo "</div>\n";
				echo "<div class=\"clear\"></div>\n";
			}

			if (count($report_evaluations["evaluations"])) {
 			?>
				<table class="tableList" cellspacing="0" cellpadding="1" summary="List of Course Evaluations">
					<colgroup>
						<col class="title" />
						<col class="date" />
						<col class="date" />
						<col class="general" />
					</colgroup>
					<thead>
						<tr>
							<td class="title borderl<?php echo (($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"] == "title") ? " sorted".strtoupper($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"]) : ""); ?>"><?php echo admin_order_link("title", "Evaluation Title","reports"); ?></td>
							<td class="date<?php echo (($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"] == "evaluation_start") ? " sorted".strtoupper($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"]) : ""); ?>"><?php echo admin_order_link("evaluation_start", "Start Date","reports"); ?></td>
							<td class="date<?php echo (($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"] == "evaluation_finish") ? " sorted".strtoupper($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"]) : ""); ?>"><?php echo admin_order_link("evaluation_finish", "Finish Date","reports"); ?></td>
							<td class="general"><div class="noLink">Response Rate</div></td>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ($report_evaluations["evaluations"] as $result) {

						$query = "	SELECT COUNT(DISTINCT(`evaluator`)) FROM
									(
										SELECT ev.`evaluator_value` `evaluator`
										FROM `evaluation_evaluators` ev
										WHERE ev.`evaluator_type` = 'proxy_id'
										AND ev.`evaluation_id` = ".$db->qstr($result["evaluation_id"])."
										UNION
										SELECT a.`user_id` `evaluator`
										FROM `group_members` a , `evaluation_evaluators` ev
										WHERE ev.`evaluator_type` = 'cohort'
										AND ev.`evaluator_value` = a.`group_id`
										AND a.`member_active` = 'true'
										AND ev.`evaluation_id` = ".$db->qstr($result["evaluation_id"])."
										UNION
										SELECT a.`user_id` `evaluator`
										FROM `course_group_audience` a , `evaluation_evaluators` ev
										WHERE ev.`evaluator_type` = 'cgroup_id'
										AND ev.`evaluator_value` = a.`cgroup_id`
										AND a.`active` = 1
										AND ev.`evaluation_id` = ".$db->qstr($result["evaluation_id"])."
									) t";
						$evaluators	= $db->GetOne($query);

						$query = "	SELECT COUNT(`eprogress_id`) FROM `evaluation_progress`
									WHERE `evaluation_id` = ".$db->qstr($result["evaluation_id"])."
									AND `progress_value` = 'complete'";
						$count = $db->GetOne($query);
						if ($count) {
							$complete = round($count / $result["min_submittable"]);
						} else {
							$complete = 0;
						}

						$url = ENTRADA_URL."/admin/evaluations/reports?section=".$SECTION."&amp;step=2&amp;id=".$result["evaluation_id"];

						echo "<tr>\n";
						echo "	<td class=\"title\"><a href=\"".$url."\">".html_encode($result["evaluation_title"])."</a></td>\n";
						echo "	<td class=\"date\"><a href=\"".$url."\">".date(DEFAULT_DATE_FORMAT, $result["evaluation_start"])."</a></td>\n";
						echo "	<td class=\"date\"><a href=\"".$url."\">".date(DEFAULT_DATE_FORMAT, $result["evaluation_finish"])."</a></td>\n";
						echo "	<td class=\"general\"><a href=\"".$url."\">".($evaluators ? round($complete / ($evaluators * $result["targets"]) * 100)."%&nbsp;&nbsp;&nbsp;(".$complete."/".($evaluators * $result["targets"]).")" : "")."</a></td>\n";
						echo "</tr>\n";
					}
					?>
					</tbody>
				</table>
				<?php
			} else {
				?>
				<div class="display-generic">
					There are no <strong>student course evaluations</strong> available at this time.
				</div>
				<?php
			}

			echo "<form action=\"\" method=\"get\">\n";
			echo "<input type=\"hidden\" id=\"dstamp\" name=\"dstamp\" value=\"".html_encode($_SESSION[APPLICATION_IDENTIFIER]["tmp"]["dstamp"])."\" />\n";
			echo "</form>\n";

			/**
			 * Sidebar item that will provide another method for sorting, ordering, etc.
			 */
			$url = ENTRADA_URL."/admin/evaluations/reports?";
			$sidebar_html  = "Sort columns:\n";
			$sidebar_html .= "<ul class=\"menu\">\n";
			$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]) == "title") ? "on" : "off")."\"><a href=\"".$url.replace_query(array("sb" => "title"))."\" title=\"Sort by Evaluation Title\">by evaluation title</a></li>\n";
			$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]) == "evaluation_start") ? "on" : "off")."\"><a href=\"".$url.replace_query(array("sb" => "evaluation_start"))."\" title=\"Sort by Start Date &amp; Time\">by start date</a></li>\n";
			$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["sb"]) == "evaluation_finish") ? "on" : "off")."\"><a href=\"".$url.replace_query(array("sb" => "evaluation_finish"))."\" title=\"Sort by Finish Date &amp; Time\">by finish</a></li>\n";
			$sidebar_html .= "</ul>\n";
			$sidebar_html .= "Order columns:\n";
			$sidebar_html .= "<ul class=\"menu\">\n";
			$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"]) == "asc") ? "on" : "off")."\"><a href=\"".$url.replace_query(array("so" => "asc"))."\" title=\"Ascending Order\">in ascending order</a></li>\n";
			$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["so"]) == "desc") ? "on" : "off")."\"><a href=\"".$url.replace_query(array("so" => "desc"))."\" title=\"Descending Order\">in descending order</a></li>\n";
			$sidebar_html .= "</ul>\n";
			$sidebar_html .= "Rows per page:\n";
			$sidebar_html .= "<ul class=\"menu\">\n";
			$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"]) == "5") ? "on" : "off")."\"><a href=\"".$url.replace_query(array("pp" => "5"))."\" title=\"Display 5 Rows Per Page\">5 rows per page</a></li>\n";
			$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"]) == "15") ? "on" : "off")."\"><a href=\"".$url.replace_query(array("pp" => "15"))."\" title=\"Display 15 Rows Per Page\">15 rows per page</a></li>\n";
			$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"]) == "25") ? "on" : "off")."\"><a href=\"".$url.replace_query(array("pp" => "25"))."\" title=\"Display 25 Rows Per Page\">25 rows per page</a></li>\n";
			$sidebar_html .= "	<li class=\"".((strtolower($_SESSION[APPLICATION_IDENTIFIER][$MODULE]["pp"]) == "50") ? "on" : "off")."\"><a href=\"".$url.replace_query(array("pp" => "50"))."\" title=\"Display 50 Rows Per Page\">50 rows per page</a></li>\n";
			$sidebar_html .= "</ul>\n";

			new_sidebar_item("Sort Results", $sidebar_html, "sort-results", "open");
		break;
	}
}