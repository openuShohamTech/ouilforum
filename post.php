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
 * Edit and save a new post to a discussion
 *
 * @package   mod_ouilforum
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');

$reply        = optional_param('reply', 0, PARAM_INT);
$forumid      = optional_param('ouilforum', 0, PARAM_INT);
$f            = optional_param('f', 0, PARAM_INT);
$discussionid = optional_param('discussion', 0, PARAM_INT);
$edit         = optional_param('edit', 0, PARAM_INT);
$delete       = optional_param('delete', 0, PARAM_INT);
$prune        = optional_param('prune', 0, PARAM_INT);
$lock         = optional_param('lock', 0, PARAM_INT);
$name         = optional_param('name', '', PARAM_TEXT);
$confirm      = optional_param('confirm', 0, PARAM_INT);
$groupid      = optional_param('groupid', null, PARAM_INT);
$pin		  = optional_param('pin', 0, PARAM_INT);
$replyto	  = optional_param('replyto', 0, PARAM_INT);
$returnto     = optional_param('returnto', 'forum', PARAM_TEXT);

$url_params = array(
        'reply'    => $reply,
        'forum'    => $forumid,
        'edit'     => $edit,
        'delete'   => $delete,
        'prune'    => $prune,
		'lock'     => $lock,
        'name'     => $name,
        'confirm'  => $confirm,
        'groupid'  => $groupid,
		'pin'      => $pin,
		'returnto' => $returnto
        );

// These page_params will be passed as hidden variables later in the form.
$page_params = array('reply'=>$reply, 'ouilforum'=>$forumid, 'edit'=>$edit, 'returnto'=>$returnto);

if ($reply > 0) {
	if ($replyto > 0 && $replyto != $reply) {
		$url_params['replyto'] = $replyto;
		$page_params['replyto'] = $replyto;
	} else {
		$replyto = $reply;
	}
}
$PAGE->set_url('/mod/ouilforum/post.php', $url_params);

$sitecontext = context_system::instance();

if (!isloggedin() || isguestuser()) {

    if (!isloggedin() && !get_local_referer()) {
        // No referer+not logged in - probably coming in via email. See MDL-9052.
        require_login();
    }

    if (!empty($forumid)) { // User is starting a new discussion in a forum.
        if (!$forum = $DB->get_record('ouilforum', array('id' => $forumid))) {
            print_error('invalidforumid', 'ouilforum');
        }
    } else if (!empty($reply)) { // User is writing a new reply.
        if (!$parent = ouilforum_get_post_full($reply)) {
            print_error('invalidparentpostid', 'ouilforum');
        }
        if (!$discussion = $DB->get_record('ouilforum_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'ouilforum');
        }
        if (!$forum = $DB->get_record('ouilforum', array('id' => $discussion->ouilforum))) {
            print_error('invalidforumid');
        }
    }
    if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
        print_error('invalidcourseid');
    }

    if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $course->id)) { // For the logs.
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $forum);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    $referer = get_local_referer(false);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'ouilforum').'<br><br>'.get_string('liketologin'), get_login_url(), $referer);
    echo $OUTPUT->footer();
    exit;
}

