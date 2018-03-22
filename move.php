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

$sourceforumid = required_param('f', PARAM_INT); // Forum id. The forum is required for its hideauthor value.
$postid        = required_param('p', PARAM_INT); // Post id.

$page_params = array('f' => $sourceforumid, 'p' => $postid);
$PAGE->set_url('/mod/ouilforum/move.php', $page_params);

$can_split = $can_move = false;
if ($sourceforumid) {
	if (!$sourceforum = $DB->get_record('ouilforum', array('id' => $sourceforumid))) {
		print_error('invalidforumid', 'ouilforum');
	}
	if (!$course = $DB->get_record('course', array('id' => $sourceforum->course))) {
		print_error('invalidcourseid');
	}

	if (!$cm = get_coursemodule_from_instance('ouilforum', $sourceforum->id, $course->id)) {
		print_error('invalidcoursemodule');
	} else {
		// Check if the user has any capability.
		$modcontext = context_module::instance($cm->id);
		$can_split = has_capability('mod/ouilforum:splitdiscussions', $modcontext);
		$can_move = has_capability('mod/ouilforum:movediscussions', $modcontext);
		if (!$can_split && !$can_move) {
			print_error('cannotmoveandsplit', 'ouilforum');
		}
	}

	if (!empty($postid)) {
		if (!$post = ouilforum_get_post_full($postid, $sourceforum->hideauthor)) {
			print_error('incorrectpostid', 'ouilforum');
		}
		else {
			if (!$discussion = $DB->get_record('ouilforum_discussions', array('id' => $post->discussion)))
				print_error('notpartofdiscussion', 'ouilforum');
		}
	} else {
		print_error('missingpostid', 'ouilforum');
	}
	// Move require_course_login here to use forced language for course.
	// fix for MDL-6926
	require_course_login($course, true, $cm);

} else {
	print_error('missingforumid', 'ouilforum');
}

// The user has at least one of the two capabilities. 
// Let's check each capability according to the action (move/split discussion).
if (!$can_split && $post->parent > 0) {
	print_error('cannotsplit', 'ouilforum'); // The user cannot split a discussion.
}
if (!$can_move && $post->parent == 0) {
	print_error('cannotmove', 'ouilforum'); // The user cannot move a discussion.
}

require_course_login($course, true, $cm);

if (isguestuser()) {
	// Just in case.
	print_error('noguest');
}

// Is this the whole discussion or just port of it.
$full_discussion = empty($post->parent);
$movestring = get_string($full_discussion ? 'movediscussion' : 'movepost', 'ouilforum');
$available_forums = ouilforum_get_target_forums_options($course->id, $sourceforum->id);

$PAGE->set_cm($cm);
$PAGE->set_context($modcontext);
$PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/ouilforum/discuss.php', array('d'=>$discussion->id)));
$PAGE->navbar->add($movestring);
$PAGE->set_title(format_string($discussion->name).': '.format_string($post->subject));
$PAGE->set_heading($course->fullname);

// Cannot move discussion if there is no available target forum.
if ($full_discussion && empty($available_forums)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading($movestring);
    echo $OUTPUT->notification(get_string('move:noavailableforums', 'ouilforum'));
    echo $OUTPUT->footer();
    exit;
}
$mform_move = new mod_ouilforum_move_form('move.php', array('course' => $course,
        'sourceforum' => $sourceforum,
        'cm' => $cm,
        'postid' => $postid,
        'parentpost' => $post->parent,
        'currentdiscussionid' => $discussion->id,
		'availableforums' => $available_forums));


if ($mform_move->is_cancelled()) {
	// Go back to the main forum page.
	redirect('view.php?id='.$cm->id);
} else if ($fromform = $mform_move->get_data()) {
	require_sesskey();
	$return = 'view.php?f='.$sourceforumid;
	$new_title = trim($fromform->newtitle);
	// Move to a new forum.
	if ($fromform->movetarget == 2) {
		
		if (!$targetforum = $DB->get_record('ouilforum', array('id' => $fromform->targetforum))) {
			print_error('invalidforumid', 'ouilforum');
		}
		if ($targetforum->course != $sourceforum->course) {
			print_error('move:notsamecourse', 'ouilforum');
		}
		
		if ($targetforum->type == 'single') {
			print_error('cannotmovetosingleforum', 'ouilforum', $return);
		}
		
		$modinfo = get_fast_modinfo($course);
		$forums = $modinfo->get_instances_of('ouilforum');
		if (!array_key_exists($targetforum->id, $forums)) {
			print_error('cannotmovetonotfound', 'ouilforum', $return);
		}
		$cmto = $forums[$targetforum->id];
		if (!$cmto->uservisible) {
			print_error('cannotmovenotvisible', 'ouilforum', $return);
		}
		$destinationctx = context_module::instance($cmto->id);
		require_capability('mod/ouilforum:startdiscussion', $destinationctx);
		if (!empty($new_title)) {
			$post->subject = $new_title;
		}
		ouilforum_move_post($post, $sourceforum, $discussion, $fromform->targetforum, $cm);
		redirect('view.php?f='.$fromform->targetforum);
	}
	
	
	// Move inside this forum.
	if ($fromform->movetarget == 1 && !empty($fromform->postradio)) {
		ouilforum_move_post_sameforum($post, $fromform->postradio, $sourceforum, $cm);
		redirect($return);
		exit;
	}
	// Move inside this forum as a new discussion.
	else if ($fromform->movetarget == 3) {
		if (!empty($new_title)) {
			$post->subject = $new_title;
		}
		ouilforum_move_post($post, $sourceforum, $discussion, $sourceforumid, $cm);
		redirect($return);
		exit;
	}
}

$replycount = ouilforum_count_replies($post);
$discussion->discussion = $discussion->id;

if (empty($post->parent)) {
	$movestring = 'discussion';
} else {
	$movestring = 'post';
	$PAGE->requires->js_call_amd('mod_ouilforum/move', 'init', array(array('forum'=>$sourceforum->id)));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('move'.$movestring, 'ouilforum'));

/* 
 * Because some of the radio input fields are rendered separately and not as a group, every input is wrapped in a div container. 
 * Due to the way Moodle code works, all those divs receive the same id.
 * To solve this, the ID tag is removed from the template.
 */
$default_template = $GLOBALS['_HTML_QuickForm_default_renderer']->_elementTemplates['default'];
$GLOBALS['_HTML_QuickForm_default_renderer']->_elementTemplates['default'] = str_replace(' id="{id}"', '', $default_template);
$mform_move->set_data(null);
$mform_move->display();

// This value is re-initialized afterwards, but just to be safe side, the original value is restored.
$GLOBALS['_HTML_QuickForm_default_renderer']->_elementTemplates['default'] = $default_template;

echo '<div class="for-sr">'.get_string('move:original'.$movestring, 'ouilforum').'</div>';
ouilforum_print_post($post, $discussion, $sourceforum, $cm, $course, false, false, false, '', '',
		null, true, null, false, OUILFORUM_DISPLAY_OPEN_CLEAN, true);

if ($replycount == 1) {
	$repliesmessage = get_string($movestring.'reply', 'ouilforum');
} else if ($replycount > 1) {
	$repliesmessage = get_string($movestring.'replies', 'ouilforum', $replycount);
} else {
	$repliesmessage = get_string($movestring.'noreplies', 'ouilforum');
}
echo '<div class="ouilforum_message">'.$repliesmessage.'</div>';

echo $OUTPUT->footer();

?>