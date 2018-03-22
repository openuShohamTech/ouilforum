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
 * Forum subscription manager.
 *
 * @package    mod_ouilforum
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ouilforum;

defined('MOODLE_INTERNAL') || die();

/**
 * Post actions manager.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_actions {
	
	/**
	* Unrecommend post message. Called through Ajax
	* @param stdClass|int $post post object or id
	* @return boolean
	*/
	public static function unrecommend_post($post) {
		global $DB;
		if (is_object($post)) {
			$post->mark = 0;
			return $DB->update_record('ouilforum_posts', $post);
		} else 
			return $DB->set_field('ouilforum_posts', 'mark', 0, array('id'=>$post));
	}

	/**
	 * Mark post as recommended. Called through Ajax
	 * @param stdClass|int $post post object or id
	 * @return boolean update success/fail
	 */
	public static function recommend_post($post) {
		global $DB;
		if (is_object($post)) {
			$post->mark = 1;
			return $DB->update_record('ouilforum_posts', $post);
		} else 
			return $DB->set_field('ouilforum_posts', 'mark', 1, array('id'=>$post));
	}
	
	/**
	 * Flag a post. Called through AJAX
	 * @param stdClass|int $post post object or id
	 * @param int $userid
	 */
	public static function add_flag($post, $userid, $flag=1) {
		global $DB;
		if (is_object($post))
			$post = $post->id;
		if ($record = $DB->get_record('ouilforum_flags', array('userid'=>$userid, 'postid'=>$post))) {
			if ($record->flag != $flag) {
				$record->flag = $flag;
				$record->flagged_date = time();
				$DB->update_record('ouilforum_flags', $record);
				return true;
			}
			return true;
		} else {
			$ouilforum_flag = new \stdClass();
			$ouilforum_flag->userid			= $userid;
			$ouilforum_flag->postid			= $post;
			$ouilforum_flag->flagged_date	= time();
			$ouilforum_flag->flag			= $flag;
			return $DB->insert_record('ouilforum_flags', $ouilforum_flag, false);
		}
	}
	
	/**
	 * Remove a flag made by a user in a post. Called by Ajax
	 * @param int $postid
	 * @param int $userid
	 */
	public static function remove_flag($postid, $userid) {
		global $DB;
		$DB->delete_records_select('ouilforum_flags', 'userid = ? AND postid = ?', array($userid, $postid));
	}

	/**
	 * Remove all flags in a post. Used when deleting a post. 
	 * To delete a flag for a specific user, use remove_flag().
	 * @param int $postid
	 */
	public static function remove_flags($postid) {
		global $DB;
		$DB->delete_records_select('ouilforum_flags', 'postid = ?', array($postid));
	}

	/**
	 * Returns the amount of flagged posts in a discussion
	 * @param int $discussionid discussion ID
	 * @paran int $userid
	 * @return number
	 */
	public static function count_flagged_posts($discussionid, $userid=null) {
		global $DB, $USER;
		if (!$userid)
			$userid = $USER->id;
		$sql = 'SELECT count(f.id)
    FROM {ouilforum_flags} f
	JOIN {ouilforum_posts} p on (f.postid = p.id AND f.userid = ?)
	JOIN {ouilforum_discussions} d on d.id = p.discussion
    WHERE d.id  = ?';
	
		return $DB->count_records_sql($sql, array($userid, $discussionid));
	}
	
	/**
	 * Returns the amount of recommended posts in a discussion
	 * @param int $discussionid
	 * @return number
	 */
	public static function count_recommended_posts($discussionid) {
		global $DB;
	
		$sql = 'SELECT count(p.discussion)
	FROM {ouilforum_posts} p
	WHERE p.discussion = ? AND mark = 1';
	
		return $DB->count_records_sql($sql, array($discussionid));
	}

	/**
	 * Check if the discussion has recommended posts.
	 * @param int $discussionid
	 * @return bool
	 */
	public static function is_discussion_recommended($discussionid) {
		global $DB;
		$sql = 'SELECT "x"
	FROM {ouilforum_posts}
	WHERE discussion = ? AND mark = 1';
		return $DB->record_exists_sql($sql, array($discussionid));
	}

	/**
	 * Check if the discussion has recommended posts.
	 * @param int $discussionid
	 * @return bool
	 */
	public static function is_discussion_flagged($discussionid, $userid) {
		global $DB, $USER;
		if (!$userid)
			$userid = $USER->id;
		$sql = 'SELECT "x"
    FROM {ouilforum_flags} f
	JOIN {ouilforum_posts} p on (f.postid = p.id AND f.userid = ?)
	JOIN {ouilforum_discussions} d on d.id = p.discussion
    WHERE d.id  = ?';
		return $DB->record_exists_sql($sql, array($userid, $discussionid));
	}
	
	/**
	 * Delete all flags made by a user in a discussion
	 * @param int $discussionid
	 * @param int $userid
	 * @return bool success/failure
	 */
	public static function delete_all_flags($discussionid, $userid) {
		global $DB;
		$where_sql = 'userid = ? and postid in (select id from {ouilforum_posts} where discussion = ?)';
		return $DB->delete_records_select('ouilforum_flags', $where_sql, array($userid, $discussionid));
	}

	/**
	 * Return a array of all discussions in the forum that have at least one flagged post.
	 * To avoid duplicate discussion id warning, the query is using the flag id and then return the distinct discussions
	 * @param int $forumid 
	 * @param int $userid the suer id. Optional
	 * @return array results array
	 */
	public static function get_discussions_flagged($forumid, $userid = null) {
		global $DB, $USER;
	
		if (!$userid)
			$userid = $USER->id;
		$sql = 'SELECT f.id, d.id AS discussion, f.flag
	FROM {ouilforum_discussions} d
	JOIN {ouilforum_posts} p ON p.discussion = d.id
	JOIN {ouilforum_flags} f
	ON (f.postid = p.id AND f.userid = ?)
	WHERE d.ouilforum = ?';
	
		if ($flagged_array = $DB->get_records_sql($sql, array($userid, $forumid))) {
			$discussions_array = array();
			foreach ($flagged_array as $flag_record) {
				// keep only the discussion id
				$discussions_array[$flag_record->discussion] = $flag_record->flag;
			}
			return $discussions_array;
		} else {
			return array();
		}
	}
	/**
	 * Get all discussions in the course module that have at least one recommended post
	 * @param int $forumid 
	 * @return array all Id's or empty array
	 */
	public static function get_discussions_recommended($forumid) {
		global $DB;
	
		$sql = 'SELECT DISTINCT d.id
	FROM {ouilforum_discussions} d
	JOIN {ouilforum_posts} p ON p.discussion = d.id
	WHERE d.ouilforum = ?
	AND p.mark = 1';
	
		if ($recommended_array = $DB->get_records_sql($sql, array($forumid))) {
			foreach ($recommended_array as $recommended_record) {
				$recommended_array[$recommended_record->id] = true;
			}
			return $recommended_array;
		} else {
			return array();
		}
	}
}