require_login(0, false); // Script is useless unless they're logged in.
$return_url = '';
if (!empty($forumid)) { // User is starting a new discussion in a forum.
    if (!$forum = $DB->get_record('ouilforum', array('id' => $forumid))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (!ouilforum_user_can_post_discussion($forum, $groupid, -1, $cm)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                        'returnurl' => '/mod/ouilforum/view.php?f='.$forum->id)),
                        get_string('youneedtoenrol'));
                }
            }
        }
        print_error('nopostforum', 'ouilforum');
    }

    if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error('activityiscurrentlyhidden');
    }

    $SESSION->fromurl = $return_url = ouilforum_get_referer($returnto, $course->id, $forum->id);

    // Load up the $post variable.

    $post = new stdClass();
    $post->course        = $course->id;
    $post->forum         = $forum->id;
    $post->discussion    = 0; // Ie discussion # not defined yet.
    $post->parent        = 0;
    $post->subject       = '';
    $post->userid        = $USER->id;
    $post->message       = '';
    $post->messageformat = editors_get_preferred_format();
    $post->messagetrust  = 0;

    if (isset($groupid)) {
        $post->groupid = $groupid;
    } else {
        $post->groupid = groups_get_activity_group($cm);
    }

} else if (!empty($reply)) { // User is writing a new reply.

    if (!$parent = ouilforum_get_post_full($reply)) {
        print_error('invalidparentpostid', 'ouilforum');
    }
    if (!$discussion = $DB->get_record('ouilforum_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'ouilforum');
    }
    if (!$forum = $DB->get_record('ouilforum', array('id' => $discussion->ouilforum))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if (!$course = $DB->get_record('course', array('id' => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926
    $PAGE->set_cm($cm, $course, $forum);

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (!ouilforum_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) { // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                    'returnurl' => '/mod/ouilforum/view.php?f='.$forum->id)),
                    get_string('youneedtoenrol'));
            }
        }
        print_error('nopostforum', 'ouilforum');
    }

    // Make sure user can post here.
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('nopostforum', 'ouilforum');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('nopostforum', 'ouilforum');
            }
        }
    }

    if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error('activityiscurrentlyhidden');
    }

    // Load up the $post variable.
    $post = new stdClass();
    $post->course      = $course->id;
    $post->forum       = $forum->id;
    $post->discussion  = $parent->discussion;
    $post->parent      = $parent->id;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';

    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'ouilforum');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre.' '.$post->subject;
    }
    $return_url = ouilforum_get_referer($returnto, $course->id, $forum->id, $discussion->id);

} else if (!empty($edit)) { // User is editing their own post.

    if (!$post = ouilforum_get_post_full($edit)) {
        print_error('invalidpostid', 'ouilforum');
    }
    if ($post->parent) {
        if (!$parent = ouilforum_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'ouilforum');
        }
    }

    if (!$discussion = $DB->get_record('ouilforum_discussions', array('id' => $post->discussion))) {
        print_error('notpartofdiscussion', 'ouilforum');
    }
    if (!$forum = $DB->get_record('ouilforum', array('id' => $discussion->ouilforum))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if (!$course = $DB->get_record('course', array('id' => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $forum);

    if (!($forum->type == 'news' && !$post->parent && $discussion->timestart > time())) {
        if (((time() - $post->created) > $CFG->maxeditingtime) and
                    !has_capability('mod/ouilforum:editanypost', $modcontext)) {
            print_error('maxtimehaspassed', 'ouilforum', '', format_time($CFG->maxeditingtime));
        }
    }
    if (($post->userid <> $USER->id) and
                !has_capability('mod/ouilforum:editanypost', $modcontext)) {
        print_error('cannoteditposts', 'ouilforum');
    }


    // Load up the $post variable.
    $post->edit    = $edit;
    $post->course  = $course->id;
    $post->forum   = $forum->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $post = trusttext_pre_edit($post, 'message', $modcontext);

    $return_url = ouilforum_get_referer($returnto, $course->id, $forum->id, $discussion->id);
    
} else if (!empty($delete)) { // User is deleting a post.

    if (!$post = ouilforum_get_post_full($delete)) {
        print_error('invalidpostid', 'ouilforum');
    }
    if (!$discussion = $DB->get_record('ouilforum_discussions', array('id' => $post->discussion))) {
        print_error('notpartofdiscussion', 'ouilforum');
    }
    if (!$forum = $DB->get_record('ouilforum', array('id' => $discussion->ouilforum))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $forum->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
        print_error('invalidcourseid');
    }

    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);

    if ( !(($post->userid == $USER->id && has_capability('mod/ouilforum:deleteownpost', $modcontext))
                || has_capability('mod/ouilforum:deleteanypost', $modcontext)) ) {
        print_error('cannotdeletepost', 'ouilforum');
    }

    $return_url = ouilforum_get_referer($returnto, $course->id, $forum->id, $discussion->id);

    $replycount = ouilforum_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) { // User has confirmed the delete.
        // Check user capability to delete post.
        $timepassed = time() - $post->created;
        if (($timepassed > $CFG->maxeditingtime) && !has_capability('mod/ouilforum:deleteanypost', $modcontext)) {
            print_error('cannotdeletepost', 'ouilforum', $return_url);
        }

        if ($post->totalscore) {
            notice(get_string('couldnotdeleteratings', 'rating'), $return_url);

        } else if ($replycount && !has_capability('mod/ouilforum:deleteanypost', $modcontext)) {
            print_error('couldnotdeletereplies', 'ouilforum', $return_url);

        } else {
            if (!$post->parent) { // Post is a discussion topic as well, so delete discussion.
                if ($forum->type == 'single') {
                    notice('Sorry, but you are not allowed to delete that discussion!', $return_url);
                }
                ouilforum_delete_discussion($discussion, false, $course, $cm, $forum);

                $params = array(
                    'objectid' => $discussion->id,
                    'context' => $modcontext,
                    'other' => array(
                        'forumid' => $forum->id,
                    )
                );

                $event = \mod_ouilforum\event\discussion_deleted::create($params);
                $event->add_record_snapshot('ouilforum_discussions', $discussion);
                $event->trigger();

                if ($returnto==""){
                	redirect('view.php?f='.$discussion->ouilforum);
                }else{
                	redirect($return_url);
                }

            } else if (ouilforum_delete_post($post, has_capability('mod/ouilforum:deleteanypost', $modcontext),
                $course, $cm, $forum)) {

                if ($forum->type == 'single' && $returnto == 'discussion') {
                    // Single discussion forums are an exception. 
                    // We show the forum itself since it only has one discussion thread.
                	if ($returnto.len==0){
                    	$return_url = new moodle_url('/mod/ouilforum/view.php', array('f' => $forum->id));
                	}
                }
                redirect($return_url);
            } else {
                print_error('errorwhiledelete', 'ouilforum');
            }
        }

    } else { // User just asked to delete something.

        $PAGE->navbar->add(get_string('delete', 'ouilforum'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        
        if ($replycount && !has_capability('mod/ouilforum:deleteanypost', $modcontext)) {
        		print_error('couldnotdeletereplies', 'ouilforum', $return_url);
        }
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($forum->name), 2);
        $confirm_string = $replycount ? get_string('deletesureplural', 'ouilforum', $replycount+1) :
        	get_string('deletesure', 'ouilforum');
        
        $return_url_delete="post.php?delete=$delete&confirm=$delete";
        if ($returnto=="") {
        	$return_url_cancel = $CFG->wwwroot.'/mod/ouilforum/discuss.php?d='.$post->discussion.'#p'.$post->id;
        } else {
        	$return_url_cancel= $returnto;
        	$return_url_delete.=".&returnto=".$returnto;
        }

        echo $OUTPUT->confirm($confirm_string, $return_url_delete , $return_url_cancel);
        echo '<span class="for-sr">'.get_string('posttodelete', 'ouilforum').'</span>';
        ouilforum_print_post($post, $discussion, $forum, $cm, $course, false, false, false,
        		null, null, null, null, null, null, OUILFORUM_DISPLAY_OPEN_CLEAN);
        
    }
    echo $OUTPUT->footer();
    die;

} else if (!empty($on_top)) {

    if (!$forum = $DB->get_record("ouilforum", array("id" => $forumid))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if (!$course = $DB->get_record("course", array("id" => $forum->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("ouilforum", $forum->id, $course->id)) {
        print_error("invalidcoursemodule");
    }
	
    $forumurl = "view.php?id=$cm->id";
	if (!$discussion = $DB->get_record('ouilforum_discussions', array('id'=>$discussion))) {
		print_error('Discussion id is not correct!');
	}

	$discussion->on_top = $on_top == 1 ? 1 : 0;
	if (!$DB->update_record("ouilforum_discussions", $discussion)) {
		print_error("couldnotupdate", "ouilforum", $forumurl);
	}

	$modcontext = context_module::instance($cm->id);
	$params = array(
			'context' => $modcontext,
			'objectid' => $discussion->id,
	);
	$event = $on_top == 1 ?
				\mod_ouilforum\event\discussion_pinned::create($params) :
				\mod_ouilforum\event\discussion_unpinned::create($params);
	$event->trigger();
	
	redirect($forumurl);
	die;
} else if (!empty($pin)) {

	if (!$discussion = $DB->get_record('ouilforum_discussions', array('id'=>$discussionid))) {
		print_error('Discussion id is not correct!');
	}
	if (!$forum = $DB->get_record('ouilforum', array('id' => $discussion->ouilforum))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
	
    $forumurl = 'view.php?id='.$cm->id;
	$discussion->on_top = $pin == 1 ? 1 : 0;
	if (!$DB->update_record('ouilforum_discussions', $discussion)) {
		print_error('couldnotupdate', 'ouilforum', $forumurl);
	}

	$modcontext = context_module::instance($cm->id);
	$params = array(
			'context' => $modcontext,
			'objectid' => $discussion->id,
	);
	$event = $pin == 1 ?
				\mod_ouilforum\event\discussion_pinned::create($params) :
				\mod_ouilforum\event\discussion_unpinned::create($params);
	$event->trigger();
	
	redirect($forumurl);
	die;
} else if (!empty($lock)) {

    if (!$forum = $DB->get_record("ouilforum", array("id" => $f))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if (!$course = $DB->get_record("course", array("id" => $forum->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("ouilforum", $forum->id, $course->id)) {
        print_error("invalidcoursemodule");
    }
	
    $forumurl = "view.php?id=$cm->id";
	if (!$discussion = $DB->get_record('ouilforum_discussions', array('id'=>$discussionid))) {
		print_error('Discussion id is not correct!');
	}

	$discussion->locked = $lock == 1 ? 1 : 0;
	if (!$DB->update_record("ouilforum_discussions", $discussion)) {
		print_error("couldnotupdate", "ouilforum", $forumurl);
	}
	redirect($forumurl);
	die;
} else if (!empty($prune)) { // Pruning.

    if (!$post = ouilforum_get_post_full($prune)) {
        print_error('invalidpostid', 'ouilforum');
    }
    if (!$discussion = $DB->get_record('ouilforum_discussions', array('id' => $post->discussion))) {
        print_error('notpartofdiscussion', 'ouilforum');
    }
    if (!$forum = $DB->get_record('ouilforum', array('id' => $discussion->ouilforum))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if ($forum->type == 'single') {
        print_error('cannotsplit', 'ouilforum');
    }
    if (!$post->parent) {
        print_error('alreadyfirstpost', 'ouilforum');
    }
    if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $forum->course)) { // For the logs.
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }
    if (!has_capability('mod/ouilforum:splitdiscussions', $modcontext)) {
        print_error('cannotsplit', 'ouilforum');
    }

    $PAGE->set_cm($cm);
    $PAGE->set_context($modcontext);

    $prunemform = new mod_ouilforum_prune_form(null, array('prune' => $prune, 'confirm' => $prune));


    if ($prunemform->is_cancelled()) {
        redirect($return_url);
    } else if ($fromform = $prunemform->get_data()) {
        // User submits the data.
        $newdiscussion = new stdClass();
        $newdiscussion->course       = $discussion->course;
        $newdiscussion->ouilforum    = $discussion->ouilforum;
        $newdiscussion->name         = $name;
        $newdiscussion->firstpost    = $post->id;
        $newdiscussion->userid       = $discussion->userid;
        $newdiscussion->groupid      = $discussion->groupid;
        $newdiscussion->assessed     = $discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart    = $discussion->timestart;
        $newdiscussion->timeend      = $discussion->timeend;

        $newid = $DB->insert_record('ouilforum_discussions', $newdiscussion);

        $newpost = new stdClass();
        $newpost->id      = $post->id;
        $newpost->parent  = 0;
        $newpost->subject = $name;

        $DB->update_record('ouilforum_posts', $newpost);

        ouilforum_change_discussionid($post->id, $newid);

        // Update last post in each discussion.
        ouilforum_discussion_update_last_post($discussion->id);
        ouilforum_discussion_update_last_post($newid);

        // Fire events to reflect the split..
        $params = array(
            'context' => $modcontext,
            'objectid' => $discussion->id,
            'other' => array(
                'forumid' => $forum->id,
            )
        );
        $event = \mod_ouilforum\event\discussion_updated::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $newid,
            'other' => array(
                'forumid' => $forum->id,
            )
        );
        $event = \mod_ouilforum\event\discussion_created::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $newid,
                'forumid' => $forum->id,
                'forumtype' => $forum->type,
            )
        );
        $event = \mod_ouilforum\event\post_updated::create($params);
        $event->add_record_snapshot('ouilforum_discussions', $discussion);
        $event->trigger();

        redirect($return_url);

    } else {
        // Display the prune form.
        $course = $DB->get_record('course', array('id' => $forum->course));
        $PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/ouilforum/discuss.php', array('d'=>$discussion->id)));
        $PAGE->navbar->add(get_string('prune', 'ouilforum'));
        $PAGE->set_title(format_string($discussion->name).": ".format_string($post->subject));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($forum->name), 2);
        echo $OUTPUT->heading(get_string('pruneheading', 'ouilforum'), 3);

        $prunemform->display();

        ouilforum_print_post($post, $discussion, $forum, $cm, $course, false, false, false);
    }

    echo $OUTPUT->footer();
    die;
} else {
    print_error('unknowaction');

}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = context_course::instance($forum->course);
}


// From now on user must be logged on properly.
if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $course->id)) { // For the logs.
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

if (isguestuser()) {
    // just in case
    print_error('noguest');
}

if (!isset($forum->maxattachments)) {  // TODO - delete this once we add a field to the forum table
    $forum->maxattachments = 3;
}

$thresholdwarning = ouilforum_check_throttling($forum, $cm);
$mform_post = new mod_ouilforum_post_form('post.php', array('course' => $course,
		'cm' => $cm,
        'coursecontext' => $coursecontext,
        'modcontext' => $modcontext,
        'ouilforum' => $forum,
        'post' => $post,
        'subscribe' => \mod_ouilforum\subscriptions::is_subscribed($USER->id, $forum,
        null, $cm),
        'thresholdwarning' => $thresholdwarning,
        'edit' => $edit), 'post', '', array('id' => 'mformforum'));

$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_ouilforum', 'attachment', empty($post->id)?null:$post->id, 
		mod_ouilforum_post_form::attachment_options($forum));

