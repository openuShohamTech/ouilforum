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

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/mod/ouilforum/lib.php');
require_once($CFG->libdir.'/rsslib.php');

$id = optional_param('id', 0, PARAM_INT); // Course id.

$url = new moodle_url('/mod/ouilforum/index.php', array('id'=>$id));
$PAGE->set_url($url);

if ($id) {
    if (!$course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    $course = get_site();
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_ouilforum\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strforums       = get_string('forums', 'ouilforum');
$strforum        = get_string('forum', 'ouilforum');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'ouilforum');
$strsubscribed   = get_string('subscribed', 'ouilforum');
$strunreadposts  = get_string('unreadposts', 'ouilforum');
$strtracking     = get_string('tracking', 'ouilforum');
$strmarkallread  = get_string('markallread', 'ouilforum');
$strtrackforum   = get_string('trackforum', 'ouilforum');
$strnotrackforum = get_string('notrackforum', 'ouilforum');
$strsubscribe    = get_string('subscribe', 'ouilforum');
$strunsubscribe  = get_string('unsubscribe', 'ouilforum');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');
$strsubscribeyes = get_string('subscribeyes', 'ouilforum');
$strsubscribeno  = get_string('subscribeno', 'ouilforum');
$strtrackyes     = get_string('trackyes', 'ouilforum');
$strtrackno      = get_string('trackno', 'ouilforum');


// Retrieve the list of forum digest options for later.
$digestoptions = ouilforum_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/ouilforum/maildigest.php',
    array(
        'backtoindex' => 1,
    )),
    'maildigest',
    $digestoptions,
    null,
    '');
$digestoptions_selector->method = 'post';

// Start of the table for General Forums.

$generaltable = new html_table();
$generaltable->head  = array($strforum, $strdescription, $strdiscussions);
$generaltable->align = array('left', 'left', 'center');

if ($usetracking = ouilforum_tp_can_track_forums()) {
    $untracked = ouilforum_tp_get_untracked_forums($USER->id, $course->id);
}

// Fill the subscription cache for this course and user combination.
\mod_ouilforum\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = !isguestuser() && isloggedin() && has_capability('mod/ouilforum:viewdiscussion', $coursecontext);

$show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->ouilforum_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->ouilforum_enablerssfeeds);

$usesections = course_format_uses_sections($course->format);

// Parse and organise all the forums.  Most forums are course modules but
// some special ones are not.  These get placed in the general forums
// category with the forums in section 0.

$forums = $DB->get_records_sql('SELECT f.*,
           d.maildigest
      FROM {ouilforum} f
 LEFT JOIN {ouilforum_digests} d ON d.ouilforum = f.id AND d.userid = ?
     WHERE f.course = ?', 
		array($USER->id, $course->id));

$forumslist = array('generalforums' => array(), 'learningforums' => array());
$forumsoutput = array('generalforums' => array(), 'learningforums' => array());
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('ouilforum') as $forumid=>$cm) {
    if (!$cm->uservisible or !isset($forums[$forumid])) {
        continue;
    }

    $forum = $forums[$forumid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue; // Shouldn't happen.
    }

    if (!has_capability('mod/ouilforum:viewdiscussion', $context)) {
        continue;
    }

    // Fill two type array - order in modinfo is the same as in course.
    if ($forum->type == 'news' || $forum->type == 'social') {
        $forumslist['generalforums'][$forum->id] = $forum;

    } else if ($course->id == SITEID || empty($cm->sectionnum)) {
        $forumslist['generalforums'][$forum->id] = $forum;

    } else {
        $forumslist['learningforums'][$forum->id] = $forum;
    }
}

