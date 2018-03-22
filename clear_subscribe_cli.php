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

define('CLI_SCRIPT', true); // This is a command line script.
require_once('../../config.php');
require_once('lib.php');
$tm = time();

global $arg;

$today = date("d-m-Y-H-i-s");
$filepath = $CFG->dataroot."/ouilforum_reports/clear_subscribe_cli_".$today.".html";

$to_directory = true;
if (!file_exists(dirname($filepath))) {
	$to_directory = @mkdir(dirname($filepath), $CFG->directorypermissions);
}

if (!$to_directory) {
	echo "\n\nCould not create target directory. Output will be displayed here.\n";
}
$newline = $to_directory ? '<br>' : "\n";
if ($to_directory) {
	echo "\n".$filepath."\n"; 
}

$moodle_course_id = 0;
$multiple_courses = true;
$debug = true;

$runquery = false;
$moodle_course_id = 0;
$semester = '0';
if (isset($_SERVER['argv'][1])) {
	$semester = $_SERVER['argv'][1];
}
$semester = trim($semester);
$delete_action = "no";

if (isset($_SERVER['argv'][2])) {
 	$delete_action = $_SERVER['argv'][2];
}

$run = "no";

$delete_action = "no";
if (isset($_SERVER['argv'][2])) {
	if ("yes" == $_SERVER['argv'][2]) {
		$delete_action = "yes";
	}
}

$force = false;
if (isset($_SERVER['argv'][3])) {
	if ("yes" == $_SERVER['argv'][3]) {
		$force = true;
	}
}

$forum_type = '';
$forum_type_str = 'all';
if (isset($_SERVER['argv'][4])) {
	$forum_type_str = $_SERVER['argv'][4];
	
	if ("news" == $_SERVER['argv'][4]) {
		$forum_type = " and  ouforum.type ='news' ";
	}
 	if ("no_news" == $_SERVER['argv'][4]) {
 		$forum_type = "  and ouforum.type !='news' ";
 	}
}

if ($semester != '0') {
	if ($force === true) {
		$run="yes";
	} else {
		if ($semester > $CFG->current_semester) {
			$run = "yes";
		}
	}
}

$grouping = 'all';
$multiple_courses = "yes";
$force_subscribe = "no";
$query_cond = "";
$params = array('grouping'=>$grouping);

if ($multiple_courses == "no") {
	if ($moodle_course_id > 0) {
		$params['moodle_course_id']	= $moodle_course_id;
		$query_cond = "  ouforum.course =:moodle_course_id";
	}
} else {
	if (mb_strlen($semester, 'utf-8') > 4) {
		$params['semester'] = "%-".$semester."%";
    	$query_cond = "   ouforum.course IN (SELECT id  FROM mdl_course WHERE shortname LIKE '%-".$semester."%')". $forum_type;
	}
}
 
if ($run == "yes") {
	if (strlen($query_cond) > 0) {
		$runquery = true;
	}
}

$delete_permission = false;
if ($delete_action === "yes") {
	$delete_permission = true;
}

ob_start();
if ($to_directory) {
	echo '
<!DOCTYPE html>
<html dir="ltr" lang="en" xml:lang="en">
<head>
	<meta name="X-UA-Compatible" content="IE=EDGE">
	<title>clear subscribe </title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>';
}
echo $newline." ***********************************";
echo $newline."  Moodle_course_id = ".$moodle_course_id;
echo $newline."  Semester = ".$semester;
echo $newline."  Multiple courses = ".$multiple_courses;
echo $newline."  Force semester = ";
if (true == $force) {
	echo "yes";
} else {
	echo "no";
}
echo $newline."  Forum type = ".$forum_type_str;


echo $newline."  Delete action = ".$delete_action;
echo $newline.'  Delete permission = ';
if (true == $delete_permission) {
	echo "yes";
} else {
	echo "no";
}
echo $newline."  Run query = ";
if (true == $runquery) {
	echo "yes";
} else {
	echo "no";
}
echo $newline." ***********************************".$newline;

if ($runquery) {
	// 1) get course forum subscribe.
	// 2) get forum subscribe.
	// Check permisiion  if no permission - remove user from subscribe.

 	raise_memory_limit(MEMORY_EXTRA);
 	
 	if ($grouping === 'all') {
 		$query = "SELECT ouforum.id AS id, ouforum.course AS course, ouforum.name AS name,
				forcesubscribe FROM ".$CFG->prefix."ouilforum AS ouforum WHERE ".$query_cond;
 	}
 	$subscribe_str = array(
 			0 => 'OUILFORUM_CHOOSESUBSCRIBE',
 			1 => 'OUILFORUM_FORCESUBSCRIBE',
 			2 => '<b>OUILFORUM_INITIALSUBSCRIBE</b>',
 			3 => 'OUILFORUM_DISALLOWSUBSCRIBE');

 	echo $query;
 	if ($rs = $DB->get_records_sql($query, $params)) {
 		foreach ($rs as $forum) {
 			$fourm_id = $forum->id;
 			$course_id = $forum->course;

 			echo $newline."<hr>$newline<b> <span> course: ".$course_id."</span> <span>forum: ".$fourm_id.
 				"</span> - <span>".$forum->name."</span></b>$newline forcesubscribe=".$forum->forcesubscribe. "  ".
 				$subscribe_str[$forum->forcesubscribe]. $newline."";
 			ouilforum_clear_subscribe($fourm_id, $course_id, $delete_permission, true);
 			if ("yes" === $force_subscribe) {
 				if ($forum->forcesubscribe == OUILFORUM_INITIALSUBSCRIBE) {
 					echo $newline." add  subscribe";
 					ouilforum_forcesubscribe_users($fourm_id, $course_id, OUILFORUM_INITIALSUBSCRIBE);
 				}
 			}
 		}
 	}
}

$cur_tm = time();
$dif = $cur_tm - $tm;
$time_values = array(
		'decade' => 315705600,
		'year'   => 31570560,
		'month'  => 2630880,
		'week'   => 604800,
		'day'    => 86400,
		'hour'   => 3600,
		'minute' => 60,
		'second' => 1);

$running_time = '';
if ($dif <= 1) {
	$running_time = "0 second ";
} else {
	foreach ($time_values as $value => $time) {
		$no = $dif / $time;
		if ($no > 1) {
			$no = floor($no);
			$running_time = sprintf("%d %s",$no, $value);
			if ($no != 1) {
				$running_time.= 's';
			}
			break;
		}
	}
}
echo $newline."Finished running script";
echo $newline."Running time : ".$running_time.$newline;
if ($to_directory) {
	echo '</body></html>';
}

$page = ob_get_contents();
ob_end_clean();

if ($to_directory) {
	if (($fw = fopen($filepath, "w")) !== false) {
		fputs($fw, $page, strlen($page));
		fclose($fw);
	}
} else {
	echo $newline.$newline.$page.$newline.$newline;
}
echo "\nFinished running script";
echo "\nRunning time : ".$running_time."\n";