// Load data into form NOW!

if ($USER->id != $post->userid) { // Not the original author, so add a message to the end.
    $data = new stdClass();
    $data->date = userdate($post->modified);
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
                       fullname($USER).'</a>';
        $post->message .= '<p><span class="edited">('.get_string('editedby', 'ouilforum', $data).')</span></p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(".get_string('editedby', 'ouilforum', $data).')';
    }
    unset($data);
}

$formheading = '';
if (!empty($parent)) {
    $heading = get_string('yourreply', 'ouilforum');
    $formheading = get_string('reply', 'ouilforum');
} else {
    if ($forum->type == 'qanda') {
        $heading = get_string('yournewquestion', 'ouilforum');
    } else {
        $heading = get_string('yournewtopic', 'ouilforum');
    }
}

$postid = empty($post->id) ? null : $post->id;
$draftid_editor = file_get_submitted_draft_itemid('message');
$quick_message = optional_param('quickmessage', '', PARAM_RAW_TRIMMED);
$quick_subject = optional_param('quicksubject', '', PARAM_RAW_TRIMMED);
$postsubject = $post->subject;
if (!empty($quick_subject)) {
	$postsubject = htmlspecialchars($quick_subject);
}
if (!empty($quick_message)) {
	$currenttext = ouilforum_format_quick_message($quick_message);
} else {
	$currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_ouilforum', 'post', $postid, 
			mod_ouilforum_post_form::editor_options($modcontext, $postid), $post->message);
}

