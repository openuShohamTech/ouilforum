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
 * File containing the form definition to move a post in the forum.
 *
 * @package   mod_ouilforum
 * @copyright 2015 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');

class mod_ouilforum_move_form extends moodleform {

	function definition() {
		global $CFG, $OUTPUT, $DB;

        $mform = &$this->_form;

        $course = $this->_customdata['course'];
        $cm = $this->_customdata['cm'];
        $sourceforum = $this->_customdata['sourceforum'];
        $postid = $this->_customdata['postid'];
        $parentpost = $this->_customdata['parentpost'];
        $currentdiscussionid = $this->_customdata['currentdiscussionid'];
        $available_forums = $this->_customdata['availableforums'];

        $modcontext = context_module::instance($cm->id);

    	$alldiscussions = ouilforum_get_discussions_top($cm, $sourceforum->id);

    	$mform->addElement('hidden', 'f', $sourceforum->id);
    	$mform->setType('f', PARAM_INT);
    	$mform->addElement('hidden', 'p', $postid);
    	$mform->setType('p', PARAM_INT);

    	// Move discussion requires only a few elements
    	if ($parentpost == 0) {
    		$movestr = get_string('movediscussion', 'ouilforum');
    		$mform->addElement('selectgroups', 'targetforum', get_string('forumslist', 'ouilforum') , $available_forums, array('style' => 'max-width: 100%;'));
    		$mform->addElement('text', 'newtitle', get_string('move:newtitle', 'ouilforum'));
    		$mform->addRule('newtitle', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
    		$mform->addHelpButton('newtitle', 'move:newtitle', 'ouilforum');
    		$mform->setType('newtitle', PARAM_TEXT);
    		$mform->addElement('hidden', 'movetarget', 2);
    		$mform->setType('movetarget', PARAM_INT);
    	} else {
    		$movestr = get_string('movepost', 'ouilforum');
	
	        // If there is more then one discussion, choose between moving the message to a different ouilforum
	        // or this ouilforum.
	        $radioarray = array();
	        $discussionposts = ouilforum_count_discussion_posts($currentdiscussionid);
	        /*
	         Move in same forum conditions:
	         - If a parent post, and there's more than one discussion in the forum.
	         - If a child post, and there are more then two posts in the discussion OR there's more than one discussion in forum.
	         */
	        $move_sameforum = ($parentpost > 0 && ($discussionposts > 2 || count($alldiscussions) > 1));
	
	        // Move to a new discussion in same forum conditions:
	        // - If not a parent post.
	        $move_sameforumnew = $parentpost > 0;
	       	$mform->addElement('header', 'movemessageaction', get_string('move:action', 'ouilforum'));
	       	$radioarray[] = &$mform->createElement('radio', 'movetarget', '', get_string('move:newouilforum',  'ouilforum'), 2);
	       	if ($move_sameforum)
	        	$radioarray[] = &$mform->createElement('radio', 'movetarget', '', get_string('move:thisouilforum', 'ouilforum'), 1);
	       	if ($move_sameforumnew)
	        	$radioarray[] = &$mform->createElement('radio', 'movetarget', '', get_string('move:thisouilforumnew', 'ouilforum'), 3);
	
	        $mform->addGroup($radioarray, 'radioar', '', array('<br/>'), false);
	        $mform->addRule('radioar', null, 'required');
	        $mform->setType('radioar', PARAM_INT);
	
	        $mform->addElement('html', '<div id="newtitle">');
	        $mform->addElement('html', '<hr>');
	        $mform->addElement('text', 'newtitle', get_string('move:newtitle', 'ouilforum'));
	        $mform->addRule('newtitle', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
	        $mform->addHelpButton('newtitle', 'move:newtitle', 'ouilforum');
	        $mform->setType('newtitle', PARAM_TEXT);
	        $mform->addElement('html', '</div>');
	
	        if ($move_sameforum) {
	        	$mform->addElement('html', '<div id="discussionslist">');
	        	$mform->addElement('html', '<hr>');
	        	$mform->addElement('html', '<h4>'.get_string('move:discussionslist', 'ouilforum').'</h4>');
	        	
	        	$mform->addElement('html', '<ul class="discussionslist">');
	        	foreach ($alldiscussions as $discussion) {
	        		if(($discussion->id == $currentdiscussionid) || ($parentpost == 0 && $postid == $discussion->firstpost)) {
	        			continue;
	        		}
	        		$userObj = ouilforum_get_discussion_user_data($discussion, $sourceforum, $modcontext);
	        		if ($parentpost == $userObj->postid) {
	        			continue;
	        		}
        			// We need to get the posts first.
        			$posts = ouilforum_get_all_discussion_posts($discussion->id, "p.created ASC", false, $sourceforum->hideauthor, true);
        			// Get a short preview of the post content.
        			$attributes = array('class'=>'ouilforumpost post_body post_preview closed_post',
        					'id'=> 'postmain'.$userObj->postid,
        					'aria-hidden'=>'true');
        			$postcontent = ouilforum_display_post_content($posts[$userObj->postid], $course->id, $modcontext, $cm, $attributes, true);
        			$attributes = null;
        			$label = '';
        			$mform->addElement('html', '<li class="discussionslist">');
        			$mform->addElement('radio', 'postradio', '',
        					$label.'<a class="post_link" id="post'.$userObj->postid.'" href="#" data-postid="'.$userObj->postid.'" aria-controls="postmain'.
        					$userObj->postid.'" aria-expanded="false">'.$discussion->name.'</a><br>'.
        					$userObj->userdate.$postcontent, $userObj->postid, $attributes);
        			$mform->addElement('html', '</li>');
	        	}
	        	$mform->addElement('html', '</ul>');
	        	$mform->addElement('html', '</div>');
	        }
	        $mform->addElement('html', '<div id="forumslist">');
			$mform->addElement('selectgroups', 'targetforum', get_string('forumslist', 'ouilforum') , $available_forums, array('style' => 'max-width: 100%;'));
			$mform->addElement('html', '</div>');
    	}
    	$buttonarray = array();
		$buttonarray[] = &$mform->createElement('submit', 'submitbutton', $movestr);
		$buttonarray[] = &$mform->createElement('cancel');
		
		$mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
		$mform->setType('buttonar', PARAM_RAW);
		
	}

}

?>