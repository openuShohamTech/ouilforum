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
 * Defines backup_ouilforum_activity_task class
 *
 * @package   mod_ouilforum
 * @category  backup
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/ouilforum/backup/moodle2/backup_ouilforum_stepslib.php');
require_once($CFG->dirroot.'/mod/ouilforum/backup/moodle2/backup_ouilforum_settingslib.php');

/**
 * Provides the steps to perform one complete backup of the Forum instance
 */
class backup_ouilforum_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance data in the ouilforum.xml file
     */
    protected function define_my_steps() {
        $this->add_step(new backup_ouilforum_activity_structure_step('ouilforum structure', 'ouilforum.xml'));
    }

    /**
     * Encodes URLs to the index.php, view.php and discuss.php scripts
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of forums.
        $search="/(".$base."\/mod\/ouilforum\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@OUILFORUMINDEX*$2@$', $content);

        // Link to forum view by moduleid.
        $search="/(".$base."\/mod\/ouilforum\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@OUILFORUMVIEWBYID*$2@$', $content);

        // Link to forum view by forumid.
        $search="/(".$base."\/mod\/ouilforum\/view.php\?f\=)([0-9]+)/";
        $content= preg_replace($search, '$@OUILFORUMVIEWBYF*$2@$', $content);

        // Link to forum discussion with parent syntax.
        $search = "/(".$base."\/mod\/ouilforum\/discuss.php\?d\=)([0-9]+)(?:\&amp;|\&)parent\=([0-9]+)/";
        $content= preg_replace($search, '$@OUILFORUMDISCUSSIONVIEWPARENT*$2*$3@$', $content);

        // Link to forum discussion with post highlight and hash.
        $search = "/(".$base."\/mod\/ouilforum\/discuss.php\?d\=)([0-9]+)(?:\&amp;|\&)p\=([0-9]+)\#p([0-9]+)/";
        $content= preg_replace($search, '$@OUILFORUMDISCUSSIONVIEWPOSTHASH*$2*$3*$3@$', $content);
        
        // Link to forum discussion with post highlight.
        $search = "/(".$base."\/mod\/ouilforum\/discuss.php\?d\=)([0-9]+)(?:\&amp;|\&)p\=([0-9]+)/";
        $content= preg_replace($search, '$@OUILFORUMDISCUSSIONVIEWPOST*$2*$3@$', $content);
        
        // Link to forum discussion with relative syntax.
        $search="/(".$base."\/mod\/ouilforum\/discuss.php\?d\=)([0-9]+)\#([0-9]+)/";
        $content= preg_replace($search, '$@OUILFORUMDISCUSSIONVIEWINSIDE*$2*$3@$', $content);

        // Link to forum discussion by discussionid.
        $search="/(".$base."\/mod\/ouilforum\/discuss.php\?d\=)([0-9]+)/";
        $content= preg_replace($search, '$@OUILFORUMDISCUSSIONVIEW*$2@$', $content);

        return $content;
    }
}
