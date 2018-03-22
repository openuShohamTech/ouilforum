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
require_once($CFG->libdir.'/completionlib.php');

$id          = optional_param('id', 0, PARAM_INT);        // Course Module ID.
$f           = optional_param('f', 0, PARAM_INT);         // Forum ID.
$mode        = optional_param('mode', 0, PARAM_INT);      // Display mode (for single forum).
$showall     = optional_param('showall', '', PARAM_INT);  // show all discussions on one page.
$changegroup = optional_param('group', -1, PARAM_INT);    // choose the current group.
$page        = optional_param('page', 0, PARAM_INT);      // which page to show.
$search      = optional_param('search', '', PARAM_CLEAN); // search string.

$params = array();
if ($id) {
	$params['id'] = $id;
} else {
    $params['f'] = $f;
}
if ($page) {
    $params['page'] = $page;
}
if ($search) {
    $params['search'] = $search;
}
$PAGE->set_url('/mod/ouilforum/view.php', $params);

if ($id) {
    if (!$cm = get_coursemodule_from_id('ouilforum', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
    if (!$forum = $DB->get_record('ouilforum', array('id' => $cm->instance))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if ($forum->type == 'single') {
        $PAGE->set_pagetype('mod-ouilforum-discuss');
    }
    require_course_login($course, true, $cm);
} else if ($f) {

    if (!$forum = $DB->get_record('ouilforum', array('id' => $f))) {
        print_error('invalidforumid', 'ouilforum');
    }
    if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
        print_error('coursemisconf');
    }
    if (!$cm = get_coursemodule_from_instance('ouilforum', $forum->id, $course->id)) {
        print_error('missingparameter');
    }
    require_course_login($course, true, $cm);
} else {
     print_error('missingparameter');
}

$context = context_module::instance($cm->id);
$PAGE->set_context($context);

if (!empty($CFG->enablerssfeeds) && !empty($CFG->ouilforum_enablerssfeeds) && $forum->rsstype && $forum->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");
    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($forum->name);
    rss_add_http_header($context, 'mod_ouilforum', $forum, $rsstitle);
}

// Print header.
$PAGE->set_title($forum->name);
$PAGE->add_body_class('ouilforumtype-'.$forum->type);
$PAGE->set_heading($course->fullname);

// Some capability checks.
if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
    notice(get_string('activityiscurrentlyhidden'));
}
if (!has_capability('mod/ouilforum:viewdiscussion', $context)) {
    notice(get_string('noviewdiscussionspermission', 'ouilforum'));
}
ouilforum_add_desktop_styles();    
// Mark viewed and trigger the course_module_viewed event.
ouilforum_view($forum, $course, $cm, $context);
$PAGE->requires->js_call_amd('mod_ouilforum/ouilforum', 'init', array(array(
		'forum'=>$forum->id,
		'forumtype'=>$forum->type,
		'page'=>$page
)));
$PAGE->requires->strings_for_js(array(
		'flagpost', 'unflagpost', 'recommendpost', 'unrecommendpost', 'clicktosubscribe', 'clicktounsubscribe',
    	'opendiscussionthread', 'closediscussionthread', 'discussionreply', 'discussionreplies', 
    	'cancel', 'delete', 'deletesure', 'deletesureplural', 'copylink:close', 'copylink:copied', 
    	'copylink:copy', 'copylink:failed', 'copylink:notsupported', 'copylink:title',
    	'subscribeforum:no', 'subscribeforum:nolabel', 'subscribeforum:yes', 'subscribeforum:yeslabel',
    	'tracking:no', 'tracking:nolabel', 'tracking:yes', 'tracking:yeslabel', 
    	'lockdiscussion', 'unlockdiscussion', 'forwardtitle', 'forwardsent',
    	'subscribediscussion:no', 'subscribediscussion:nolabel', 
    	'subscribediscussion:yes', 'subscribediscussion:yeslabel', 'copylink:gotolink',
    	'pgl:loadbutton', 'pgl:buttonclose', 'pgl:buttonnext', 'pgl:buttonprev',
    	'linktopostfield', 'forwarderror:empty', 'forwarderror:invalidemail', 'confirmdiscardcontentlock',
    	'enabled', 'disabled', 'postingfailed'
    )
	, 'ouilforum');

echo $OUTPUT->header();

echo ouilforum_print_top_panel($course, $search);
echo ouilforum_print_top_buttons($USER->id, $forum);
    
echo $OUTPUT->heading(format_string($forum->name), 2, 'forum_title');
if (!empty($forum->intro) && $forum->type != 'single' && $forum->type != 'teacher') {
    echo $OUTPUT->box(format_module_intro('ouilforum', $forum, $cm->id), 'generalbox', 'intro');
}
    
echo ouilforum_print_elements_for_js();

// Find out current groups mode.
echo '<div class="selector_wrapper_container">';
groups_print_activity_menu($cm, $CFG->wwwroot.'/mod/ouilforum/view.php?id='.$cm->id);
echo '</div>';

// Print settings and things across the top.
if ($mode) {
	$mode = ouilforum_normalize_layout_mode($mode);
	set_user_preference('ouilforum_displaymode', $mode);
}
$displaymode = get_user_preferences('ouilforum_displaymode', $CFG->ouilforum_displaymode);
    
// If it's a simple single discussion forum, we need to print the display mode control.
if ($forum->type == 'single') {
	$discussion = null;
    $discussions = $DB->get_records('ouilforum_discussions', array('ouilforum'=>$forum->id), 'on_top ASC, timemodified ASC');
    if (!empty($discussions)) {
    	if (isset($discussions[1])) {
    		echo $OUTPUT->notification('multidiscussionswarning', 'ouilforum');
    	}
        $discussion = array_pop($discussions);
    }
    if ($discussion) {
    	echo '<div class="clearfix forummode_container">';
    	echo '<div class="selector_wrapper_container float_end">';
        ouilforum_print_mode_form($forum->id, $displaymode, true);
        echo '</div>';
        echo '</div>';
    }
}

if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
    $a = new stdClass();
    $a->blockafter = $forum->blockafter;
    $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);
    echo $OUTPUT->notification(get_string('thisforumisthrottled', 'ouilforum', $a));
}

