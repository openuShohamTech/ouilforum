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
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ouilforum;

defined('MOODLE_INTERNAL') || die();

/**
 * Forum subscription manager.
 *
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions {

    /**
     * The status value for an unsubscribed discussion.
     *
     * @var int
     */
    const OUILFORUM_DISCUSSION_UNSUBSCRIBED = -1;

    /**
     * The subscription cache for forums.
     *
     * The first level key is the user ID
     * The second level is the forum ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $forumcache = array();

    /**
     * The list of forums which have been wholly retrieved for the forum subscription cache.
     *
     * This allows for prior caching of an entire forum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedforums = array();

    /**
     * The subscription cache for forum discussions.
     *
     * The first level key is the user ID
     * The second level is the forum ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $forumdiscussioncache = array();

    /**
     * The list of forums which have been wholly retrieved for the forum discussion subscription cache.
     *
     * This allows for prior caching of an entire forum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedforums = array();

    /**
     * The digestion cache for forums.
     * 
     * The first level key is the user ID
     * The second level is the forum ID
     * The value is the digestion status (default 0)
     * @var array[]
     */
    protected static $digestcache = array();
    
    /**
     * Returns the current digestion mode for the forum.
     *
     * @param int $userid The ID of the user
     * 
     * @param int $userid
     * @param int $forumid
     * @return int The forum digestion mode
     */
    public static function get_digest_mode($userid, $forumid) {
    	return self::fetch_digest_cache($userid, $forumid);
    }
    
    /**
     * Returns the user's digestion mode for a forum.
     * 
     * @param int $userid
     * @param int $forumid
     */
    protected static function fetch_digest_cache($userid, $forumid) {
    	if (isset(self::$digestcache[$userid]) && isset(self::$digestcache[$userid][$forumid])) {
    		return self::$digestcache[$userid][$forumid];
    	}
    	self::fill_digest_cache($userid, $forumid);
    
    	if (!isset(self::$digestcache[$userid]) || !isset(self::$digestcache[$userid][$forumid])) {
    		return -1;
    	}
    
    	return self::$digestcache[$userid][$forumid];
    }
    
    /**
     * Returns the user's digestion mode for all forums in a course.
     * 
     * @param int $userid
     * @param int $courseid
     * @return array[]
     */
    public static function fetch_digest_in_course($userid, $courseid) {
    	self::fill_digest_cache($userid, null, $courseid);
    	return self::$digestcache[$userid];
    }
    
    /**
     * Fills the digestion cache for a single user. 
     * The query can be made for a single forum of for all forums in a course
     * 
     * @param int $userid
     * @param int $forumid
     * @param int $courseid
     * @return array[]
     */
    protected static function fill_digest_cache($userid, $forumid = null, $courseid = null) {
    	global $DB;
    
    	if (!isset(self::$digestcache[$userid])) {
    		self::$digestcache[$userid] = array();
    	}
    	// A single forum.
    	if (isset($forumid)) {
    		if (!isset(self::$digestcache[$userid][$forumid])) {
    			if (!$digest = $DB->get_record('ouilforum_digests', array('userid'=>$userid, 'ouilforum'=>$forumid))) {
    				self::$digestcache[$userid][$forumid] = 0;
    			} else {
    				self::$digestcache[$userid][$forumid] = $digest->maildigest;
    			}
    		}
    		return self::$digestcache[$userid][$forumid];
    	}
    	// All forums in course.
    	else if (isset($courseid)) {
    		$sql = 'SELECT f.id, d.ouilforum, d.userid, d.maildigest  
	FROM {ouilforum} f
	LEFT JOIN (
		SELECT userid, ouilforum, maildigest
			FROM {ouilforum_digests}
			WHERE userid=?
	) d ON (f.id = d.ouilforum)
	WHERE f.course=?';

    		if ($forums = $DB->get_records_sql($sql, array($userid, $courseid))) {
    			foreach ($forums as $forum) {
    				$digest = empty($forum->maildigest) ? 0 : $forum->maildigest;
    				self::$digestcache[$userid][$forum->id] = $digest;
    			}
    		}
   			return self::$digestcache[$userid];
    	}
    }
    
    /**
     * Whether a user is subscribed to this forum, or a discussion within
     * the forum.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the forum preference.
     *
     * If it is not specified then only the forum preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $forum The record of the forum to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $forum, $discussionid = null, $cm = null) {
        // If forum is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($forum)) {
            if (!$cm) {
                $cm = get_fast_modinfo($forum->course)->instances['ouilforum'][$forum->id];
            }
            if (has_capability('mod/ouilforum:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_forum($userid, $forum);
        }

        $subscriptions = self::fetch_discussion_subscription($forum->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::OUILFORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_forum($userid, $forum);
    }

    /**
     * Whether a user is subscribed to this forum.
     *
     * @param int $userid The user ID
     * @param \stdClass $forum The record of the forum to test
     * @return boolean
     */
    protected static function is_subscribed_to_forum($userid, $forum) {
        return self::fetch_subscription_cache($forum->id, $userid);
    }

    /**
     * Helper to determine whether a forum has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $forum The record of the forum to test
     * @return bool
     */
    public static function is_forcesubscribed($forum) {
        return ($forum->forcesubscribe == OUILFORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a forum has it's subscription mode set to disabled.
     *
     * @param \stdClass $forum The record of the forum to test
     * @return bool
     */
    public static function subscription_disabled($forum) {
        return ($forum->forcesubscribe == OUILFORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified forum can be subscribed to.
     *
     * @param \stdClass $forum The record of the forum to test
     * @return bool
     */
    public static function is_subscribable($forum) {
        return (!\mod_ouilforum\subscriptions::is_forcesubscribed($forum) &&
                !\mod_ouilforum\subscriptions::subscription_disabled($forum));
    }

    /**
     * Set the forum subscription mode.
     *
     * By default when called without options, this is set to OUILFORUM_FORCESUBSCRIBE.
     *
     * @param \stdClass $forum The record of the forum to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($forumid, $status = 1) {
        global $DB;
        return $DB->set_field("ouilforum", "forcesubscribe", $status, array("id" => $forumid));
    }

    /**
     * Returns the current subscription mode for the forum.
     *
     * @param \stdClass $forum The record of the forum to set
     * @return int The forum subscription mode
     */
    public static function get_subscription_mode($forum) {
        return $forum->forcesubscribe;
    }

    /**
     * Returns an array of forums that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable forums
     */
    public static function get_unsubscribable_forums() {
        global $USER, $DB;

        // Get courses that $USER is enrolled in and can see.
        $courses = enrol_get_my_courses();
        if (empty($courses)) {
            return array();
        }

        $courseids = array();
        foreach($courses as $course) {
            $courseids[] = $course->id;
        }
        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

        // Get all forums from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a forum in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {ouilforum} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {ouilforum_subscriptions} fs ON (fs.ouilforum = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'ouilforum',
            'userid' => $USER->id,
            'forcesubscribe' => OUILFORUM_FORCESUBSCRIBE,
        ));
        $forums = $DB->get_recordset_sql($sql, $params);

        $unsubscribableforums = array();
        foreach($forums as $forum) {
            if (empty($forum->visible)) {
                // The forum is hidden - check if the user can view the forum.
                $context = \context_module::instance($forum->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden forum to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribableforums[] = $forum;
        }
        $forums->close();

        return $unsubscribableforums;
    }

    /**
     * Get the list of potential subscribers to a forum.
     *
     * @param context_module $context the forum context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/ouilforum:allowforcesubscribe', $groupid, true);
        if (!$sort) {
            list($sort, $sortparams) = users_order_by_sql('u');
            $params = array_merge($params, $sortparams);
        }

        $sql = "SELECT $fields
                FROM {user} u
                JOIN ($esql) je ON je.id = u.id
            ORDER BY $sort";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fetch the forum subscription data for the specified userid and forum.
     *
     * @param int $forumid The forum to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($forumid, $userid) {
        if (isset(self::$forumcache[$userid]) && isset(self::$forumcache[$userid][$forumid])) {
            return self::$forumcache[$userid][$forumid];
        }
        self::fill_subscription_cache($forumid, $userid);

        if (!isset(self::$forumcache[$userid]) || !isset(self::$forumcache[$userid][$forumid])) {
            return false;
        }

        return self::$forumcache[$userid][$forumid];
    }

    /**
     * Fill the forum subscription data for the specified userid and forum.
     *
     * If the userid is not specified, then all subscription data for that forum is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $forumid The forum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($forumid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedforums[$forumid])) {
            // This forum has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$forumcache[$userid])) {
                    self::$forumcache[$userid] = array();
                }

                if (!isset(self::$forumcache[$userid][$forumid])) {
                    if ($DB->record_exists('ouilforum_subscriptions', array(
                        'userid' => $userid,
                        'ouilforum' => $forumid,
                    ))) {
                        self::$forumcache[$userid][$forumid] = true;
                    } else {
                        self::$forumcache[$userid][$forumid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('ouilforum_subscriptions', array(
                    'ouilforum' => $forumid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$forumcache[$data->userid])) {
                        self::$forumcache[$data->userid] = array();
                    }
                    self::$forumcache[$data->userid][$forumid] = true;
                }
                self::$fetchedforums[$forumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the forum subscription data for all forums that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$forumcache[$userid])) {
            self::$forumcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS forumid,
                    s.id AS subscriptionid
                FROM {ouilforum} f
                LEFT JOIN {ouilforum_subscriptions} s ON (s.ouilforum = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => OUILFORUM_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$forumcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this forum.
     *
     * @param stdClass $forum The forum record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the forum context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($forum, $groupid = 0, $context = null, $fields = null,
            $includediscussionsubscriptions = false) {
        global $CFG, $DB;

        if (empty($fields)) {
            $allnames = get_all_user_name_fields(true, 'u');
            $fields ="u.id,
                      u.username,
                      $allnames,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackforums,
                      u.mnethostid";
        }

        // Retrieve the forum context if it wasn't specified.
        $context = ouilforum_get_context($forum->id, $context);

        if (self::is_forcesubscribed($forum)) {
            $results = \mod_ouilforum\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['forumid'] = $forum->id;

            if ($includediscussionsubscriptions) {
                $params['sforumid'] = $forum->id;
                $params['dsforumid'] = $forum->id;
                $params['unsubscribed'] = self::OUILFORUM_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {ouilforum_subscriptions} s
                            WHERE
                                s.ouilforum = :sforumid
                                UNION
                            SELECT userid FROM {ouilforum_discussion_sub} ds
                            WHERE
                                ds.forumid = :dsforumid
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {ouilforum_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.ouilforum = :forumid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a forum.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('ouilforum', $forum->id, $forum->course);
        $modinfo = get_fast_modinfo($forum->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and forum.
     *
     * This is returned as an array of discussions for that forum which contain the preference in a stdClass.
     *
     * @param int $forumid The forum to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the forum.
     */
    public static function fetch_discussion_subscription($forumid, $userid = null) {
        self::fill_discussion_subscription_cache($forumid, $userid);

        if (!isset(self::$forumdiscussioncache[$userid]) || !isset(self::$forumdiscussioncache[$userid][$forumid])) {
            return array();
        }

        return self::$forumdiscussioncache[$userid][$forumid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and forum.
     *
     * If the userid is not specified, then all discussion subscription data for that forum is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $forumid The forum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($forumid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedforums[$forumid])) {
            // This forum hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$forumdiscussioncache[$userid])) {
                    self::$forumdiscussioncache[$userid] = array();
                }

                if (!isset(self::$forumdiscussioncache[$userid][$forumid])) {
                    $subscriptions = $DB->get_recordset('ouilforum_discussion_sub', array(
                        'userid' => $userid,
                        'forumid' => $forumid,
                    ), null, 'id, discussionid');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($forumid, $userid, $data->discussionid);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('ouilforum_discussion_sub', array(
                    'forumid' => $forumid,
                ), null, 'id, userid, discussionid');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($forumid, $data->userid, $data->discussionid);
                }
                self::$discussionfetchedforums[$forumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $forumid The ID of the forum that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     */
    protected static function add_to_discussion_cache($forumid, $userid, $discussion) {
        if (!isset(self::$forumdiscussioncache[$userid])) {
            self::$forumdiscussioncache[$userid] = array();
        }

        if (!isset(self::$forumdiscussioncache[$userid][$forumid])) {
            self::$forumdiscussioncache[$userid][$forumid] = array();
        }

        self::$forumdiscussioncache[$userid][$forumid][$discussion] = 1;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking forum discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$forumdiscussioncache = array();
        self::$discussionfetchedforums = array();
    }

    /**
     * Reset the forum cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking forum subscription states.
     */
    public static function reset_forum_cache() {
        self::$forumcache = array();
        self::$fetchedforums = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $forum The forum record for this forum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the ouilforum_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $forum, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $forum)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->ouilforum = $forum->id;

        $result = $DB->insert_record("ouilforum_subscriptions", $sub);

        if ($userrequest) {
        	$sub_params = array('userid' => $userid, 'forumid' => $forum->id);
            $discussionsubscriptions = $DB->get_recordset('ouilforum_discussion_sub', $sub_params);
            $DB->delete_records_select('ouilforum_discussion_sub',
                    'userid = :userid AND forumid = :forumid', $sub_params);

            // Reset the subscription caches for this forum.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$forumdiscussioncache[$userid]) && isset(self::$forumdiscussioncache[$userid][$forum->id])) {
                foreach (self::$forumdiscussioncache[$userid][$forum->id] as $discussionid => $preference) {
                    if ($preference != self::OUILFORUM_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$forumdiscussioncache[$userid][$forum->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this forum.
        self::$forumcache[$userid][$forum->id] = true;

        $context = ouilforum_get_context($forum->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('forumid' => $forum->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('ouilforum_discussion_sub', $subscription);
            }
            $discussionsubscriptions->close();
        }
        $event->trigger();

        return $result;
    }

    /**
     * Removes user from the subscriber list
     *
     * @param int $userid The ID of the user to unsubscribe
     * @param \stdClass $forum The forum record for this forum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $forum, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'ouilforum' => $forum->id,
        );
        $DB->delete_records('ouilforum_digests', $sqlparams);

        if ($forumsubscription = $DB->get_record('ouilforum_subscriptions', $sqlparams)) {
            $DB->delete_records('ouilforum_subscriptions', array('id' => $forumsubscription->id));

            if ($userrequest) {
            	$sub_params = array('userid' => $userid, 'forumid' => $forum->id);
                $discussionsubscriptions = $DB->get_recordset('ouilforum_discussion_sub', $sub_params);
                $DB->delete_records('ouilforum_discussion_sub', $sub_params);

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$forumdiscussioncache[$userid]) && isset(self::$forumdiscussioncache[$userid][$forum->id])) {
                    self::$forumdiscussioncache[$userid][$forum->id] = array();
                }
            }

            // Reset the cache for this forum.
            self::$forumcache[$userid][$forum->id] = false;

            $context = ouilforum_get_context($forum->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $forumsubscription->id,
                'relateduserid' => $userid,
                'other' => array('forumid' => $forum->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('ouilforum_subscriptions', $forumsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('ouilforum_discussion_sub', $subscription);
                }
                $discussionsubscriptions->close();
            }
            $event->trigger();
        }

        return true;
    }

    /**
     * Subscribes the user to the specified discussion.
     *
     * @param int $userid The userid of the user being subscribed
     * @param \stdClass $discussion The discussion to subscribe to
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function subscribe_user_to_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user is subscribed to the discussion already.
        $subscription = $DB->get_record('ouilforum_discussion_sub', array('userid' => $userid, 'discussionid' => $discussion->id));
        if ($subscription) {
        	return true;
        }
        // No discussion-level subscription. Check for a forum level subscription.
        if ($DB->record_exists('ouilforum_subscriptions', array('userid' => $userid, 'ouilforum' => $discussion->ouilforum))) {
            if ($subscription) {
                // The user is subscribed to the forum, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('ouilforum_discussion_sub', array('id' => $subscription->id));
                unset(self::$forumdiscussioncache[$userid][$discussion->ouilforum][$discussion->id]);
            } else {
                // The user is already subscribed to the forum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $DB->update_record('ouilforum_discussion_sub', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->forumid = $discussion->ouilforum;
                $subscription->discussionid = $discussion->id;

                $subscription->id = $DB->insert_record('ouilforum_discussion_sub', $subscription);
                self::$forumdiscussioncache[$userid][$discussion->ouilforum][$discussion->id] = 1;
            }
        }

        $context = ouilforum_get_context($discussion->ouilforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'forumid' => $discussion->ouilforum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_created::create($params);
        $event->trigger();

        return true;
    }
    /**
     * Unsubscribes the user from the specified discussion.
     *
     * @param int $userid The userid of the user being unsubscribed
     * @param \stdClass $discussion The discussion to unsubscribe from
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function unsubscribe_user_from_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user's subscription preference for this discussion.
        if (!$subscription = $DB->get_record('ouilforum_discussion_sub', array('userid' => $userid, 'discussionid' => $discussion->id))) {
        	return false;
        }
        $DB->delete_records('ouilforum_discussion_sub', array('id' => $subscription->id));
                unset(self::$forumdiscussioncache[$userid][$discussion->ouilforum][$discussion->id]);

        $context = ouilforum_get_context($discussion->ouilforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'forumid' => $discussion->ouilforum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }
    
    /**
     * Unsubscribe all users from a discussion.
     * @param stdClass $discussion The discussion to unsubscribe from
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     * @return bool Weather all subscriptions were removed
     */
    public static function unsubscribe_all_users_from_discussion($discussion, $context=null) {
        global $DB;

        // First check whether the user's subscription preference for this discussion.
        if (!$users = $DB->get_record('ouilforum_discussion_sub', array('discussionid' => $discussion->id))) {
        	return false;
        }
        $context = ouilforum_get_context($discussion->ouilforum, $context);
        $status = true;
        foreach ($users as $user) {
        	$status = $status && \mod_ouilforum\subscriptions::unsubscribe_user_from_discussion($user->userid, $discussion, $context);
        }
        return $status;
    }

}
