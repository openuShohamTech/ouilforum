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

$forumid = required_param('f', PARAM_INT);
$postid  = required_param('p', PARAM_INT);

$PAGE->set_url('/mod/ouilforum/print.php', array('f' => $forumid, 'post' => $postid));


if (!$forum = $DB->get_record('ouilforum', array('id' => $forumid))) {
	print_error('invalidforumid', 'ouilforum');
}
if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
	print_error('invalidcourseid');
}
if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $course->id)) {
	print_error('invalidcoursemodule');
}
if (!$post = ouilforum_get_post_full($postid, $forum->hideauthor)) {
	print_error('invalidpostid', 'ouilforum');
}
if (!$discussion = $DB->get_record('ouilforum_discussions', array('id' => $post->discussion))) {
	print_error('notpartofdiscussion', 'ouilforum');
}
$modcontext = context_module::instance($cm->id);

require_course_login($course, true, $cm);

$PAGE->set_pagelayout('base');
$PAGE->set_cm($cm);
$PAGE->set_context($modcontext);

echo $OUTPUT->header();

echo html_writer::tag('button', get_string('print', 'ouilforum'), array('class'=>'not-printable', 'onclick'=>'print()'));

$data = new stdClass();
$data->message = '';
$data->format = FORMAT_HTML;
$print = ouilforum_print_post_plain($post, $cm, $course, $forum, $data, null, false, $USER->timezone, false);

echo $print[FORMAT_HTML];
echo $OUTPUT->footer();
