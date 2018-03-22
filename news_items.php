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
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.

$params = array();
if ($id) {
	$params['id'] = $id;
}
$course =null;

if (!$course = $DB->get_record('course', array('id' => $id))) {
	print_error('coursemisconf');
}
$PAGE->set_url('/mod/ouilforum/news_items.php', $params);
$PAGE->set_pagelayout('course');
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course, false);
$PAGE->set_context($context);

// Print header.
$PAGE->set_title("news");
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();    
echo'<div class="back_to_home_page"><span class="news_post_action">
    <a href="/course/view.php?id='.$course->id.'"><i class="fa fa-arrow-right"></i>'.get_string('to_home_page', 'ouilforum').'</a>
    </span></div>';

echo ouilforum_print_news_items($course, null, 1);
echo $OUTPUT->footer($course);