$manageactivities = has_capability('moodle/course:manageactivities', $coursecontext);
if (\mod_ouilforum\subscriptions::subscription_disabled($forum) && !$manageactivities) {
    // User does not have permission to subscribe to this discussion at all.
    $discussionsubscribe = false;
} else if (\mod_ouilforum\subscriptions::is_forcesubscribed($forum)) {
    // User does not have permission to unsubscribe from this discussion at all.
    $discussionsubscribe = true;
} else {
    if (isset($discussion) && \mod_ouilforum\subscriptions::is_subscribed($USER->id, $forum, $discussion->id, $cm)) {
        // User is subscribed to the discussion - continue the subscription.
        $discussionsubscribe = true;
    } else if (!isset($discussion) && \mod_ouilforum\subscriptions::is_subscribed($USER->id, $forum, null, $cm)) {
        // Starting a new discussion, and the user is subscribed to the forum - subscribe to the discussion.
        $discussionsubscribe = true;
    } else {
        // User is not subscribed to either forum or discussion. Follow user preference.
        $discussionsubscribe = $USER->autosubscribe;
    }
}

$mform_post->set_data(array(
		'attachments'=>$draftitemid,
        'general'=>$heading,
		'subject'=>$postsubject,
        'message'=>array(
        		'text'=>$currenttext,
        		'format'=>empty($post->messageformat) ? editors_get_preferred_format() : $post->messageformat,
        		'itemid'=>$draftid_editor
        ),
        'discussionsubscribe' => $discussionsubscribe,
        'mailnow'=>!empty($post->mailnow),
        'userid'=>$post->userid,
        'parent'=>$post->parent,
        'discussion'=>$post->discussion,
        'course'=>$course->id) +
        	$page_params +
		
			(isset($post->format)?array(
					'format'=>$post->format):
				array())+

            (isset($discussion->timestart)?array(
            		'timestart'=>$discussion->timestart):
                array())+

            (isset($discussion->timeend)?array(
            		'timeend'=>$discussion->timeend):
                array())+

			(isset($post->groupid)?array(
					'groupid'=>$post->groupid):
                array())+

			(isset($discussion->id)?
					array('discussion'=>$discussion->id):
                array()));

