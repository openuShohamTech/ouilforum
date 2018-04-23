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
 * File containing the form definition to post in the forum.
 *
 * @package   mod_ouilforum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('FORMAT_TEXT',0);
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Class to post in a forum.
 *
 * @package   mod_ouilforum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_ouilforum_post_form_mobile extends moodleform {

    /**
     * Returns the options array to use in filemanager for forum attachments
     *
     * @param stdClass $forum
     * @return array
     */
    public static function attachment_options($forum) {
        global $COURSE, $PAGE, $CFG;
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes, $forum->maxbytes);
        return array(
            'subdirs' => 0,
            'maxbytes' => $maxbytes,
            'maxfiles' => $forum->maxattachments,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL
        );
    }

    /**
     * Returns the options array to use in forum text editor
     *
     * @param context_module $context
     * @param int $postid post id, use null when adding new post
     * @return array
     */
    public static function editor_options(context_module $context, $postid) {
        global $COURSE, $PAGE, $CFG;
        // TODO: add max files and max size support
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
        return array(
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $maxbytes,
            'trusttext'=> true,
            'return_types'=> FILE_INTERNAL | FILE_EXTERNAL,
            'subdirs' => file_area_contains_subdirs($context, 'mod_ouilforum', 'post', $postid)
        );
    }

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT;

        $mform =& $this->_form;

        $course = $this->_customdata['course'];
        $cm = $this->_customdata['cm'];
        $coursecontext = $this->_customdata['coursecontext'];
        $modcontext = $this->_customdata['modcontext'];
        $forum = $this->_customdata['ouilforum'];
        $post = $this->_customdata['post'];
        $subscribe = $this->_customdata['subscribe'];
        $edit = $this->_customdata['edit'];
        $thresholdwarning = $this->_customdata['thresholdwarning'];

        $mform->addElement('hidden', 'returnto');
        $mform->setType('returnto', PARAM_ALPHA);
        
     //   $mform->addElement('header', 'general', '');//fill in the data depending on page params later using set_data

        // If there is a warning message and we are not editing a post we need to handle the warning.
       // if (!empty($thresholdwarning) && !$edit) {
            // Here we want to display a warning if they can still post but have reached the warning threshold.
          //  if ($thresholdwarning->canpost) {
            //    $message = get_string($thresholdwarning->errorcode, $thresholdwarning->module, $thresholdwarning->additional);
              //  $mform->addElement('html', $OUTPUT->notification($message));
           // }
       // }

        $mform->addElement('text', 'subject', get_string('mobile_forum_title', 'ouilforum'), 'size="48"');
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        //$mform->addElement('editor', 'message', get_string('message', 'ouilforum'), null, self::editor_options($modcontext, (empty($post->id) ? null : $post->id)));
        //$mform->setType('message', PARAM_RAW);
        
       
        $mform->addElement('textarea', 'message', get_string('mobile_forum_message', 'ouilforum'), 'wrap="virtual" rows="5" cols="37"');
        $mform->setType('message', PARAM_TEXT);

            $mform->addElement('hidden', 'timestart');
            $mform->setType('timestart', PARAM_INT);
            $mform->addElement('hidden', 'timeend');
            $mform->setType('timeend', PARAM_INT);
            $mform->setConstants(array('timestart'=> 0, 'timeend'=>0));

        
           
        //-------------------------------------------------------------------------------
        // buttons
        if (isset($post->edit)) { // hack alert
            $submit_string = get_string('savechanges');
        } else {
            $submit_string = get_string('posttoforum_news', 'ouilforum');
        }

 
	       
        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'ouilforum');
        $mform->setType('ouilforum', PARAM_INT);

        $mform->addElement('hidden', 'discussion');
        $mform->setType('discussion', PARAM_INT);

        $mform->addElement('hidden', 'parent');
        $mform->setType('parent', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('hidden', 'reply');
        $mform->setType('reply', PARAM_INT);
        
        
        $this->add_action_buttons(true, $submit_string);
        
    }

    function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        // Filter received data.
        $data->subject = ouilforum_filter_post($data->subject, true);
        $data->message = ouilforum_filter_post($data->message);
        return $data;
    }

    function definition_after_data() {
    	parent::definition_after_data();
    	$mform = &$this->_form;
    	
    	// Filter user input.
    	$subject = $mform->getElement('subject');
    	$message = $mform->getElement('message');
    	$subject->setValue(ouilforum_filter_post($subject->getValue()));
    	$message->setValue(ouilforum_filter_post($message->getValue()));
    }

    /**
     * Form validation
     *
     * @param array $data data from the form.
     * @param array $files files uploaded.
     * @return array of errors.
     */
    
    function add_action_buttons($cancel = true, $submitlabel=null){
    	if (is_null($submitlabel)){
    		$submitlabel = get_string('savechanges');
    	}
    	$mform =& $this->_form;
    	if ($cancel){
    		//when two elements we need a group
    		$buttonarray=array();
    		$buttonarray[] = &$mform->createElement('button', 'submitbutton', $submitlabel);
    		$buttonarray[] = &$mform->createElement('button','cancel',get_string('cancelforum_news','ouilforum'));
    		$mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    		$mform->closeHeaderBefore('buttonar');
    	} else {
    		//no group needed
    		$mform->addElement('button', 'submitbutton', $submitlabel);
    		$mform->closeHeaderBefore('submitbutton');
    	}
    }
    
    
    /*
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (($data['timeend']!=0) && ($data['timestart']!=0) && $data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('timestartenderror', 'ouilforum');
        }
        if (empty($data['subject'])) {
            $errors['subject'] = get_string('erroremptysubject', 'ouilforum');
        }
        return $errors;
    }*/
}
