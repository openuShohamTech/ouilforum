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
 * Forum external functions and service definitions.
 *
 * @package   mod_ouilforum
 * @copyright 2012 Mark Nelson <markn@moodle.com>
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_ouilforum_get_forums_by_courses' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'get_forums_by_courses',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Returns a list of forum instances in a provided set of courses, if
            no courses are provided then all the forum instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/ouilforum:viewdiscussion'
    ),

    'mod_ouilforum_get_forum_discussions' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'get_forum_discussions',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'DEPRECATED (use mod_ouilforum_get_forum_discussions_paginated instead):
                            Returns a list of forum discussions contained within a given set of forums.',
        'type' => 'read',
        'capabilities' => 'mod/ouilforum:viewdiscussion, mod/ouilforum:viewqandawithoutposting'
    ),

    'mod_ouilforum_get_forum_discussion_posts' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'get_forum_discussion_posts',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Returns a list of forum posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/ouilforum:viewdiscussion, mod/ouilforum:viewqandawithoutposting'
    ),

    'mod_ouilforum_get_forum_discussions_paginated' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'get_forum_discussions_paginated',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Returns a list of forum discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/ouilforum:viewdiscussion, mod/ouilforum:viewqandawithoutposting'
    ),

    'mod_ouilforum_view_forum' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'view_forum',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'write',
        'capabilities' => 'mod/ouilforum:viewdiscussion'
    ),

    'mod_ouilforum_view_forum_discussion' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'view_forum_discussion',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Trigger the forum discussion viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/ouilforum:viewdiscussion'
    ),

    'mod_ouilforum_add_discussion_post' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'add_discussion_post',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Create new posts into an existing discussion.',
        'type' => 'write',
        'capabilities' => 'mod/ouilforum:replypost'
    ),

    'mod_ouilforum_add_quick_discussion_post' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'add_quick_discussion_post',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Create new posts into an existing discussion.',
        'type' => 'write',
    	'ajax' => 'true',
        'capabilities' => 'mod/ouilforum:replypost'
    ),
	'mod_ouilforum_add_discussion' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'add_discussion',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Add a new discussion into an existing forum.',
        'type' => 'write',
        'capabilities' => 'mod/ouilforum:startdiscussion'
    ),

    'mod_ouilforum_add_quick_discussion' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'add_quick_discussion',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Add a new discussion into an existing forum.',
        'type' => 'write',
    	'ajax' => 'true',
        'capabilities' => 'mod/ouilforum:startdiscussion'
    ),

	'mod_ouilforum_recommend_post' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'recommend_post',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Add or remove a post recommendation.',
        'type' => 'write',
        'ajax' => 'true'
    ),

    'mod_ouilforum_flag_post' => array(
    		'classname' => 'mod_ouilforum_external',
    		'methodname' => 'flag_post',
    		'classpath' => 'mod/ouilforum/externallib.php',
    		'description' => 'Add or remove a post flag.',
    		'type' => 'write',
    		'ajax' => 'true'
    ),
    
    'mod_ouilforum_single_read' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'single_read',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Mark a single post as read.',
        'type' => 'write',
        'ajax' => 'true'
    ),

    'mod_ouilforum_multiple_read' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'multiple_read',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Mark multiple posts as read.',
        'type' => 'write',
        'ajax' => 'true'
    ),

    'mod_ouilforum_subscribe_discussion' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'subscribe_discussion',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Set user subscription in a discussion.',
        'type' => 'write',
        'ajax' => 'true'
    ),

	'mod_ouilforum_delete_post' => array(
		'classname' => 'mod_ouilforum_external',
		'methodname' => 'delete_post',
		'classpath' => 'mod/ouilforum/externallib.php',
		'description' => 'Delete a post or a discussion.',
		'type' => 'write',
		'ajax' => 'true',
		'capabilities' => 'mod/ouilforum:deleteownpost'
	),

    'mod_ouilforum_subscribe_forum' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'subscribe_forum',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Set user subscription in a forum.',
        'type' => 'write',
        'ajax' => 'true',
        'capabilities' => 'mod/ouilforum:viewdiscussion'
    ),

    'mod_ouilforum_track_forum' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'track_forum',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Set user tracking in a forum.',
        'type' => 'write',
        'ajax' => 'true',
        'capabilities' => 'mod/ouilforum:viewdiscussion'
    ),

    'mod_ouilforum_subscribe_forums' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'subscribe_forums',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Set user subscription in all course forums.',
        'type' => 'write',
        'ajax' => 'true',
        'capabilities' => 'mod/ouilforum:viewdiscussion'
    ),

    'mod_ouilforum_track_forums' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'track_forums',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Set user tracking in all course forums.',
        'type' => 'write',
        'ajax' => 'true',
        'capabilities' => 'mod/ouilforum:viewdiscussion'
    ),
		
	'mod_ouilforum_lock_discussion' => array(
        'classname' => 'mod_ouilforum_external',
        'methodname' => 'lock_discussion',
        'classpath' => 'mod/ouilforum/externallib.php',
        'description' => 'Lock or unlock a discussion.',
        'type' => 'write',
        'ajax' => 'true',
        'capabilities' => 'mod/ouilforum:lockmessage'
    ),

	'mod_ouilforum_quick_forward_post' => array(
			'classname' => 'mod_ouilforum_external',
			'methodname' => 'quick_forward_post',
			'classpath' => 'mod/ouilforum/externallib.php',
			'description' => 'Forward a post.',
			'type' => 'write',
			'ajax' => 'true'
	),

	'mod_ouilforum_mark_forum_read' => array(
			'classname' => 'mod_ouilforum_external',
			'methodname' => 'mark_forum_read',
			'classpath' => 'mod/ouilforum/externallib.php',
			'description' => 'Mark all posts in the forum as read.',
			'type' => 'write',
			'ajax' => 'true',
			'capabilities' => 'mod/ouilforum:viewdiscussion'
	),
		
);