if ($forum->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
    echo $OUTPUT->notification(get_string('qandanotify','ouilforum'));
}

switch ($forum->type) {
    case 'single':
    	if (!empty($discussions) && count($discussions) > 1) {
            echo $OUTPUT->notification(get_string('warnformorepost', 'ouilforum'));
        }
        if (!$post = ouilforum_get_post_full($discussion->firstpost)) {
            print_error('cannotfindfirstpost', 'ouilforum');
        }

        $canreply    = ouilforum_user_can_post($forum, $discussion, $USER, $cm, $course, $context);
        $canrate     = has_capability('mod/ouilforum:rate', $context);
        if ($canreply) {
        	echo ouilforum_print_quick_reply_dialog();
        }
        echo '&nbsp;'; // This should fix the floating in FF.
        echo '<ul class="discussionslist">';
        ouilforum_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate);
        echo '</ul>';
        break;

    case 'eachuser':
    	echo '<p class="align_center">';
        if (ouilforum_user_can_post_discussion($forum, null, -1, $cm)) {
            print_string('allowsdiscussions', 'ouilforum');
        } else {
            echo '&nbsp;';
        }
        echo '</p>';
        if (!empty($showall)) {
            ouilforum_print_latest_discussions($course, $forum, 0, 'header', '', -1, -1, -1, 0, $cm);
        } else {
            ouilforum_print_latest_discussions($course, $forum, -1, 'header', '', -1, -1, $page, $CFG->ouilforum_manydiscussions, $cm);
        }
        break;

    case 'teacher':
    	if (!empty($showall)) {
            ouilforum_print_latest_discussions($course, $forum, 0, 'header', '', -1, -1, -1, 0, $cm);
        } else {
            ouilforum_print_latest_discussions($course, $forum, -1, 'header', '', -1, -1, $page, $CFG->ouilforum_manydiscussions, $cm);
        }
        break;

    case 'blog':
    	echo '<br>';
        $sort = $displaymode == OUILFORUM_MODE_FLATOLDEST ? 'p.created ASC' : 'p.created DESC';
        if (!empty($showall)) {
            ouilforum_print_latest_discussions($course, $forum, 0, 'plain', $sort, -1, -1, -1, 0, $cm);
        } else {
            ouilforum_print_latest_discussions($course, $forum, -1, 'plain', $sort, -1, -1, $page,
                $CFG->ouilforum_manydiscussions, $cm);
        }
        break;

    default:
    	echo '<br>';
        if (!empty($showall)) {
            ouilforum_print_latest_discussions($course, $forum, 0, 'header', '', -1, -1, -1, 0, $cm);
        } else {
            ouilforum_print_latest_discussions($course, $forum, -1, 'header', '', -1, -1, $page, $CFG->ouilforum_manydiscussions, $cm);
        }
        break;
}

echo $OUTPUT->footer($course);
