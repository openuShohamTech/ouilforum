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
 * @copyright 2009 Petr Skoda (http://skodak.org)
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/ouilforum/lib.php');

    $settings->add(new admin_setting_configselect('ouilforum_displaymode', get_string('displaymode', 'ouilforum'),
                       get_string('configdisplaymode', 'ouilforum'), OUILFORUM_MODE_NESTED, ouilforum_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('ouilforum_replytouser', get_string('replytouser', 'ouilforum'),
                       get_string('configreplytouser', 'ouilforum'), 1));

    // Less non-HTML characters than this is short.
    $settings->add(new admin_setting_configtext('ouilforum_shortpost', get_string('shortpost', 'ouilforum'),
                       get_string('configshortpost', 'ouilforum'), 300, PARAM_INT));

    // More non-HTML characters than this is long.
    $settings->add(new admin_setting_configtext('ouilforum_longpost', get_string('longpost', 'ouilforum'),
                       get_string('configlongpost', 'ouilforum'), 600, PARAM_INT));

    // Number of discussions on a page.
    $settings->add(new admin_setting_configtext('ouilforum_manydiscussions', get_string('manydiscussions', 'ouilforum'),
                       get_string('configmanydiscussions', 'ouilforum'), 100, PARAM_INT));

    // Number of replies on a view page.
    $settings->add(new admin_setting_configtext('ouilforum_replieslimit', get_string('replieslimit', 'ouilforum'),
                       get_string('configreplieslimit', 'ouilforum'), 50, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->ouilforum_maxbytes)) {
            $maxbytes = $CFG->ouilforum_maxbytes;
        }
        $settings->add(new admin_setting_configselect('ouilforum_maxbytes', get_string('maxattachmentsize', 'ouilforum'),
                           get_string('configmaxbytes', 'ouilforum'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all forums.
    $settings->add(new admin_setting_configtext('ouilforum_maxattachments', get_string('maxattachments', 'ouilforum'),
                       get_string('configmaxattachments', 'ouilforum'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[OUILFORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'ouilforum');
    $options[OUILFORUM_TRACKING_OFF] = get_string('trackingoff', 'ouilforum');
    $options[OUILFORUM_TRACKING_FORCED] = get_string('trackingon', 'ouilforum');
    $settings->add(new admin_setting_configselect('ouilforum_trackingtype', get_string('trackingtype', 'ouilforum'),
                       get_string('configtrackingtype', 'ouilforum'), OUILFORUM_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('ouilforum_trackreadposts', get_string('trackforum', 'ouilforum'),
                       get_string('configtrackreadposts', 'ouilforum'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('ouilforum_allowforcedreadtracking', get_string('forcedreadtracking', 'ouilforum'),
                       get_string('forcedreadtracking_desc', 'ouilforum'), 0));

    // Default number of days that a post is considered old.
    $settings->add(new admin_setting_configtext('ouilforum_oldpostdays', get_string('oldpostdays', 'ouilforum'),
                       get_string('configoldpostdays', 'ouilforum'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('ouilforum_usermarksread', get_string('usermarksread', 'ouilforum'),
                       get_string('configusermarksread', 'ouilforum'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d", $i);
    }
    // Default time (hour) to execute 'clean_read_records' cron.
    $settings->add(new admin_setting_configselect('ouilforum_cleanreadtime', get_string('cleanreadtime', 'ouilforum'),
                       get_string('configcleanreadtime', 'ouilforum'), 2, $options));

    // Default time (hour) to send digest email.
    $settings->add(new admin_setting_configselect('ouildigestmailtime', get_string('digestmailtime', 'ouilforum'),
                       get_string('configdigestmailtime', 'ouilforum'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'ouilforum').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'ouilforum');
    }
    $settings->add(new admin_setting_configselect('ouilforum_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'ouilforum'),
            2 => get_string('posts', 'ouilforum')
        );
        $settings->add(new admin_setting_configselect('ouilforum_rsstype', get_string('rsstypedefault', 'ouilforum'),
                get_string('configrsstypedefault', 'ouilforum'), 0, $options));

        $options = array(
            0  => '0',
            1  => '1',
            2  => '2',
            3  => '3',
            4  => '4',
            5  => '5',
            10 => '10',
            15 => '15',
            20 => '20',
            25 => '25',
            30 => '30',
            40 => '40',
            50 => '50'
        );
        $settings->add(new admin_setting_configselect('ouilforum_rssarticles', get_string('rssarticles', 'ouilforum'),
                get_string('configrssarticlesdefault', 'ouilforum'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('ouilforum_enabletimedposts', get_string('timedposts', 'ouilforum'),
                       get_string('configenabletimedposts', 'ouilforum'), 0));

    $settings->add(new admin_setting_configcheckbox('ouilforum_enableredirectpage', get_string('enableredirectpage', 'ouilforum'),
                       get_string('configenableredirectpage', 'ouilforum'), 0));
    
    $settings->add(new admin_setting_configselect('ouilforum_splitshortname', get_string('splitshortname', 'ouilforum'),
    		get_string('configsplitshortname', 'ouilforum'), 0, array(
    				OUILFORUM_EXTRACT_SHORTNAME_NONE => get_string('splitshortname:none', 'ouilforum'),
    				OUILFORUM_EXTRACT_SHORTNAME_PRE => get_string('splitshortname:pre', 'ouilforum'),
    				OUILFORUM_EXTRACT_SHORTNAME_POST => get_string('splitshortname:post', 'ouilforum')
    		)));
    
    $settings->add(new admin_setting_configtext_with_maxlength('ouilforum_shortnamedelimiter', get_string('shortnamedelimiter', 'ouilforum'),
    		get_string('configshortnamedelimiter', 'ouilforum'), '', PARAM_RAW_TRIMMED, 1, 1));
    
}