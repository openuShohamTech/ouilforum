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
global $CFG;
require_once($CFG->libdir.'/filelib.php');

$filepath = $CFG->dataroot."/ouilforum_reports";

require_login();
if (!is_siteadmin()) {
	die;
} else {
	$file = optional_param('file', '', PARAM_TEXT);
	$file1path = "";
	
	if ($file != '') {
		$file1path = $CFG->dataroot."/ouilforum_reports/".$file;
		send_file($file1path, basename($file1path), 1, 0, 0, 1);
		echo "<br>Downloading file: ".$file;
	} else {
		$fileslist = scandir($filepath, 1);
		echo "<b>Reports of clear_subscribe_cli:</b><br><ul>";
		foreach ($fileslist as $file1) {
			if (strpos($file1, "clear_subscribe_cli_") !== false) {
				$file1path = $CFG->dataroot."/ouilforum_reports/".$file1;
				echo '<li><a href="/mod/ouilforum/ouilforum_reports.php?file='.$file1.'">'.$file1.'</a></li>';
			}
		}
		echo "</ul>";
	}
}