if ($mform_post->is_cancelled()) {
    if (!isset($discussion->id) || $forum->type === 'qanda') {
        // Q and A forums don't have a discussion page, so treat them like a new thread..
        redirect(new moodle_url('/mod/ouilforum/view.php', array('f' => $forum->id)));
    } else {
        redirect(new moodle_url('/mod/ouilforum/discuss.php', array('d' => $discussion->id)));
    }
} else if ($fromform = $mform_post->get_data()) {

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/ouilforum/view.php?f=$forum->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    $fromform->itemid        = $fromform->message['itemid'];
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message       = $fromform->message['text'];
    // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
    $fromform->messagetrust  = trusttext_trusted($modcontext);

    if ($fromform->edit) { // Updating a post.
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        if (!$realpost = $DB->get_record('ouilforum_posts', array('id' => $fromform->id))) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }

        // If user has edit any post capability or has either startnewdiscussion or reply capability and is editting own post,
        // then he can proceed. MDL-7066
        if (!(($realpost->userid == $USER->id && (has_capability('mod/ouilforum:replypost', $modcontext)
                            || has_capability('mod/ouilforum:startdiscussion', $modcontext))) ||
                            has_capability('mod/ouilforum:editanypost', $modcontext)) ) {
            print_error('cannotupdatepost', 'ouilforum');
        }

        // If the user has access to all groups and they are changing the group, then update the post.
        if (isset($fromform->groupinfo) && has_capability('mod/ouilforum:movediscussions', $modcontext)) {
            if (empty($fromform->groupinfo)) {
                $fromform->groupinfo = -1;
            }

            if (!ouilforum_user_can_post_discussion($forum, $fromform->groupinfo, null, $cm, $modcontext)) {
                print_error('cannotupdatepost', 'ouilforum');
            }

            $DB->set_field('forum_discussions' ,'groupid' , $fromform->groupinfo, array('firstpost' => $fromform->id));
        }

        $updatepost = $fromform; // Realpost.
        $updatepost->forum = $forum->id;
        if (!ouilforum_update_post($updatepost, $mform_post, $message)) {
            print_error('couldnotupdate', 'ouilforum', $errordestination);
        }

        // MDL-11818
        if (($forum->type == 'single') && ($updatepost->parent == '0')) { // Updating first post of single discussion type -> updating forum intro.
            $forum->intro = $updatepost->message;
            $forum->timemodified = time();
            $DB->update_record("ouilforum", $forum);
        }

        $timemessage = 2;
        if (!empty($message)) { // If we're printing stuff about the file upload.
            $timemessage = 4;
        }

        if ($realpost->userid == $USER->id) {
            $message.= '<br>'.get_string('postupdated', 'ouilforum');
        } else {
            $realuser = $DB->get_record('user', array('id' => $realpost->userid));
            $message.= '<br>'.get_string('editedpostupdated', 'ouilforum', fullname($realuser));
        }

        if ($subscribemessage = ouilforum_post_subscription($fromform, $forum, $discussion)) {
            $timemessage = 4;
        }
        if ($forum->type == 'single' && $returnto == 'discussion') {
            // Single discussion forums are an exception.
            // We show the forum itself since it only has one discussion thread.
            $return_url = new moodle_url('/mod/ouilforum/view.php', array('f' => $forum->id));
        } else {
        	$return_url = ouilforum_get_referer($returnto, $course->id, $forum->id, $discussion->id);
        }

        $params = array(
            'context' => $modcontext,
            'objectid' => $fromform->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'forumid' => $forum->id,
                'forumtype' => $forum->type,
            )
        );

        if ($realpost->userid !== $USER->id) {
            $params['relateduserid'] = $realpost->userid;
        }

        $event = \mod_ouilforum\event\post_updated::create($params);
        $event->add_record_snapshot('ouilforum_discussions', $discussion);
        $event->trigger();

        ouilforum_redirect($return_url, $message.$subscribemessage, $timemessage);

        exit;


    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        // Before we add this we must check that the user will not exceed the blocking threshold.
        ouilforum_check_blocking_threshold($thresholdwarning);

        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->forum=$forum->id;
        if ($fromform->id = ouilforum_add_new_post($addpost, $mform_post, $message)) {
            $timemessage = 2;
            if (!empty($message)) { // If we're printing stuff about the file upload.
                $timemessage = 4;
            }

            if ($subscribemessage = ouilforum_post_subscription($fromform, $forum, $discussion)) {
                $timemessage = 4;
            }

            if (!empty($fromform->mailnow) && $course->visible) {
                $message.= get_string('postmailnow', 'ouilforum');
                $timemessage = 4;
            } else {
                $message.= '<p>'.get_string('postaddedsuccess', 'ouilforum').'</p>';
                $message.= '<p>'.get_string('postaddedtimeleft', 'ouilforum', format_time($CFG->maxeditingtime)).'</p>';
            }

            if ($forum->type == 'single') {
                // Single discussion forums are an exception.
                // We show the forum itself since it only has one discussion thread.
                $discussionurl = new moodle_url('/mod/ouilforum/view.php', array('f' => $forum->id), 'p'.$fromform->id);
            } else {
                $discussionurl = new moodle_url('/mod/ouilforum/discuss.php', array('d' => $discussion->id), 'p'.$fromform->id);
            }

            $params = array(
                'context' => $modcontext,
                'objectid' => $fromform->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'forumid' => $forum->id,
                    'forumtype' => $forum->type,
                )
            );
            $event = \mod_ouilforum\event\post_created::create($params);
            $fromform->mark = null; // Avoid debug notice about missing fields @todo: fix in future
            $event->add_record_snapshot('ouilforum_posts', $fromform);
            $event->add_record_snapshot('ouilforum_discussions', $discussion);
            $event->trigger();

            // Update completion state.
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                ($forum->completionreplies || $forum->completionposts)) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }

            ouilforum_redirect($return_url, $message.$subscribemessage, $timemessage);

        } else {
            print_error('couldnotadd', 'ouilforum', $errordestination);
        }
        exit;

    } else { // Adding a new discussion.
        // The location to redirect to after successfully posting.
        $redirectto = new moodle_url('view.php', array('f' => $fromform->ouilforum));

        $fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;

        $discussion = $fromform;
        $discussion->name = $fromform->subject;
        $discussion->on_top = 0;
        $discussion->locked = 0;
        
        $newstopic = false;
        if ($forum->type == 'news' && !$fromform->parent) {
            $newstopic = true;
        }
        $discussion->timestart = $fromform->timestart;
        $discussion->timeend = $fromform->timeend;

        $allowedgroups = array();
        $groupstopostto = array();

        // If we are posting a copy to all groups the user has access to.
        if (isset($fromform->posttomygroups)) {
            // Post to each of my groups.
            require_capability('mod/ouilforum:canposttomygroups', $modcontext);

            // Fetch all of this user's groups.
            // Note: all groups are returned when in visible groups mode so we must manually filter.
            $allowedgroups = groups_get_activity_allowed_groups($cm);
            foreach ($allowedgroups as $groupid => $group) {
                if (ouilforum_user_can_post_discussion($forum, $groupid, -1, $cm, $modcontext)) {
                    $groupstopostto[] = $groupid;
                }
            }
        } else if (isset($fromform->groupinfo)) {
            // Use the value provided in the dropdown group selection.
            $groupstopostto[] = $fromform->groupinfo;
            $redirectto->param('group', $fromform->groupinfo);
        } else if (isset($fromform->groupid) && !empty($fromform->groupid)) {
            // Use the value provided in the hidden form element instead.
            $groupstopostto[] = $fromform->groupid;
            $redirectto->param('group', $fromform->groupid);
        } else {
            // Use the value for all participants instead.
            $groupstopostto[] = -1;
        }

        // Before we post this we must check that the user will not exceed the blocking threshold.
        ouilforum_check_blocking_threshold($thresholdwarning);

        foreach ($groupstopostto as $group) {
            if (!ouilforum_user_can_post_discussion($forum, $group, -1, $cm, $modcontext)) {
                print_error('cannotcreatediscussion', 'ouilforum');
            }

            $discussion->groupid = $group;
            $message = '';
            if ($discussion->id = ouilforum_add_discussion($discussion, $mform_post, $message)) {

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $discussion->id,
                    'other' => array(
                        'forumid' => $forum->id,
                    )
                );
                $event = \mod_ouilforum\event\discussion_created::create($params);
                $event->add_record_snapshot('ouilforum_discussions', $discussion);
                $event->trigger();

                $timemessage = 2;
                if (!empty($message)) { // If we're printing stuff about the file upload.
                    $timemessage = 4;
                }

                if ($fromform->mailnow && $course->visible) {
                    $message.= get_string('postmailnow', 'ouilforum');
                    $timemessage = 4;
                } else {
                    $message.= '<p>'.get_string('postaddedsuccess', 'ouilforum').'</p>';
                    $message.= '<p>'.get_string('postaddedtimeleft', 'ouilforum', format_time($CFG->maxeditingtime)).'</p>';
                }

                if ($subscribemessage = ouilforum_post_subscription($fromform, $forum, $discussion)) {
                    $timemessage = 6;
                }
            } else {
                print_error('couldnotadd', 'ouilforum', $errordestination);
            }
        }

        // Update completion status.
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) &&
                ($forum->completiondiscussions || $forum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        // Redirect back to the discussion.
        ouilforum_redirect($return_url, $message.$subscribemessage, $timemessage);
    }
}

// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.
// $course, $forum are defined. $discussion is for edit and reply only.

if ($post->discussion) {
    if (! $toppost = $DB->get_record('ouilforum_posts', array('discussion' => $post->discussion, 'parent' => 0))) {
        print_error('cannotfindparentpost', 'ouilforum', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = ($forum->type == 'news') ? get_string('addanewtopic', 'ouilforum') :
                                                   get_string('addanewdiscussion', 'ouilforum');
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $forum->name;
}
if ($forum->type == 'single') {
    // There is only one discussion thread for this forum type.
    // We should not show the discussion name (same as forum name in this case) in the breadcrumbs.
    $strdiscussionname = '';
} else {
    // Show the discussion name in the breadcrumbs.
    $strdiscussionname = format_string($discussion->name).':';
}

$forcefocus = empty($reply) ? NULL : 'message';

if (!empty($discussion->id)) {
    $PAGE->navbar->add(format_string($toppost->subject, true), 'discuss.php?d='.$discussion->id);
}

if ($post->parent) {
    $PAGE->navbar->add(get_string('reply', 'ouilforum'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('editpost', 'ouilforum'));
}

$PAGE->set_title("$course->shortname: $strdiscussionname ".format_string($toppost->subject));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($forum->name), 2);

// checkup
if (!empty($parent) && !ouilforum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
    print_error('cannotreply', 'ouilforum');
}
if (empty($parent) && empty($edit) && !ouilforum_user_can_post_discussion($forum, $groupid, -1, $cm, $modcontext)) {
    print_error('cannotcreatediscussion', 'ouilforum');
}

if ($forum->type == 'qanda'
            && !has_capability('mod/ouilforum:viewqandawithoutposting', $modcontext)
            && !empty($discussion->id)
            && !ouilforum_user_has_posted($forum->id, $discussion->id, $USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','ouilforum'));
}

// If there is a warning message and we are not editing a post we need to handle the warning.
if (!empty($thresholdwarning) && !$edit) {
    // Here we want to throw an exception if they are no longer allowed to post.
    ouilforum_check_blocking_threshold($thresholdwarning);
}

if (!empty($parent)) {
    if (!$discussion = $DB->get_record('ouilforum_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'ouilforum');
    }

    if ($reply) {
    	$parent = ouilforum_get_post_full($replyto);
    }
    ouilforum_print_post($parent, $discussion, $forum, $cm, $course, false, false, false,
    		null, null, null, null, null, null, OUILFORUM_DISPLAY_OPEN_CLEAN);
    if (empty($post->edit) && empty($reply)) {
        if ($forum->type != 'qanda' || ouilforum_user_can_see_discussion($forum, $discussion, $modcontext)) {
            $forumtracked = ouilforum_tp_is_tracked($forum);
            $posts = ouilforum_get_all_discussion_posts($discussion->id, "created ASC", $forumtracked);
            ouilforum_print_posts_threaded($course, $cm, $forum, $discussion, $parent, 0, false, $forumtracked, $posts);
        }
    }
} else {
    if (!empty($forum->intro)) {
        echo $OUTPUT->box(format_module_intro('ouilforum', $forum, $cm->id), 'generalbox', 'intro');

        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir.'/plagiarismlib.php');
            echo plagiarism_print_disclosure($cm->id);
        }
    }
}

if (!empty($formheading)) {
    echo $OUTPUT->heading($formheading, 2, array('class' => 'accesshide'));
}
$mform_post->display();

echo $OUTPUT->footer();

