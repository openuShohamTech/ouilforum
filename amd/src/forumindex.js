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
 * This class manages activities in a forum index.
 *
 * @module    mod_ouilforum/forumindex
 * @package   mod_ouilforum
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.1
 */
define(['jquery', 'jqueryui', 'core/ajax', 'core/str', 'mod_ouilforum/accessibility', 'mod_ouilforum/stagebutton', 'mod_ouilforum/toast'], function($, jqui, ajax, s, acc, sb) {
    // Private variables and functions.
	var PARAMS = {
		course: 0,
		str: {},
		infoDialog: null,
		currentDialog: null,
		pendingAction: null,
		toast: null,
		useHTMLFallback: false,
		compactMode: null
	};

    /**
     * Set collapse attributes for a post.
     * @param {DOMNode} node Post/discussion node.
     * @param {Bool} open Open or close the post.
     */
    var setPostAria = function(node, open) {
    	node.find('.post_link').attr('aria-expanded', open);
    	node.find('.of_post_main').attr('aria-hidden', !open);
    };

    /**
     * Called when subscribe button is pressed.
     */
    var subscribeForum = function() {
    	var node = $(this);
    	if (acc.isWaiting(node)) {
    		return;
    	}
    	if (PARAMS.compactMode) {
			if (!sb.hasOptions()) {
				sb.setOptions(node.next(), node.closest('.stage_button_container').attr('id'), runSubscribeForum);
			}
			sb.setDisplay();
    	} else {
    		acc.setWait(node, true);
    		runSubscribeForum(node);
    	}
    };
    
    /**
     * Sets an AJAX call for forum subscription.
     * @param {Node} node Subscribe button
     */
    var runSubscribeForum = function(node) {
    	var forum = node.attr('data-forumid');
    	var action = node.attr('data-actiontype');
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_subscribe_forum',
    		args: {
                forumid: forum,
                subscribe: action
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(node, false);
    		if (callResult == true) {
    			setForumButton(node, 'subscribe', action == 'subscribe', true);
    		}
    	}).fail(function(callResult) {
    		acc.setWait(node, false);
    		setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    	});
    };

    /**
     * Set the state of a forum button.
     * @param {Node} node Button node 
     * @param {String} type Button type 
     * @param {Bool} state Button active state 
     * @param {Bool} inform Inform screen reader 
     */
    var setForumButton = function(node, type, state, inform) {
		var newTitle, newLabel, newAction, newStatus;
		if (state) {
			newLabel = newTitle = PARAMS.str[type+'ForumYesLabel']+', '+PARAMS.str.enabled;
			newAction = 'un'+type;
			newStatus = 'on';
		} else {
			newLabel = newTitle = PARAMS.str[type+'ForumYesLabel']+', '+PARAMS.str.disabled;
			newAction = type;
			newStatus = 'off';
		}
		sb.setState(node.next(), state);
		node.attr('data-actiontype', newAction).attr('data-buttonstate', newStatus).attr('title', newTitle);
		if (inform) {
			acc.informScreenReader(node, PARAMS.str.done, newLabel, 'aria-label');
		} else {
			node.attr('aria-label', newLabel);
		}
    	
    };
    
    /**
     * Sets an AJAX call to subscribe/unsubscribe from all forums.
     */
    var subscribeAllForums = function(action) {
    	var node = $('#subscribealltrigger');
    	if (acc.isWaiting(node)) {
    		return;
    	}
    	acc.setWait(node, true);
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_subscribe_forums',
    		args: {
                courseid: PARAMS.course,
                subscribe: action
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(node, false);
    		if (callResult != '-1') {
    			if (callResult != '0') {
	    			var ids = callResult.split(',');
	    			$.each(ids, function(index, forumId) {
	    				var forumButton = $('#subscribeforum'+forumId);
	    				if (forumButton.length > 0) {
	    					setForumButton(forumButton, 'subscribe', action == 'subscribe', false);
	    				}
	    			});
    			}
    			var label = action === 'subscribe' ? PARAMS.str.subscribeForumIndexNoLabel : PARAMS.str.subscribeForumIndexYesLabel
    			acc.informScreenReader(node, PARAMS.str.done, label, 'aria-label');
    		} else {
    			acc.setWait(node, false);
    			setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    		}
    	}).fail(function(callResult) {
    		acc.setWait(node, false);
    		setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    	});
    };

    /**
     * Sets an AJAX call to track/untrack from all forums.
     */
    var trackAllForums = function() {
    	var node = $(this);
    	if (acc.isWaiting(node)) {
    		return;
    	}
    	var action = node.attr('data-action');
    	var trackOn = action == 'track';
    	acc.setWait(node, true);
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_track_forums',
    		args: {
                courseid: PARAMS.course,
                track: action
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(node, false);
    		if (callResult[0].id != -1) {
    			$.each(callResult, function(index, forum) {
    				var forumButton = $('#trackforum'+forum.id);
    				if (forumButton.length > 0) {
    					setForumButton(forumButton, 'track', trackOn, false);
    					setForumUnread(forum.id, forum.unread, trackOn);
    				}
    			});
    			acc.informScreenReader(node, PARAMS.str.done, node.attr('aria-label'), 'aria-label');
    		} else {
    			acc.setWait(node, false);
    			setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    		}
    	}).fail(function(callResult) {
    		acc.setWait(node, false);
    		setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    	});
    };

    /**
     * Called when track button is pressed.
     */
    var trackForum = function() {
    	var node = $(this);
    	if (acc.isWaiting(node)) {
    		return;
    	}
		if (!sb.hasOptions()) {
			sb.setOptions(node.next(), node.closest('.stage_button_container').attr('id'), runTrackForum);
		}
		sb.setDisplay();
    };
    
    /**
     * Sets an AJAX call for forum tracking.
     */
    var runTrackForum = function(node) {
    	acc.setWait(node, true);
    	var forum = node.attr('data-forumid');
    	var action = node.attr('data-actiontype');
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_track_forum',
    		args: {
                forumid: forum,
                track: action,
                inview: false
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(node, false);
    		if (callResult.result != false) {
	    		var newText, newLabel, newAction, newStatus;
	    		if (action == 'track') {
	    			newText = PARAMS.str.trackForumNo;
	    			newLabel = PARAMS.str.trackForumNoLabel;
	    			newAction = 'untrack';
	    			newStatus = 'on';
	    			setForumUnread(forum, callResult.unread, true);
	    		} else {
	    			newText = PARAMS.str.trackForumYes;
	    			newLabel = PARAMS.str.trackForumYesLabel;
	    			newAction = 'track';
	    			newStatus = 'off';
	    			setForumUnread(forum, 0, false);
	    		}
	    		sb.setState(node.next(), newStatus == 'on');
	    		node.attr('data-actiontype', newAction).attr('data-buttonstate', newStatus);
	    		node.find('.button_text').html(newText);
	    		acc.informScreenReader(node, PARAMS.str.done, newLabel, 'aria-label');
	    	}
    	}).fail(function(callResult) {
    		acc.setWait(node, false);
    		setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    	});
    };

    /**
     * Set the unread posts indicator in a forum.
     * @param {Int} forumId
     * @param {Int} unread Number of unread posts in the forum
     * @param {Bool} add Add/remove the indicator
     */
    var setForumUnread = function(forumId, unread, add) {
    	var unreadDiv = $('#forumunread'+forumId);
    	if (unreadDiv.length > 0) {
    		if (!add) {
    			unreadDiv.html('');
    		} else {
    			if (unread > 0) {
    				unread = '<a href="view.php?f='+forumId+'">'+unread+'</a>';
    			}
    			unreadDiv.html(PARAMS.str.unreadPosts+': <span class="forumlist_number">'+unread+'</span>');
    		}
    	}
    };
    
    /**
     * Init the info dialog.
     */
    var initInfoDialog = function() {
		PARAMS.infoDialog = $('<div id="info_dialog"></div>');
		PARAMS.infoDialog.html('<div id="info_dialog_content"></div>');
	    PARAMS.infoDialog.dialog({
	    	dialogClass: 'no-close',
	        resizable: false,
	        height: "auto",
	        minWidth: 200,
	        maxWidth: 600,
	        autoOpen: false,
	        modal: true,
	        draggable: false,
	        buttons: [
	            {
	            	text: PARAMS.str.copyLinkClose,
	            	click: function() {
	            		$(this).dialog("close");
	            		$(this).find('#info_dialog_content').html('');
	            	}
	            }
	        ]
	    });
    };

    /**
     * Toggle forum info display.
     */
    var toggleForumInfo = function() {
    	setForumInfo($(this).closest('.forum_container'), !$(this).hasClass('d_opened'));
    	setForumsListButton($(this).closest('ul'));
    };
    
    /**
     * Toggle info display for all forums in current list.
     */
    var toggleForumsInfo = function() {
    	var opened = $(this).hasClass('d_opened');
    	var newTitle = opened ? PARAMS.str.forumInfoShowAll : PARAMS.str.forumInfoHideAll; 
    	$(this).toggleClass('d_opened').attr('title', newTitle);
    	$(this).closest('.forum_list_header').next('ul').find('.forum_container').each(function() {
    		setForumInfo($(this), !opened);
    	});
    };

    /**
     * Match the info display button state to the the forums in current list.
     * @param {Node} list Forums list node
     */
    var setForumsListButton = function(list) {
    	var button = list.prev('.forum_list_header').find('button.forum_header_button'), forums = 0, closed = 0;
    	if (button.length == 0) {
    		return;
    	}
    	list.find('li .forum_body').each(function() {
    		forums++;
    		if ($(this).hasClass('hidden_element')) {
    			closed++;
    		}
    	});
    	if (closed == 0) {
    		button.addClass('d_opened').attr('title', PARAMS.str.forumInfoHideAll);
    	} else if (closed == forums) {
    		button.removeClass('d_opened').attr('title', PARAMS.str.forumInfoShowAll);
    	}
    }

    /**
     * Set the forum info display.
     * @param {Node} node Forum node
     * @param {Bool} open Display mode
     */
    var setForumInfo = function(node, open) {
    	var button = node.find('.forum_button');
    	var body = node.find('.forum_body');
    	var newText;
    	if (open) {
    		button.addClass('d_opened');
    		body.removeClass('hidden_element');
    		newText = PARAMS.str.forumInfoHide;
    	} else {
    		button.removeClass('d_opened');
    		body.addClass('hidden_element');
    		newText = PARAMS.str.forumInfoShow;
    	}
    	button.attr('aria-expanded', open).attr('title', newText);
    };
    
    /**
     * Set display compact mode.
     * @param {Bool} isCompact Is compact mode.
     */
    var setCompactMode = function(isCompact) {
    	if (PARAMS.compactMode == isCompact) {
    		return;
    	}
    	PARAMS.compactMode = isCompact;
    	if (!isCompact && sb.hasOptions()) {
    		sb.setDisplay(true);
    	}
    };

    /**
     * Mark all unread posts in a forum as read.
     * @param {Node} node Button node.
     */
    var markForumRead = function(node) {
    	var forumId = node.attr('data-forumid');
		acc.setWait(node, true);
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_mark_forum_read',
    		args: {
                forumid: forumId,
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(node, false);
    		if (callResult != false) {
    			node.prev().html('0');
    			$('#unreadmarker'+forumId).remove();
    			node.closest('.forum_container').find('.forum_list_title a').focus();
    			node.remove();
	    		acc.informScreenReader(node, PARAMS.str.done, '', 'aria-label');
	    	} else {
	    		setFailNode(node, false, 'aria-label', '');
	    	}
    	}).fail(function(callResult) {
    		acc.setWait(node, false);
    		setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    	});
    };

    /**
     * Set failure display.
     * @param {Node} node Target node.
     * @param {Bool} toast Toast message.
     * @param {String} attribute Attribute to update for the screen reader.
     * @param {String} text Attribute value.
     */
    var setFailNode = function(node, toast, attribute, text) {
		acc.markActionFail(node);
		if (toast) {
			PARAMS.toast.toast('show', PARAMS.str.fail);
		}
		if (attribute) {
			acc.informScreenReader(node, PARAMS.str.fail, text, attribute);
		}
    };

    /**
     * Init strings.
     */
    var initStrings = function() {
    	var strList = ['actionsuccess', 'actionfail', 'cancel', 'subscribeforum:no',
    	               'subscribeforum:nolabel', 'subscribeforum:yes', 'subscribeforum:yeslabel', 'tracking:no',
    	               'tracking:nolabel', 'tracking:yes', 'tracking:yeslabel', 'confirm',
    	               'unreadposts', 'markallread', 'foruminfohide', 'foruminfohideall',
    	               'foruminfoshow', 'foruminfoshowall', 'subscribeforumindex:no', 'subscribeforumindex:nolabel',
    	               'subscribeforumindex:yes', 'subscribeforumindex:yeslabel', 'trackingindex:no', 'trackingindex:nolabel',
    	               'trackingindex:yes', 'trackingindex:yeslabel', 'enabled', 'disabled'];
    	var strings = [];
    	$.each(strList, function(strId, strStr) {
    		strings.push({key: strStr, component: 'ouilforum'});
    	});
		s.get_strings(strings).done(function(s) {
			PARAMS.str.done = s[0];
			PARAMS.str.fail = s[1];
			PARAMS.str.cancel = s[2];
			PARAMS.str.subscribeForumNo = s[3];
			PARAMS.str.subscribeForumNoLabel = s[4];
			PARAMS.str.subscribeForumYes = s[5];
			PARAMS.str.subscribeForumYesLabel = s[6];
			PARAMS.str.trackForumNo = s[7];
			PARAMS.str.trackForumNoLabel = s[8];
			PARAMS.str.trackForumYes = s[9];
			PARAMS.str.trackForumYesLabel = s[10];
			PARAMS.str.confirm = s[11];
			PARAMS.str.unreadPosts = s[12];
			PARAMS.str.markAllRead = s[13];
			PARAMS.str.forumInfoHide = s[14];
			PARAMS.str.forumInfoHideAll = s[15];
			PARAMS.str.forumInfoShow = s[16];
			PARAMS.str.forumInfoShowAll = s[17];
			PARAMS.str.subscribeForumIndexNo = s[18];
			PARAMS.str.subscribeForumIndexNoLabel = s[19];
			PARAMS.str.subscribeForumIndexYes = s[20];
			PARAMS.str.subscribeForumIndexYesLabel = s[21];
			PARAMS.str.trackingIndexNo = s[22];
			PARAMS.str.trackingIndexNoLabel = s[23];
			PARAMS.str.trackingIndexYes = s[22];
			PARAMS.str.trackingIndexYesLabel = s[23];
			PARAMS.str.enabled = s[26];
			PARAMS.str.disabled = s[27];
			initInfoDialog();
        });
    };

    return {
		init: function(params) {
			PARAMS.course = params.course;
			initStrings();
			sb.init();
    		$('.forumslist .subscribeforumbutton').click(subscribeForum);
    		$('.forumslist .trackforumbutton').click(trackForum);
    		$('.simpleactionmenu.subscribeallmenu a[data-action]').click(function(e) {
    			e.preventDefault();
    			subscribeAllForums($(this).attr('data-action'));
    		});
    		$('#region-main').on('keyup', '.quicknewdialog', function(e) {
    			if (e.keyCode != 27) {
    				return;
    			}
    			$(this).find('button[data-action="cancel"]').click();
    		});
    		$('.forum_container .forum_button').click(toggleForumInfo);
    		$('.forum_list_header .forum_header_button').click(toggleForumsInfo);
    		$('.forum_unread').on('click', 'button', function(e) {
    			e.stopPropagation();
    			markForumRead($(this));
    		});
    		PARAMS.toast = $('<div></div>').appendTo('body');
    		PARAMS.toast.toast();
    		PARAMS.useHTMLFallback = $('<input type="email">')[0].type == 'text';
    		setCompactMode(window.innerWidth < 768);
    		$(window).resize(function(e) {
    			setCompactMode(window.innerWidth < 768);
    		});
		}
	};
});