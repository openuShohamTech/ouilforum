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

$forumid = required_param('f', PARAM_INT);       // Forum ID
$postid  = required_param('postid', PARAM_INT);  // POST  ID
$page    = optional_param('page', 0, PARAM_INT); // page number

$PAGE->set_url('/mod/ouilforum/forward.php', array('f' => $forumid, 'postid' => $postid));
$page_params = array('f' => $forumid, 'postid' => $postid);

if ($forumid) {
	if (!$ouilforum = $DB->get_record('ouilforum', array('id' => $forumid))) {
		print_error('invalidforumid', 'ouilforum');
	}
	if (!$course = $DB->get_record('course', array('id' => $ouilforum->course))) {
		print_error('invalidcourseid');
	}

	if (!$cm = get_coursemodule_from_instance('ouilforum', $ouilforum->id, $course->id)) {
		print_error('invalidcoursemodule');
	} else {
		$modcontext = context_module::instance($cm->id);
	}

	if (!empty($postid)) {
		// User post.
		if (!$post = ouilforum_get_post_full($postid, 0 , $ouilforum->hideauthor)) {
			print_error('Post ID was incorrect', 'ouilforum');
		}
		else {
			if (!$discussion = $DB->get_record('ouilforum_discussions', array('id' => $post->discussion)))
				print_error('notpartofdiscussion', 'ouilforum');
		}
	} else {
		print_error('missingpostid', 'ouilforum');
	}
	require_course_login($course, true, $cm);

} else {
	print_error('missingforumid', 'ouilforum');
}

if (isguestuser()) {
	// Just in case.
	print_error('noguest');
}

$PAGE->set_cm($cm);
$PAGE->set_context($modcontext);
$PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/ouilforum/forward.php', array('f' => $forumid, 'postid' => $postid)));
$PAGE->navbar->add(get_string('forwardtitle', 'ouilforum'));
$PAGE->set_title(format_string($discussion->name).': '.format_string($post->subject));
$PAGE->set_heading($course->fullname);

$forwardsubject = $post->subject;
$quick_subject = optional_param('quicksubject', '', PARAM_TEXT);
if (!empty($quick_subject)) {
	$forwardsubject = $quick_subject;
}
$form_params = array('course' => $course,
		'ouilforum' => $ouilforum,
		'cm' => $cm,
		'post' => $post,
		'subject' => $forwardsubject);
$mform_mail = new mod_ouilforum_forward_form('forward.php', $form_params);

$set = array();
$quick_email = optional_param('quickemail', '', PARAM_EMAIL);
if (!empty($quick_email)) {
	$set['email'] = $quick_email;
}
$quick_message = optional_param('quickmessage', '', PARAM_RAW_TRIMMED);
if (!empty($quick_message)) {
	$set['message'] = array('text'=>htmlspecialchars($quick_message), 'format'=>1);
}
$quick_ccme = optional_param('quickccme', 0, PARAM_INT);
if (!empty($quick_ccme)) {
	$set['ccme'] = $quick_ccme;
}
if (!empty($set)) {
	$mform_mail->set_data($set);
}
if ($mform_mail->is_cancelled()) {
	redirect('view.php?f='.$ouilforum->id, '', 0);
	exit;
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('forwardtitle', 'ouilforum'));

if ($form = $mform_mail->get_data()) {
	
	// Set stricness to IGNORE_MULTIPLE if in developer mode, to handle test users with the same email.
	$strictness = ($CFG->debugdeveloper) ? IGNORE_MULTIPLE : IGNORE_MISSING; //@todo: check the config variable

	$userto = $DB->get_record('user', array('email' => $form->email), '*', $strictness);
	$course_unique = ouilforum_extract_course_shortname($course->shortname);

	$a = (object)array('name' => fullname($USER, true),
            'course_short'   => $course->shortname,
            'course_full'    => $course->fullname,
            'email'          => $USER->email,
            'course_unique'  => $course_unique,
            'course_link'    => $CFG->wwwroot.'/course/view.php?id='.$course->id,
            'link_to_sender' => $CFG->wwwroot.'/user/view.php?id='.$USER->id,
            'forum_name'     => $ouilforum->name);

	$post->message = ouilforum_handle_images_mail($post->message);
	
	$msg = ouilforum_print_post_plain($post, $cm, $course, $ouilforum, $form, get_string('forwardpreface', 'ouilforum', $a), false, $userto->timezone);

    $subject = stripslashes($form->subject).' '.get_string('strconsubject', 'ouilforum').' '.$course_unique;
    if (!email_to_user($userto, $USER, $subject, $msg[FORMAT_PLAIN], $msg[FORMAT_HTML])) {
    	print_error('errorforwardemail', 'ouilforum', $userto->email);
    }
	
	// Send to me.
 	if (!empty($form->ccme)) {
		if (!email_to_user($USER, $USER, $subject, $msg[FORMAT_PLAIN], $msg[FORMAT_HTML])) {
			print_error('errorforwardemail', 'ouilforum', $USER->email);
		}
	}
	// Sending email done.
	echo $OUTPUT->box(get_string('forwarddone', 'ouilforum'));

} else {
	$post->lastpost = true;
	$mform_mail->display();
	$discussion->discussion = $discussion->id;
	echo '<span class="for-sr">'.get_string('posttosend', 'ouilforum').'</span>';
	echo '<div id="discussion'.$discussion->id.'">'.
		ouilforum_print_post($post, $discussion, $ouilforum, $cm, $course, false, false, false, 
				'', '', null, true, null, false, OUILFORUM_DISPLAY_OPEN_CLEAN).
		'</div>';
	
}

echo $OUTPUT->footer();
