<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   mod_ouilforum
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once('../../config.php');
require_once('lib.php');

$PAGE->set_url('/mod/ouilforum/clear_subscribe.php');
require_login();
if (!is_siteadmin()) {
	die;
} else {

	$currentuser = $USER->id;
	$context = $usercontext = context_user::instance($currentuser, MUST_EXIST);

	$PAGE->set_context($context);
	$PAGE->set_pagelayout('mypublic');
	$PAGE->set_pagetype('user-profile');
	$strpublicprofile = get_string('checkSubscibe','ouilforum');
	$PAGE->blocks->add_region('content');
	$PAGE->set_title(fullname($USER).": $strpublicprofile");
	$PAGE->set_heading(fullname($USER).": $strpublicprofile");
	echo $OUTPUT->header();

	$runquery = false;
	$moodle_course_id = optional_param('moodle_course_id', 0, PARAM_ALPHANUM);
	$grouping         = optional_param('grouping', 'all', PARAM_ALPHANUM);
	$delete_action    = optional_param('deleteaction', "no", PARAM_ALPHANUM);
	$run              = optional_param('run', "no", PARAM_ALPHANUM);
	$multiple_courses = optional_param('multiplecourses', "no", PARAM_ALPHANUM);
	$force_subscribe  = optional_param('forcesubscribe', "no", PARAM_ALPHANUM);
	$semester         = optional_param('semester', '', PARAM_TEXT);
	$semester         = trim($semester);
?>
<form action="clear_subscribe.php" method="get">
	<input type="hidden" name="run" value="yes">
	<table>
		<tr>
			<td>Moodle course id</td>
			<td><input type="text" name="moodle_course_id"></td>
		</tr>
		<tr>
			<td>Semester</td>
			<td><input type="text" name="semester"></td>
		</tr>
		<tr>
			<td>Grouping</td>
			<td>
				<select name="grouping">
					<option value="all" selected >All</option>
					<option value="man">man</option>
					<option value="mer">mer</option>
					<option value="sgl">sgl</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Remove</td>
			<td>
				<select name="deleteaction">
					<option value="no" selected >No</option>
					<option value="yes">Yes</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Force subscribe</td>
			<td>
				<select name="forcesubscribe">
					<option value="no" selected >No</option>
					<option value="yes"  >Yes</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Multiple courses</td>
			<td>
				<select name="multiplecourses">
					<option value="no" selected >No</option>
					<option value="yes">Yes</option>
				</select>
			</td>
		</tr>
		<tr>
			<td>Submit</td>
			<td><input type="submit" value="Clear forum"></td>
		</tr>
	</table>
</form>
<?php 

	$query_cond = "";
	$params = array();
	$params['grouping']	= $grouping;

	if ($multiple_courses == "no") {
		if ($moodle_course_id > 0) {
			$params['moodle_course_id']	= $moodle_course_id;
			$query_cond = "  ouforum.course =:moodle_course_id";
		}
	} else {
		if (mb_strlen($semester, 'utf-8') > 4) {
			$params['semester'] = "%-".$semester."%";
			$query_cond = "   ouforum.course IN (SELECT id  FROM mdl_course WHERE  ".$DB->sql_like('shortname', ':semester').")";
		}
	}

	if ($run == "yes") {
		if (strlen($query_cond) > 0) {
			$runquery = true;
		}
	}

	if ($runquery) {
		// 1) get course forum subscribe.
		// 2) get forum subscribe.
		// Check permisiion  if no permission - remove user from subscribe.
		 
		if ($grouping === 'all') {
			$query = "SELECT ouforum.id as id  , ouforum.course as course ,ouforum.name as name , forcesubscribe FROM ".$CFG->prefix."ouilforum  as ouforum where ".$query_cond;
		} else {

			$params['grouping']	= $grouping;

			$query = "SELECT ouforum.id , ouforum.course  , ouforum.name  , grouping.name groupmembersonly , groupingid , forcesubscribe ";
			$query.= " FROM  {ouilforum} AS ouforum    , {course_modules} AS module , {groupings} AS grouping";
			$query.= " WHERE  ouforum.id=instance AND module=111 AND module.course=ouforum.course  AND groupmembersonly=1 ";
			$query.= " AND grouping.id=groupingid  and  grouping.name = :grouping  and " .$query_cond ;
		}
		echo "<br>  moodle_course_id  = ".$moodle_course_id;
		echo "<br>  deleteAction  = ".$delete_action;
		echo "<br>  multipleCourse  = ".$multiple_courses;
		echo "<br>  forceSubscribe  = ".$force_subscribe;
		echo "<br>";
		$delete_permission = false;
		if ($delete_action === "yes") {
			$delete_permission = true;
		}

		$subscribe_str = array(
				0 => 'OUILFORUM_CHOOSESUBSCRIBE',
				1 => 'OUILFORUM_FORCESUBSCRIBE',
				2 => 'OUILFORUM_INITIALSUBSCRIBE',
				3 => 'OUILFORUM_DISALLOWSUBSCRIBE');

		if ($rs = $DB->get_records_sql($query, $params)) {
			foreach ($rs as $forum) {
				$fourm_id = $forum->id;
				$course_id = $forum->course;

				echo "<br><hr><br><b> <span> course: ".$course_id."</span> <span>forum: ".$fourm_id.
					"</span> - <span>".$forum->name."</span></b><br> forcesubscribe=".
					$forum->forcesubscribe. "  ". $subscribe_str[$forum->forcesubscribe]. "<br>";
				ouilforum_clear_subscribe($fourm_id, $course_id, $delete_permission,true);
				if ("yes" === $force_subscribe) {
					if ($forum->forcesubscribe == OUILFORUM_INITIALSUBSCRIBE) {
						echo "<br> add  subscribe";
						ouilforum_forcesubscribe_users($fourm_id, $course_id, OUILFORUM_INITIALSUBSCRIBE);
					}
				}
			}
		}
		echo "<br><b>Finished running script</b>";
	}

} // Site admin only.