// Only real courses have learning forums.
if ($course->id == SITEID) {
	unset($forumslist['learningforums']);
}
$sub_count = 0;
$track_count = 0;
foreach ($forumslist as $forumtype=>$typelist) {
	foreach ($typelist as $forum) {
		$trackunread = 0;
		$cm      = $modinfo->instances['ouilforum'][$forum->id];
		$context = context_module::instance($cm->id);

		$count = ouilforum_count_discussions($forum, $cm, $course);
		
		if ($usetracking) {
			if ($forum->trackingtype == OUILFORUM_TRACKING_OFF) {
			} else {
				if (isset($untracked[$forum->id])) {
				} else if ($unread = ouilforum_tp_count_forum_unread_posts($cm, $course)) {
					$track_count++;
					$trackunread = $unread;
				} else {
					$track_count++;
				}
		
				if (($forum->trackingtype == OUILFORUM_TRACKING_FORCED) && ($CFG->ouilforum_allowforcedreadtracking)) {
				} else if ($forum->trackingtype === OUILFORUM_TRACKING_OFF || ($USER->trackforums == 0)) {
				}
			}
		}
		$forum->intro = format_module_intro('ouilforum', $forum, $cm->id);
		$forumname = format_string($forum->name, true);
		
		if ($cm->visible) {
			$style = '';
		} else {
			$style = 'class="dimmed"';
		}
		$forumlink = "<a href=\"view.php?f=$forum->id\" $style>".format_string($forum->name,true)."</a>";
		$discussionlink = "<a href=\"view.php?f=$forum->id\" $style>".$count."</a>";
		
	    $lastupdate = ouilforum_get_last_forum_update($cm, $course);
	    // If forum has no discussion, set the forum's updata date.
	    if ($lastupdate == 0) {
	    	$lastupdate = $forum->timemodified;
	    }
	    $roww = array(
	    		'forum'=>$forum,
	    		'visible'=>$cm->visible, 
	    		'name'=>format_string($forum->name, true),
	    		'intro'=>$forum->intro,
	    		'discussions'=>$count,
	    		'cantrack'=>$usetracking,
	    		'istracked'=>!isset($untracked[$forum->id]),
	    		'unread'=>$trackunread,
	    		'lastupdate'=>$lastupdate,
	    		'cansubscribe'=>$can_subscribe,
	    		'locked'=>ouilforum_is_forum_locked($forum)
	    );
	    // TODO: Set digest option in the interface.
        // If this forum has RSS activated, calculate it.
        if ($show_rss) {
        	if ($forum->rsstype and $forum->rssarticles) {
        		//Calculate the tooltip text
        		if ($forum->rsstype == 1) {
        			$tooltiptext = get_string('rsssubscriberssdiscussions', 'ouilforum');
        		} else {
        			$tooltiptext = get_string('rsssubscriberssposts', 'ouilforum');
        		}
        
        		if (!isloggedin() && $course->id == SITEID) {
        			$userid = guest_user()->id;
        		} else {
        			$userid = $USER->id;
        		}
        		// Get html code for RSS link.
        		$roww['rss'] = rss_get_link($context->id, $userid, 'mod_ouilforum', $forum->id, $tooltiptext);
        	} else {
        	}
        }
        $forumsoutput[$forumtype][$forum->id] = $roww;
        
	}
}
ouilforum_add_desktop_styles();
$PAGE->requires->js_call_amd('mod_ouilforum/forumindex', 'init', array(array(
		'course'=>$course->id
)));
$PAGE->requires->strings_for_js(array(
		'actionsuccess', 'actionfail', 'cancel', 'subscribeforum:no', 'subscribeforum:nolabel',
		'subscribeforum:yes', 'subscribeforum:yeslabel', 'tracking:no', 'tracking:nolabel',
		'tracking:yes', 'tracking:yeslabel', 'confirm', 'unreadposts', 'markallread',
		'subscribeforumindex:no', 'subscribeforumindex:nolabel', 'subscribeforumindex:yes', 'subscribeforumindex:yeslabel',
		'trackingindex:no', 'trackingindex:nolabel', 'trackingindex:yes', 'trackingindex:yeslabel')
		, 'ouilforum');

// Output the page.
$PAGE->navbar->add($strforums);
$PAGE->set_title("$course->shortname: $strforums");
$PAGE->add_body_class('path-mod-ouilforum2');
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo ouilforum_print_top_panel($course, '', false);

echo $OUTPUT->heading(get_string('forumslist', 'ouilforum'), 2, 'forum_index_title');
echo ouilforum_print_top_buttons_index_menu($can_subscribe, $usetracking);
if (!empty($forumsoutput['generalforums'])) {
	echo ouilforum_print_forums_list($forumsoutput['generalforums'], get_string('generalforums', 'ouilforum'));
}

if (!empty($forumsoutput['learningforums'])) {
	echo ouilforum_print_forums_list($forumsoutput['learningforums'], get_string('learningforums', 'ouilforum'));
}

echo $OUTPUT->footer();
