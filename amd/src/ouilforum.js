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
 * This class manages the majority of the fron-end actions in the forum.
 * Most of the other classes are called from here.
 *
 * @module    mod_ouilforum/ouilforum
 * @package   mod_ouilforum
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.1
 */
define(['jquery', 'jqueryui', 'core/ajax', 'core/str', 'mod_ouilforum/accessibility', 'mod_ouilforum/stagebutton', 'mod_ouilforum/postgallery', 'mod_ouilforum/ga', 'mod_ouilforum/ouilsimplemenu', 'mod_ouilforum/toast'], function($, jqui, ajax, s, acc, sb, pgl, fga) {
    // Private variables and functions.
	var PARAMS = {
		forum: 0,
		forumType: '',
		forumPage: 0,
		singlePage: true,
		discussions: 0,
		quickReply: {
			parent: 0,
			parentOrSibling: 0,
			discussion: 0,
			dialog: null,
			path: '',
			title: '',
			prefix: ''
		},
		str: {},
		delDialog: null,
		delNode: {
			node: [],
			postId: 0
		},
		linkDialog: null,
		closeDialog: null,
		forwardDialog: {
			parent: 0,
			dialog: null,
			path: '',
		},
		infoDialog: null,
		newIcons: {},
		currentDialog: null,
		pendingAction: null,
		toast: null,
		useHTMLFallback: false,
		canCopy: false,
		compactMode: null,
		discussionSub: null,
		isIE: false,
		digestBlockNode: null,
		digestMenuClass: null,
		subscribeBlockNode: null,
		settingType: null,
		ajaxCall: {
			targetId: '',
			methodName: ''
		}
	};

    /**
     * Toogle the discussion display.
     */
    var toggleDiscussion = function() {
    	var discussionId = $(this).attr('data-discussionid'),  
    	    discussion = $('#discussion'+discussionId), newText;
    	if ($(this).hasClass('d_opened')) {
    		discussion.find('.ouilforum_post').addClass('closed_post');
    		newText = PARAMS.str.openDiscussionThread;
    		setPostAria(discussion, false);
    	} else {
    		discussion.find('.ouilforum_post').removeClass('closed_post');
    		newText = PARAMS.str.closeDiscussionThread;
    		if (discussion.find('.discussion_new')) {
    		    markAllPostsAsRead(discussion);
    		}
    		setPostAria(discussion, true);
    	}
    	$(this).attr('title', newText).attr('aria-label', newText).toggleClass('d_opened');
    };

    /**
     * Open or close a single post.
     * @param {Event} e
     */
    var togglePost = function(e) {
    	if (e.type == 'keyup' && e.keyCode != 13) { return false; }
    	var postId = $(this).attr('data-postid'), 
    	    pContainer = $('#post'+postId+'container'), 
    	    pHeader = pContainer.find('.of_post_header:first'), 
    	    unread = pContainer.find('#unread'+postId);
    	if (unread.length > 0) {
    		markPostAsRead(postId);
    	}
    	pContainer.toggleClass('closed_post');
    	setPostAria(pContainer, !pContainer.hasClass('closed_post'));
    	setDiscussionButton(pContainer.closest('.ouilforum_discussion'));
    };

    /**
     * Mark post as read by user.
     * @param {Int} postId.
     * @returns
     */
    var markPostAsRead = function(postId) {
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_single_read',
    		args: {
    			forum : PARAMS.forum,
    			post : postId
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		if (callResult === true) {
    			$('#unread'+postId).remove();
	    	    var pContainer = $('#post'+postId), 
	    	        dContainer = getDiscussion(pContainer), 
	    	        unread = getUnreadPosts(dContainer, 3, true);
	    	    if (unread === 0) {
	    	    	setDiscussionRead(dContainer);
	    	    }
    		}
    	});
    };

    /**
     * Remove the new posts indicator from the discussion if there are no unread replies.
     * @param {Node} discussion.
     */
    var setDiscussionRead = function(discussion) {
    	if (!discussion.find('.posts_limit').length) {
    		discussion.find('.discussion_new').remove();
    	} else {
    		if (!discussion.find('.numbers').html().length) {
    			discussion.find('.discussion_new').remove();
    		}
    	}
    };
    
    /**
     * Mark all posts in a discussion as read by user.
     * @param {Node} discussion Discussion node
     */
    var markAllPostsAsRead = function(discussion) {
    	var unread = getUnreadPosts(discussion, 2, false);
    	if (unread.length === 0) { return; }
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_multiple_read',
    		args: {
    			forum : PARAMS.forum,
    			discussionid : discussion.attr('data-discussionid'),
    			posts : unread
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		if (callResult === true) {
    			discussion.find('.post_new').remove();
    			setDiscussionRead(discussion);
    		}
    	});
    };

    /**
     * Get all available unread posts in a discussion.
     * @param {Node} discussion Discussion node.
     * @param {Int} returnType 1 - return a list of nodes, 2 - return a list of posts ids, 3 - return amount.
     */
    var getUnreadPosts = function(discussion, returnType, ignoreFirstPost) {
    	var ids = [], unread, selector;
    	selector = ignoreFirstPost ? '.of_new_post .post_new' : '.post_new';
    	unread = discussion.find(selector);
    	if (returnType == 3) {
    		return unread.size();
    	} else if (returnType == 1) {
    		return unread;
    	} else {
    		unread.each(function() {
    			ids.push($(this).attr('id').substr(6));
    		});
    		return ids.join();
    	}
    };

    /**
     * Match discussion button state to the total posts state in the discussion.
     * @param {node} discussion
     */
    var setDiscussionButton = function(discussion) {
    	var button = discussion.find('button.discussion_button'), posts = 0, closed = 0;
    	if (button.length == 0) {
    		return;
    	}
    	discussion.find('.ouilforum_post').each(function() {
    		posts++;
    		if ($(this).hasClass('closed_post')) {
    			closed++;
    		}
    	});
    	if (closed == 0) {
    		button.addClass('d_opened');
    	} else if (closed == posts) {
    		button.removeClass('d_opened');
    	}
    }
    
    /**
     * Set collapse attributes for a post.
     * @param {DOMNode} node Post/discussion node.
     * @param {Bool} open Open or close the post.
     */
    var setPostAria = function(node, open) {
    	node.find('.post_subject').attr('aria-expanded', open);
    	node.find('.of_post_main').attr('aria-hidden', !open);
    };

    /**
     * Handle the AJAX call for the flag action.
     * @param {Event} e 
     */
    var flagPost = function(e) {
    	e.preventDefault();
    	var node = $(this);
    	var action = node.attr('data-flagvalue');
    	var menu = node.closest('.simpleactionmenu');
    	var trigger = menu.find('> button');
    	if (action == menu.attr('data-flagstatus')) {
    		return;
    	}
    	if (acc.isWaiting(trigger)) {
    		return;
    	}
    	var discussionId = getDiscussion($(this), true);
    	var postId = menu.attr('data-postid');
    	acc.setWait(trigger, true);
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_flag_post',
    		args: {
    			action: action,
    			post: postId,
    			discussion: discussionId
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(trigger, false);
    		var newText = action == 0 ? PARAMS.str.flagMenuNone : PARAMS.str.flagMenu + ' ' + action;
    		menu.attr('data-postid', action).attr('data-flagstatus', action);
    		trigger.attr('title', newText);
    		acc.informScreenReader(trigger, PARAMS.str.done, newText, 'aria-label');
    	}).fail(function(callResult) {
    		acc.setWait(trigger, false);
    		setFailNode(trigger, true, 'aria-label', trigger.attr('aria-label'));
    	});
    };

    /**
     * Handle the AJAX call for the recommend action.
     * @param {Event} e 
     */
    var recommendPost = function(e) {
    	e.preventDefault();
    	var node = $(this);
    	if (acc.isWaiting(node)) {
    		return;
    	}
    	var action = node.attr('data-action');
    	var postId = node.attr('data-postid');
    	var discussionId = getDiscussion(node, true);
    	acc.setWait(node, true);
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_recommend_post',
    		args: {
    			action: action,
    			post: postId,
    			discussion: discussionId
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(node, false);
    		var newText;
    		if (action == 'recommendpost') {
    			node.attr('data-action', 'unrecommendpost');
    			newText = PARAMS.str.unrecommendPost;
    		} else {
    			node.attr('data-action', 'recommendpost');
    			newText = PARAMS.str.recommendPost;
    		}
    		node.attr('title', newText);
    		acc.informScreenReader(node, PARAMS.str.done, newText, 'aria-label');
    	}).fail(function(callResult) {
    		acc.setWait(node, false);
    		setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    	});
    };

    /**
	 * Find the parent discussion of a post.
	 * @param {DOMNode} node The post node.
	 * @param {Bool} idOnly Return the discussion id of the whole node.
	 */
    var getDiscussion = function(node, idOnly) {
    	var discussion = node.closest('.ouilforum_discussion');
    	if (idOnly) { return discussion.attr('data-discussionid'); }
    	else { return discussion; }
    };

    /**
     * Reset input fields in a dialog.
     * @param {Node} node Dialog node.
     * @param {Bool} resetMask Reset waiting mask node.
     */
    var resetDialogInput = function(node, resetMask) {
    	node.find('input[type="text"], input[type="email"], textarea').val('');
    	node.find('input[type="checkbox"]').prop('checked', false);
    	node.find('button').prop('disable', false);
    	node.find('.dialog_field_alert_label').remove();
    	node.find('.empty_field_alert').removeClass('empty_field_alert');
    	node.find('.quick_dialog_alert').html('');
    	if (resetMask) {
    		node.find('.waitmask').addClass('hidden_element').removeClass('active');
    	}
    };
    
    /**
     * Set display state of buttons in the reply dialog.
     * @param {Bool} show The display state
     */
    var setReplyButtons = function(show) {
    	if (show) {
    		PARAMS.quickReply.dialog.find('.conditional_button').removeClass('hidden_element');
    	} else {
    		PARAMS.quickReply.dialog.find('.conditional_button').addClass('hidden_element');
    	}
    };
    
    /**
     * Send a reply.
     */
    var sendQuickReply = function() {
    	
    	$('#newpostbody .empty_field_alert').removeClass('empty_field_alert');
    	var subject = $('#newposttop span').html();
    	var newSubject = $.trim($('#quickreplysubject').val());
    	var message = $('#newpostbody textarea');
    	var messageText = $.trim(message.val());
    	var errors = false;
    	if (messageText.length == 0 && newSubject.length == 0) {
    		errors = true;
    		message.addClass('empty_field_alert');
    	}
    	if (errors) {
    		return;
    	}
    	var postParent = $('#post'+PARAMS.quickReply.parent+'container');
    	var postLevel = 1;
    	if (postParent.hasClass('postlevel1') || postParent.hasClass('postlevel2')) {
    		postLevel = 2;
    	}
    	if (newSubject.length > 0) {
    		subject = newSubject;
    	}
    	setMask('quickpostmask', true, false);
    	bindResult('reply');
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_add_quick_discussion_post',
    		args: {
                postid: PARAMS.quickReply.parent,
                subject: subject,
                message: messageText,
                ashtml: 1,
                postlevel: postLevel,
                options: [
                	{
                   		name: 'discussionsubscribe',
                   		value: 0
                    }
                ]
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		if (!callResult.error) {
	    		closeQuickReply(true);
	    		var appendResult, discussion, newPost = $.parseHTML(callResult.post);
	    		// Send adding post to google analytics.
	    	    fga.send('addpost', callResult.postid);

	    	    if (callResult.singlepost) {
	    			appendPost($(newPost), postParent);
		    		$(newPost).addClass('new_quick_discussion');
		    		scrollToPost($(newPost));
	    		} else {
	    			discussion = getDiscussion(postParent);
	    			appendReplies($(newPost), discussion, callResult.postid);
	    		}
	    	} else {
	    		$('#quickpostmask').removeClass('active').delay(1).queue(function() {
	    			$(this).addClass('hidden_element').dequeue();	
		    		PARAMS.quickReply.dialog.find('.quick_dialog_alert').html(callResult.errormessage);
		    		if (callResult.errortype == 'locked') {
		    			var discussion = getDiscussion(postParent), lockButton = discussion.find('.of_post_icons button[data-actiontype="lock"]');
		    			lockDiscussion(discussion, true, callResult.replybutton, lockButton, callResult.lockicon);
		    			setReplyButtons(false);
		    		}
	    		});
	    	}
    	}).fail(function(callResult) {
    		setMask('quickpostmask', false, false);
    		$('#newpostbody input[name="subject"]').addClass('empty_field_alert');
    		$('#newpostbody textarea').addClass('empty_field_alert');
    	});
    };

    /**
     * Find the closest parent post. The first post of a first level post.
     * A second level post cannot be a parent.
     * @param {Node} node The post node the user is replying to
     * @param {Bool} onlyId Return the id or the node
     */
    var getReplyParent = function(node, onlyId) {
    	var parentNode;
    	if (node.hasClass('firstpost') || node.hasClass('postlevel1')) {
    		parentNode = node;
    	} else {
    		parentNode = node.closest('.indentlevel1').find('.ouilforum_post.postlevel1');
    	}
		if (onlyId) {
			return parentNode.attr('data-postid');
		} else {
			return parentNode;
		}
    };
    
    /**
     * Binds a listener to the 'acaxComplete' event.
     * In some cases, a server error or timeout might not trigger the 'done' or 'fail' responses.
     * This function will catch such events.
     * @param {String} actionType The id of the masking element in the dialog
     */
    var bindResult = function(actionType) {
    	if (actionType == 'discussion') {
    		PARAMS.ajaxCall.targetId = 'quickdiscussionmask'; 
    		PARAMS.ajaxCall.methodName = 'mod_ouilforum_add_quick_discussion';
    	} else if (actionType == 'reply') {
    		PARAMS.ajaxCall.targetId = 'quickpostmask'; 
    		PARAMS.ajaxCall.methodName = 'mod_ouilforum_add_quick_discussion_post';
    	}
    	$(document).unbind('ajaxComplete', bindHook); // Just in case.
		$(document).bind('ajaxComplete', bindHook);
    };
    
    /**
     * The 'ajaxComplete' hook.
     */
    var bindHook = function(event, xhr, settings) {
    	if (settings.context[0].methodname == PARAMS.ajaxCall.methodName) { // Make sure this is the right AJAX call.
    		$(document).unbind('ajaxComplete', bindHook);
    		// No valid response or a server error.
    		if ((xhr.status == 200 && xhr.responseText.length == '') || xhr.status >= 500) {
    			setMask(PARAMS.ajaxCall.targetId, false, false);
    			$('#'+PARAMS.ajaxCall.targetId).closest('.quicknewdialog').find('.quick_dialog_alert').html(PARAMS.str.postingFailed);
    			PARAMS.ajaxCall.targetId = '';
    			PARAMS.ajaxCall.methodName = '';
    		}
    	}
    };
    
    /**
     * Scroll to the new post, if off screen.
     * @param {Node} node New post element
     */
    var scrollToPost = function(node) {
		if (!node.inView(true)) {
			$('html, body').animate({
				scrollTop: node.find('a.post_anchor').offset().top
			}, {
				duration: 800,
				complete: function() {
					node.find('.post_subject').focus();
				}
			});
		}
    };
    
    /**
     * Append a new post to its parent.
     * @param {Node} newPost The new post element
     * @param {Node} parentPost The parent post to append to
     */
    var appendPost = function(newPost, parentPost) {
    	if (parentPost.hasClass('firstpost')) {
    		getDiscussion(parentPost).find('.lastpost').removeClass('lastpost');
    		newPost.find('.ouilforum_post').addClass('lastpost');
    		var children = parentPost.next('.discussionslist');
    		children.append(newPost);
    	} else {
    		var children = parentPost.nextAll('.indent');
    		if (children.length > 0) {
    			var lastPost = children.find('.lastpost');
    			if (lastPost.length > 0) {
    				lastPost.removeClass('lastpost');
    				newPost.find('.ouilforum_post').addClass('lastpost');
    			}
    			children.last().after(newPost);
    		} else {
    			if (parentPost.hasClass('lastpost')) {
    				parentPost.removeClass('lastpost');
    				newPost.find('.ouilforum_post').addClass('lastpost');
    			}
    			parentPost.after(newPost);
    		}
    	}
		$(newPost).find('.simpleactionmenu').each(function() {
			$(this).ouilsimpleMenu();
		});
		setRepliesCount(getDiscussion(parentPost));
    };
    
    /**
     * Append a new post to its parent.
     * @param {Node} replies The discussion replies
     * @param {Node} discussion The parent discussion to append to
     * @param {Int} postId The new post id (optional)
     */
    var appendReplies = function(replies, discussion, postId) {
		setRepliesCount(discussion);
    	discussion.css('minHeight', discussion.css('height'));
    	replies.css('display', 'none');
    	discussion.find('.discussionslist').fadeOut(200, function() {
    		$(this).remove();
    		discussion.append(replies);
    		replies.find('.simpleactionmenu').each(function() {
    			$(this).ouilsimpleMenu();
    		});
    		findPostImages('#'+discussion.attr('id'));
    		replies.fadeIn(400, function() {
    			discussion.css('minHeight', '');
    			if (postId > 0) {
    				var newPost = $('#post'+postId+'container');
    				newPost.addClass('new_quick_discussion');
    				scrollToPost(newPost);
    			}
    		})
    	});
    };

    /**
     * Update discussion replies count. Called after adding or removeing posts.
     * @params {Node} discussion The discussion node
     */
    var setRepliesCount = function(discussion) {
    	var replies = discussion.find('.ouilforum_post').length-1, replyStr = '', replyNum = '';
    	if (replies > 0) {
    		replyStr = replies == 1 ? PARAMS.str.discussionReply : PARAMS.str.discussionReplies.replace('#', replies);
    		replyNum = '('+replies+')';
    	}
    	discussion.find('.discussion_subject .for-sr').html(replyStr);
    	discussion.find('.discussion_subject .numbers').html(replyNum);
    };
    
    /**
     * Count replies in a post or discussion.
     * @param {Node} postNode The parent post.
     */
    var countChildren = function(postNode) {
    	var posts;
    	if (postNode.hasClass('firstpost')) {
    		posts = getDiscussion(postNode).find('.ouilforum_post').length-1;
    	} else {
    		posts = postNode.closest('.indent').find('.ouilforum_post').length-1;
    	}
   		return posts;
    };
    
    /**
     * Sets an AJAX call to delete posts.
     */
    var deletePosts = function() {
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_delete_post',
    		args: {
                postid: PARAMS.delNode.postId
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		$('#del_dialog').next().find('button').prop('disabled', false);
    		PARAMS.delDialog.dialog('close');
        	var discussion;
        	if (!PARAMS.delNode.isDiscussion) {
        		discussion = getDiscussion(PARAMS.delNode.node);
        	}
        	if (callResult.button) {
        		$('.forummode_container').prepend(callResult.button);
        		$('ul.discussionslist').before(callResult.dialog);
        		$('.forummode_container div.alert-danger').remove();
        	}
    		PARAMS.delNode.node.animate({height: 0}, 500).delay(50).queue(function() {
    			PARAMS.delNode.node.remove();
    			$(this).dequeue();
    			var hasLastPost = PARAMS.delNode.node.find('.lastpost').length > 0;
    			if (!PARAMS.delNode.isDiscussion) {
    				setRepliesCount(discussion);
    				setDiscussionButton(discussion);
    				if (hasLastPost) {
    					discussion.find('.ouilforum_post').last().addClass('lastpost');
    				}
    			} else {
    				PARAMS.discussions--;
    				if (PARAMS.discussions == 0 && PARAMS.singlePage) {
    					$('.forumnodiscuss').removeClass('hidden_element');
    				}
    			}
    			resetDelNode();
    		});
    	}).fail(function(callResult) {
    		$('#del_dialog').next().find('button').prop('disabled', false);
    		if ('errorcode' in callResult && callResult.errorcode == 'cannotdeletepost') {
    			PARAMS.delNode.node.find('.simpleactionmenu a[data-action="delete"]').closest('li').remove();
    			PARAMS.delDialog.parent().addClass('set_noconfirmdlg')
    		}
    		PARAMS.delDialog.html(callResult.message);
    	});
    };
    
    /**
     * Reset delete dialog values.
     */
    var resetDelNode = function() {
    	PARAMS.delNode.node = [];
    	PARAMS.delNode.isDiscussion = false;
    	PARAMS.delNode.postId = 0;
    };
    
    /**
     * Called when the subscribe discussion button is pressed.
     */
    var subscribeDiscussion = function() {
    	var node = $(this);
    	if (acc.isWaiting(node)) {
    		return;
    	}
    	if (PARAMS.compactMode) {
    		if (!sb.hasOptions()) {
    			sb.setOptions(node.next(), node.closest('.stage_button_container').attr('id'), runSubscribeDiscussion);
    		}
    		sb.setDisplay();
    	} else {
    		acc.setWait(node, true);
    		runSubscribeDiscussion(node);
    	}
    };
    
    /**
     * Subscribe/unsubscribe to a discussion.
     * @param {Node} node Subscribe button.
     */
    var runSubscribeDiscussion = function(node) {
    	var discussion = node.attr('data-discussionid');
    	var action = node.attr('data-actiontype');
    	var ttt =  {
			discussion: discussion,
			subscribe: action
		};
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_subscribe_discussion',
    		args: {
    			'discussion': discussion,
    			'subscribe': action
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(node, false);
    		if (!callResult.error) {
	    		var newTitle, newLabel, newAction, newStatus;
	    		if (action == 'subscribe') {
	    			newLabel = newTitle = PARAMS.str.subscribeDiscussionYesLabel+', '+PARAMS.str.enabled;
	    			newAction = 'unsubscribe';
	    			newStatus = 'on';
	    		} else {
	    			newLabel = newTitle = PARAMS.str.subscribeDiscussionYesLabel+', '+PARAMS.str.disabled;
	    			newAction = 'subscribe';
	    			newStatus = 'off';
	    		}
	    		sb.setState(node.next(), newStatus == 'on');
	    		node.attr('data-actiontype', newAction).attr('data-buttonstate', newStatus).attr('title', newTitle);
	    		acc.informScreenReader(node, PARAMS.str.done, newLabel, 'aria-label');
    		}
    		// No errors, but couldn't subscribe. 
    		else {
    			var subNode = $('#subscribeforum');
    			// The forum can no linger be subscribed.
    			if (callResult.errortype == 'unsubscribable') {
    				subNode.remove();
    				$('.sub_discussion > div').remove();
    			}
    			// The user is already subscribed to the whole forum.
    			else if (callResult.errortype == 'subscribed') {
    				$('.sub_discussion > div').remove();
    				subNode.attr('data-actiontype', 'unsubscribe').
    					attr('data-buttonstate', 'on').attr('aria-label', PARAMS.str.subscribeForumNoLabel);
    				sb.setState(node.next(), true);
    				subNode.find('.button_text').html(PARAMS.str.subscribeForumNo);
    			}
    			if (callResult.errormessage) {
    				PARAMS.infoDialog.find('#info_dialog_content').html(callResult.errormessage);
    				PARAMS.infoDialog.dialog('open');
    			}
    		}
    	}).fail(function() {
    		acc.setWait(node, false);
    		setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    	});
    };
    
    /**
     * Sets an AJAX call for forum subscription.
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
     * Subscribe/unsubscribe to a forum.
     * @param {Node} node Subscribe button.
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
	    		var newLabel, newTitle, newAction, newStatus;
	    		if (action == 'subscribe') {
	    			newLabel = newTitle = PARAMS.str.subscribeForumYesLabel+', '+PARAMS.str.enabled;
	    			newAction = 'unsubscribe';
	    			newStatus = 'on';
	    			$('.sub_discussion > div').remove();
	    		} else {
	    			newLabel = newTitle = PARAMS.str.subscribeForumYesLabel+', '+PARAMS.str.disabled;
	    			newAction = 'subscribe';
	    			newStatus = 'off';
	    			$('.ouilforum_post .sub_discussion').each(function() {
	    				var newNode = PARAMS.discussionSub.clone();
	    				var discussionId = $(this).attr('data-discussionid');
	        			$(this).append(newNode);
	        			$(this).html($(this).html().replace(/\{\{f\}\}/g, PARAMS.forum).replace(/\{\{d\}\}/g, discussionId));
	        			sb.addDialog($(this).find('.stage_button_container'));
	        		});
	    		}
	    		sb.setState(node.next(), newStatus == 'on');
	    		sb.updateTitle(node.next(), PARAMS.str.subscribeForumYesLabel);
	    		node.attr('data-actiontype', newAction).attr('data-buttonstate', newStatus).attr('title', newTitle);
	    		if (PARAMS.digestBlockNode) {
	    			var isHidden = newStatus == 'off';
	    			PARAMS.digestBlockNode.setClass('hidden_element', isHidden);
	    			PARAMS.digestBlockNode.parent().find('.subscribe_digestmode').setClass('hidden_element', isHidden);
	    			if (isHidden) {
	    				resetDigestTree();
	    			}
	    		}
	    		if (PARAMS.subscribeBlockNode) {
	    			if (newStatus == 'on') {
	    				PARAMS.subscribeBlockNode.text.textContent = PARAMS.str.unsubscribe;
	    			} else {
	    				PARAMS.subscribeBlockNode.text.textContent = PARAMS.str.subscribe;
	    			}
	    			
	    		}
	    		acc.informScreenReader(node, PARAMS.str.done, newLabel, 'aria-label');
    		}
    	}).fail(function(callResult) {
    		acc.setWait(node, false);
    		setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    	});
    };

    /**
     * Reset digest node to default when cancelling subscription to forum.
     */
    var resetDigestTree = function() {
    	if (PARAMS.settingType == 'block') {
	    	var activeMode = PARAMS.digestBlockNode.find('.activesetting');
	    	if (activeMode.hasClass('digestmode_0')) {
	    		return; // Active mode is already in default, no need to manipulate the nodes.
	    	}
	    	// Set default node to active.
	    	var defaultMode = PARAMS.digestBlockNode.find('.digestmode_0');
	    	var innerContent = defaultMode.find('a').html();
	    	defaultMode.html('<span tabindex="-1">'+innerContent+'</span>').addClass('activesetting');
	    	// Reset current active node.
	    	var fromClass = activeMode[0].className.match(/digestmode_(\d)/i);
	    	if (fromClass !== null) {
	    		innerContent = activeMode.find('span').html();
	    		activeMode.html('<a href="'+M.cfg.wwwroot+'/mod/ouilforum/maildigest.php?id='+PARAMS.forum+
	    				'&amp;maildigest='+fromClass[1]+'&amp;sesskey='+M.cfg.sesskey+'" tabindex="-1">'+
	    				innerContent+'</a>').removeClass('activesetting');
	    	}
    	} else {
    		var parent = PARAMS.digestBlockNode.parent();
	    	var activeNode = parent.find('.subscribe_digestmode .activesetting');
	    	if (activeNode.hasClass('digestmode_0')) {
	    		return; // Active mode is already in default, no need to manipulate the nodes.
	    	}
	    	// Set default node to active.
	    	var defaultMode = parent.find('.digestmode_0').parent();
	    	var icon = defaultMode.find('i');
	    	icon[0].className = icon[0].className.replace('fa fa-fw', 'fa fa-check');
	    	var innerContent = defaultMode.find('a').html();
	    	defaultMode.html('<span class="currentlink '+PARAMS.digestMenuClass+
	    			' digestmode_0 activesetting" role="menuitem">'+innerContent+'</span>');
	    	// Reset current active node.
	    	var fromClass = activeNode[0].className.match(/digestmode_(\d)/i);
	    	if (fromClass !== null) {
	    		var parentNode = activeNode.parent();
		    	icon = activeNode.find('i');
		    	icon[0].className = icon[0].className.replace('fa fa-check', 'fa fa-fw');
	    		innerContent = activeNode.html();
	    		parentNode.html('<a href="'+M.cfg.wwwroot+'/mod/ouilforum/maildigest.php?id='+PARAMS.forum+
	    				'&amp;maildigest='+fromClass[1]+'&amp;sesskey='+M.cfg.sesskey+
	    				'" class="'+PARAMS.digestMenuClass+' digestmode_'+fromClass[1]+'" role="menuitem">'+
	    				innerContent+'</a>');
	    	}
    	}
    };

    /**
     * Called when the tracking button is pressed.
     */
    var trackForum = function() {
    	var node = $(this);
    	if (acc.isWaiting(node)) {
    		return;
    	}
    	if (PARAMS.compactMode) {
    		if (!sb.hasOptions()) {
    			sb.setOptions(node.next(), node.closest('.stage_button_container').attr('id'), runTrackForum);
    		}
    		sb.setDisplay();
    	} else {
    		acc.setWait(node, true);
    		runTrackForum(node);
    	}
    };

    /**
     * Sets an AJAX call for forum tracking.
     * @param {Node} node Tracking button.
     */
    var runTrackForum = function(node) {
    	acc.setWait(node, true);
    	var forum = node.attr('data-forumid');
    	var action = node.attr('data-actiontype');
    	var discussions = [], ids = 0;
    	if (action == 'track') {
    		$('#region-main .discussionslist .ouilforum_discussion').each(function() {
    			discussions.push($(this).attr('data-discussionid'));
    		});
    	}
    	if (discussions.length) {
    		ids = discussions.join();
    	}
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_track_forum',
    		args: {
                forumid: forum,
                track: action,
                inview: true,
                discussions: ids
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(node, false);
    		if (callResult.result != false) {
	    		var newLabel, newTitle, newAction, newStatus, unreadPosts = JSON.parse(callResult.unread);
	    		if (action == 'track') {
	    			newLabel = newTitle = PARAMS.str.trackForumYesLabel+', '+PARAMS.str.enabled;
	    			newAction = 'untrack';
	    			newStatus = 'on';
	    			setTrack(true, unreadPosts);
	    		} else {
	    			newLabel = newTitle = PARAMS.str.trackForumYesLabel+', '+PARAMS.str.disabled;
	    			newAction = 'track';
	    			newStatus = 'off';
	    			setTrack(false, unreadPosts);
	    		}
	    		sb.setState(node.next(), newStatus == 'on');
	    		node.attr('data-actiontype', newAction).attr('data-buttonstate', newStatus).attr('title', newTitle);
	    		acc.informScreenReader(node, PARAMS.str.done, newLabel, 'aria-label');
	    	}
    	}).fail(function(callResult) {
    		acc.setWait(node, false);
    		setFailNode(node, true, 'aria-label', node.attr('aria-label'));
    	});
    };

    /**
     * Set/remove tracking indicators in a forum.
     * @param {Bool} add Add or remove.
     * @param {Object} newPosts A list of unread posts ids.
     */
    var setTrack = function(add, newPosts) {
    	if (add) {
    		if (!$.isEmptyObject(newPosts)) {
    			var discussion, post;
    			$.each(newPosts, function(discussionId, posts) {
    				var discussion = $('#discussion'+discussionId+' .ouilforum_post.firstpost .of_post_title')
    				if (discussion.length == 0) {
    					return;
    				}
    				discussion.prepend(PARAMS.newIcons.discussion);
    				$.each(posts, function(pIndex, pId) {
    					post = $('#post'+pId+'container');
    					if (post.length == 0) {
    						return;
    					}
    					if (post.hasClass('firstpost')) {
    						post.find('div.author').after(PARAMS.newIcons.firstpost.replace('{postid}', pId));
    					} else {
    						post.find('.of_post_title').prepend(PARAMS.newIcons.post.replace('{postid}', pId));
    					}
    				});
    			})
    		}
    	} else {
    		var region = $('#region-main ul.discussionslist');
    		if (region.length > 0) {
    			region.find('.of_new_post').remove();
    			region.find('.firstpost_new').remove();
    		}
    	}
    };

    /**
     * Sets the discussion lock state.
     * @param {Node} disscussion Discussion node.
     * @param {Bool} lock Lock state.
     * @param {Node|String} newButton New replay button.
     * @param {Node} lockButton The discussion's lock button, if exists.
     * @param {Node|String} lockIcon an icon placeholder if the discussion doesn't have a lock button 
     */
    var lockDiscussion = function(discussion, lock, newButton, lockButton, lockIcon) {
    	var reply = discussion.find('.post_reply_button');
    	if (reply.length > 0) {
    		reply.replaceWith(newButton);
    	}
    	if (typeof lockButton == 'undefined' || lockButton.length == 0) {
	     		discussion.find('.of_post_icons').prepend(lockIcon);
    	} else {
    		if (lock) {
	    		lockButton.removeClass('ouilforum_lock').
				addClass('ouilforum_unlock').
				attr('data-action', 'unlock').
				attr('title', PARAMS.str.unlockDiscussion);
	    	} else {
	    		lockButton.removeClass('ouilforum_unlock').
				addClass('ouilforum_lock').
				attr('data-action', 'lock').
				attr('title', PARAMS.str.lockDiscussion);
	    		if (PARAMS.currentDialog == 'post') {
	    			setReplyButtons(true);
	    			PARAMS.quickReply.dialog.find('.quick_dialog_alert').html('');
	    		}
	    	}
    	}
    };
    
    /**
     * Validate closing a dialog with content.
     * @param {Node} node Dialog node.
     */
    var validateEmptyDialog = function(node) {
    	if (isDialogWaiting(node)) {
    		PARAMS.pendingAction = null;
    		return false;
    	}
    	if (isEmptyDialog(node)) {
    		return true;
    	} else {
    		PARAMS.closeDialog.dialog('open');
    		return false;
    	}
    };
    
    /**
     * check if the dialog is waiting for an AJAX response.
     * @param {Node} node Dialog node.
     */
    var isDialogWaiting = function(node) {
    	var activeMask = node.find('.waitmask');
    	if (activeMask.length == 0) {
    		return false;
    	}
    	return activeMask.hasClass('active');
    };
    
    /**
     * Check if available inputs in a dialog are empty.
     * @param {Node} node Dialog element
     */
    var isEmptyDialog = function(node) {
		var isEmpty = true;
		node.find('[data-required="true"]').each(function() {
			isEmpty = isEmpty && $.trim($(this).val()).length == 0;
		});
		return isEmpty;
    };

    /**
     * Called when a user wants to delete posts or discussions.
     * @param {Node} postNode The post or discussion to delete.
     */
    var callDeletePost = function(postNode) {
    	PARAMS.delNode.isDiscussion = postNode.hasClass('firstpost');
    	PARAMS.delNode.postId = postNode.attr('data-postid');
    	PARAMS.delNode.node = PARAMS.delNode.isDiscussion ? postNode.closest('li') : postNode.closest('.indent');
    	PARAMS.delNode.node.setClass('delete_state', true);
    	var children = countChildren(postNode);
    	var message = children == 0 ? PARAMS.str.delSure : PARAMS.str.delSurePlural.replace('#', children);
    	PARAMS.delDialog.html(message);
    	PARAMS.delDialog.dialog('open');
    };

    /**
     * Called when a user selects the post link option.
     * @param {Node} node The post link option element.
     */
    var callLinkPost = function(node) {
    	PARAMS.linkDialog.find('input').val(node.attr('href'));
    	PARAMS.linkDialog.dialog('open');
    	if (!PARAMS.canCopy) {
			PARAMS.linkDialog.next().find('button:first').prop('disabled', true);
			PARAMS.linkDialog.find('#link_copy_result').html(PARAMS.str.copyLinkNotSupported);
		} else {
			PARAMS.linkDialog.next().find('button:first').prop('disabled', false).focus();
		}
    };
    
    /**
     * Sets lock state for a discussion.
     * @param {Event} e 
     */
    var callLockDiscussion = function(e) {
    	e.preventDefault();
    	var node = $(this);
    	if (acc.isWaiting(node)) {
    		return;
    	}
    	var action = node.attr('data-action'), discussionId = node.attr('data-discussionid'), 
    	discussion = $('#discussion'+discussionId);
    	if (action == 'lock' && PARAMS.currentDialog != null) {
    		if (PARAMS.currentDialog == 'post' && PARAMS.quickReply.discussion == discussionId) {
    			PARAMS.pendingAction = node;
    			$('#quicknewpost button[data-action="cancel"]').click();
    			return;
    		}
    	}
    	PARAMS.pendingAction = null;
    	acc.setWait(node, true);
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_lock_discussion',
    		args: {
                forumid: PARAMS.forum,
                discussionid: discussionId,
                lock: action
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		acc.setWait(node, false);
    		if (callResult != false) {
	    		var newText, newLabel, newAction, newStatus;
	    		if (action == 'lock') {
	    			newLabel = PARAMS.str.unlockDiscussion;
	    		} else {
	    			newLabel = PARAMS.str.lockDiscussion;
	    		}
	    		lockDiscussion(discussion, action == 'lock', callResult, node);
	    		acc.informScreenReader(node, PARAMS.str.done, newLabel, 'aria-label');
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
     * Init delete dialog.
     */
    var initDelDialog = function() {
		PARAMS.delDialog = $('<div id="del_dialog"></div>');
	    PARAMS.delDialog.dialog({
	    	dialogClass: 'no-close',
	        resizable: false,
	        height: "auto",
	        width: 340,
	        autoOpen: false,
	        modal: true,
	        draggable: false,
	        title: PARAMS.str.del,
	        create: function(e, ui) {
	        	$(this).parent().addClass('of_clean_dialog').attr('id', 'ui_del_dialog');
	        	$(this).parent().find('button')[1].className += ' confirmbtndlg';
	        },
	        close: function(e, ui) {
	        	$(this).parent().removeClass('set_noconfirmdlg');
	        },
	        buttons: [
	            {
	            	text: PARAMS.str.del,
	            	click: function() {
	            		$('#del_dialog').next().find('button').prop('disabled', true);
	            		deletePosts();
	            	}
	            },
	            {
	            	text: PARAMS.str.cancel,
	            	click: function() {
	            		PARAMS.delNode.node.setClass('delete_state', false);
	            		resetDelNode();
	            		$(this).dialog("close");
	            	}
	            }
	        ],
	        beforeClose: function(e) {
	        	if (typeof e.keyCode != 'undefined' && e.keyCode == 27) {
            		PARAMS.delNode.node.setClass('delete_state', false);
            		resetDelNode();
	        	}
	        }
	    });
    };

    /**
     * Init close confirmation dialog.
     */
    var initCloseDialog = function() {
		PARAMS.closeDialog = $('<div id="close_dialog"></div>');
	    PARAMS.closeDialog.dialog({
	    	dialogClass: 'no-close',
	        resizable: false,
	        height: "auto",
	        width: 340,
	        autoOpen: false,
	        modal: true,
	        draggable: false,
	        title: PARAMS.str.confirmDiscardContent,
	        create: function(e, ui) {
	        	$(this).parent().addClass('of_clean_dialog').attr('id', 'ui_close_dialog');
	        },
	        open: function(e, ui) {
	        	var title;
	        	if (PARAMS.currentDialog == 'post' && PARAMS.pendingAction && PARAMS.pendingAction.attr('data-actiontype') == 'lock') {
	        		title = PARAMS.str.confirmDiscardContentLock;
	        	} else {
	        		title = PARAMS.str.confirmDiscardContent;
	        	}
	        	PARAMS.closeDialog.dialog('option', 'title', title);
	        	$('body').addClass('noscroll');
	        },
	        buttons: [
	            {
	            	text: PARAMS.str.confirm,
	            	click: function() {
	            		$('body').removeClass('noscroll');
	            		$(this).dialog("close");
	            		if (PARAMS.currentDialog == 'post') {
	            			closeQuickReply(false);
	            		} else if (PARAMS.currentDialog == 'discussion') {
	            			closeDiscussionDialog(false);
	            		} else if (PARAMS.currentDialog == 'forward') {
	            			closeForward();
	            		}
	            	}
	            },
	            {
	            	text: PARAMS.str.cancel,
	            	click: function() {
	            		PARAMS.pendingAction = null;
	            		$('body').removeClass('noscroll');
	            		$(this).dialog("close");
	            	}
	            }
	        ]
	    });
    };

    /**
     * Init post link dialog.
     */
    var initLinkDialog = function() {
		PARAMS.linkDialog = $('<div id="link_dialog"></div>');
		PARAMS.linkDialog.html('<span id="link_path_label" class="for-sr">'+PARAMS.str.linkToPostField+
				'</span><input id="post_link_path" aria-labelledby="link_path_label">'+
				'<div id="link_copy_result"></div>');
		PARAMS.linkDialog.find('input').prop('readonly', true);
	    PARAMS.linkDialog.dialog({
	        resizable: false,
	        closeText: '',
	        height: "auto",
	        width: 340,
	        autoOpen: false,
	        modal: true,
	        draggable: false,
	        title: PARAMS.str.copyLinkTitle,
	        close: function() {
	        	PARAMS.linkDialog.find('input').val('');
	        },
	        create: function(e, ui) {
	        	$(this).parent().addClass('of_clean_dialog').attr('id', 'ui_link_dialog');
	        	var closeButton = $(this).parent().find('.ui-dialog-titlebar-close');
	        	closeButton.addClass('float_start fa fa-close').attr('title', PARAMS.str.copyLinkClose);
	        	closeButton.find('span').attr('aria-hidden', true);
	        },
	        buttons: [
	            {
	            	text: PARAMS.str.copyLinkCopy,
	            	click: function() {
	            		var button = $(this).parent().find('.ui-dialog-buttonset button:first');
	            		PARAMS.linkDialog.find('input').select();
            			if (document.execCommand('copy')) {
            				PARAMS.toast.toast('show', PARAMS.str.copyLinkCopied);
            				acc.informScreenReader(button, PARAMS.str.done, '', 'aria-label');
            			} else {
            				PARAMS.toast.toast('show', PARAMS.str.copyLinkFailed);
            				acc.informScreenReader(button, PARAMS.str.fail, '', 'aria-label');
            			}
            			PARAMS.linkDialog.find('input').blur();
            			button.focus();
	            	}
	            },
	            {
	            	text: PARAMS.str.copyLinkGoTo,
	            	click: function() {
	            		var newPage = PARAMS.linkDialog.find('input').val();
	            		$(this).dialog("close");
	            		window.location.href = newPage;
	            	}
	            }
	        ]
	    });
	    PARAMS.linkDialog.find('input').click(function() {
	    	$(this).select();
	    });
    };

    /**
     * Init info dialog.
     */
    var initInfoDialog = function() {
		PARAMS.infoDialog = $('<div id="info_dialog"><div id="info_dialog_content"></div></div>');
	    PARAMS.infoDialog.dialog({
	    	dialogClass: 'no-close',
	        resizable: false,
	        height: "auto",
	        minWidth: 200,
	        maxWidth: 600,
	        autoOpen: false,
	        modal: true,
	        draggable: false,
	        create: function(e, ui) {
	        	$(this).parent().addClass('of_clean_dialog').attr('id', 'ui_info_dialog');
	        },
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
     * Init forward dialog.
     */
    var initForwardDialog = function() {
		PARAMS.forwardDialog.dialog = $('<div id="forward_dialog"></div>');
		PARAMS.forwardDialog.dialog.html($('#quickforward').remove().removeClass('hidden_element'));
		PARAMS.forwardDialog.path = PARAMS.forwardDialog.dialog.find('form').attr('action'); 
	    PARAMS.forwardDialog.dialog.dialog({
	    	dialogClass: 'no-close',
	        resizable: false,
	        height: "auto",
	        width: 340,
	        autoOpen: false,
	        modal: true,
	        draggable: false,
	        title: PARAMS.str.forwardTitle,
	        create: function(e, ui) {
	        	$(this).parent().addClass('of_clean_dialog').attr('id', 'ui_forward_dialog');
	        },
	        beforeClose: function(e, ui) {
	        	if (e.originalEvent && !validateEmptyDialog($('#quickforward'))) {
	        		return false;
	        	}
	        },
	        close: function(e, ui) {
	        	PARAMS.forwardDialog.parent = 0;
	        	resetDialogInput(PARAMS.forwardDialog.dialog, true);
	        	PARAMS.currentDialog = null;
	        }
	    });
	    $('#cancelforward').click(function(e){
			e.preventDefault();
			if (validateEmptyDialog($('#quickforward'))) {
				closeForward();
			}
	    });
	    $('#sendforward').click(function(e){
			e.preventDefault();
			sendForward();
	    });
	    $('#advancedforward').click(function(e){
	    	var email = PARAMS.forwardDialog.dialog.find('#forwardemail');
	    	if (!email[0].checkValidity()) {
	    		email.addClass('empty_field_alert');
		    	e.preventDefault();
	    	}
	    });
    };

    /**
     * Open forward dialog.
     * @param {String} path The form action path.
     * @param {Node} post The post node.
     */
    var callForward = function(path, post) {
    	PARAMS.currentDialog = 'forward';
    	PARAMS.forwardDialog.path = path;
    	PARAMS.forwardDialog.parent = post.attr('data-postid');
    	PARAMS.forwardDialog.dialog.find('form').attr('action', PARAMS.forwardDialog.path);
    	PARAMS.forwardDialog.dialog.find('form #forwardsubject').val(post.find('.post_subject').html());
    	PARAMS.forwardDialog.dialog.dialog('open');
    };

    /**
     * Sets an AJAX call to forward the post.
     */
    var sendForward = function() {
    	PARAMS.forwardDialog.dialog.find('.empty_field_alert').removeClass('empty_field_alert');
    	PARAMS.forwardDialog.dialog.find('.dialog_field_alert_label').remove();
    	PARAMS.forwardDialog.dialog.find('.quick_dialog_alert').html('');
    	var email = PARAMS.forwardDialog.dialog.find('#forwardemail');
    	var subject = PARAMS.forwardDialog.dialog.find('#forwardsubject');
    	var message = PARAMS.forwardDialog.dialog.find('#forwardcontent');
    	var errors = false;
    	// Check required fields.
    	if ($.trim(message.val()).length == 0) {
    		errors = true;
    		message.addClass('empty_field_alert');
    		setDialogFieldAlert(message, PARAMS.str.forwardErrorEmpty, true, false);
    	}
    	if ($.trim(subject.val()).length == 0) {
    		errors = true;
    		subject.addClass('empty_field_alert');
    		setDialogFieldAlert(subject, PARAMS.str.forwardErrorEmpty, true, false);
    	}
    	var emptyEmail = $.trim(email.val()).length == 0;
    	if (emptyEmail || !email[0].checkValidity()) {
    		errors = true;
    		email.addClass('empty_field_alert');
    		var emailAlert = emptyEmail ? PARAMS.str.forwardErrorEmpty : PARAMS.str.forwardErrorInvalidEmail;
    		setDialogFieldAlert(email, emailAlert, true, false);
    	}
    	if (errors) {
    		PARAMS.forwardDialog.dialog.find('.dialog_field_alert_label:first').focus();
    		return;
    	}
    	
    	setMask('forwardmask', true, false);
		PARAMS.forwardDialog.dialog.find('button').prop('disabled', true);
		
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_quick_forward_post',
    		args: {
				postid : PARAMS.forwardDialog.parent,
				forumid: PARAMS.forum,
				email: email.val(),
				subject: subject.val(),
				ccme: PARAMS.forwardDialog.dialog.find('#ccme').is(':checked'),
				message: message.val()
    		}
    	}]);
    	promise[0].done(function(callResult) {
    		PARAMS.forwardDialog.dialog.find('button').prop('disabled', false);
    		setMask('forwardmask', false, false);

    		if (callResult.error) {
    			PARAMS.forwardDialog.dialog.find('.quick_dialog_alert').html(callResult.errormessage);
    			if (callResult.errortype == 'email') {
    				email.addClass('empty_field_alert');
    				setDialogFieldAlert(email, callResult.errormessage, true, true);
    			}
    		} else {
    			PARAMS.toast.toast('show', PARAMS.str.forwardSent);
    			closeForward();
    		}
     	}).fail(function(callResult) {
    		PARAMS.forwardDialog.dialog.find('button').prop('disabled', false);
    		setMask('forwardmask', false, false);
    		PARAMS.forwardDialog.dialog.find('.quick_dialog_alert').html(PARAMS.str.fail);
    		PARAMS.toast.toast('show', PARAMS.str.fail);
    	});
    };

    /**
     * Close forward dialog.
     */
    var closeForward = function() {
    	PARAMS.forwardDialog.dialog.dialog('close');
    };
    
    /**
     * Add or remove alert message.
     * @param {Node} node Anchor node
     * @param {String} alertText Alert content
     * @param {Bool} add Add or remove message
     * @param {Bool} focus Set focus to the element
     */
    var setDialogFieldAlert = function(node, alertText, add, focus) {
    	if (add) {
    		var alertLabel = $('<span tabindex="0" class="dialog_field_alert_label">'+alertText+'</span>');
    		alertLabel.insertBefore(node);
    		if (focus) {
    			alertLabel.focus();
    		}
    	} else {
    		node.prev('.dialog_field_alert_label').remove();
    	}
    };
    
    /**
     * Init new discussion dialog.
     */
    var initDiacussionDialog = function() {
    	$('#region-main').on('click', '#addquickdiscussion', function() {
			if ($(this).hasClass('wait_for_dialog')) {
				$('#quicknew'+PARAMS.currentDialog+' button[data-action="cancel"]').click();
				return;
			} else if (PARAMS.currentDialog != null) {
				PARAMS.pendingAction = $(this);
				$('#quicknew'+PARAMS.currentDialog+' button[data-action="cancel"]').click();
				return;
			} else {
    			$(this).addClass('wait_for_dialog');
				PARAMS.currentDialog = 'discussion';
    			$('#quickdiscussioncontainer').removeClass('hidden_element').delay(1).queue(function() {
					$(this).removeClass('closed_dialog').dequeue();
				}).delay(200).queue(function() {
					$(this).find('input[type="text"]').focus();
					$(this).dequeue();
				});
			}
		});
    	$('#region-main').on('click', '#cancelquickdiscussion', function(e) {
			e.preventDefault();
			if (validateEmptyDialog($('#quicknewdiscussion'))) {
				closeDiscussionDialog(false);
			}
		});
    	$('#region-main').on('click', '#sendquickdiscussion', function(e) {
			e.preventDefault();
			$('#newdiscussionfooter button').prop('disable', true);
			sendDiscussion();
		});
    };
    
    /**
     * Close quick discussion dialog.
     * @param {Bool} fast Closing method.
     */
    var closeDiscussionDialog = function(fast) {
    	var quickDialog = $('#quickdiscussioncontainer');
    	PARAMS.currentDialog = null;
    	$('#newdiscussionbody .empty_field_alert').removeClass('empty_field_alert');
    	if (fast || PARAMS.pendingAction != null) {
    		quickDialog.addClass('closed_dialog').addClass('hidden_element');
    		resetDialogInput(quickDialog, true);
    		$('#addquickdiscussion').removeClass('wait_for_dialog');
    	} else {
    		quickDialog.addClass('closed_dialog').delay(400).queue(function() {
    			resetDialogInput($(this), false);
    			$(this).addClass('hidden_element').dequeue();
    			setMask('quickdiscussionmask', false, true);
    			if (PARAMS.pendingAction == null) {
    				$('#addquickdiscussion').removeClass('wait_for_dialog').focus();
    			}
    		});
    	}
    	if (PARAMS.pendingAction != null) {
    		PARAMS.pendingAction.focus().click();
    		PARAMS.pendingAction = null;
    	}
    };
    
    /**
     * Send a new discussion.
     */
    var sendDiscussion = function() {
    	$('#newdiscussionbody .empty_field_alert').removeClass('empty_field_alert');
    	var subject = $('#newdiscussionbody input[name="quicksubject"]');
    	var subjectText = $.trim(subject.val());
    	var message = $('#newdiscussionbody textarea');
    	var messageText = $.trim(message.val());
    	var groupId = $('#quicknewdiscussion #quickdiscussiongroupid').val();
    	var errors = false;
    	if (subjectText.length == 0) {
    		errors = true;
    		subject.addClass('empty_field_alert');
    	}
    	if (errors) {
    		return;
    	}
    	var args = {
    		forumid: PARAMS.forum,
    		subject: subjectText,
    		message: messageText,
    		groupid: groupId,
    		ashtml: 1,
    		options: [
    		    {
    		    	name: 'discussionsubscribe',
    		    	value: 0
    		    }
    		]
    	};
    	var myGroups = $('#newdiscussionbody #posttomygroups');
    	if (myGroups.length > 0 && myGroups.is(':checked')) {
    		args.togroups = 1;
    	}
    	setMask('quickdiscussionmask', true, false);
    	bindResult('discussion');
    	var promise = ajax.call([{
    		methodname: 'mod_ouilforum_add_quick_discussion',
    		args: args
    	}]);
    	promise[0].done(function(callResult) {
    		closeDiscussionDialog(true);
    		var newDiscussion = $.parseHTML(callResult);
    		$(newDiscussion).addClass('new_quick_discussion');
    		var list = $('ul.discussionslist');
    		var pinned = list.find('li.discussionslist.pinned');
    		if (pinned.length == 0) {
    			list.prepend(newDiscussion);
    		} else {
    			$(pinned[pinned.length-1]).after(newDiscussion);
    		}
			$(newDiscussion).find('.simpleactionmenu').each(function() {
				$(this).ouilsimpleMenu();
			});
			if (!$(newDiscussion[0]).inView(true)) {
				$('html, body').animate({
					scrollTop: $(newDiscussion[0]).find('a.post_anchor').offset().top
				}, 800);
			}
			PARAMS.discussions+= newDiscussion.length;
			if (PARAMS.discussions == 1) {
				$('.forumnodiscuss').addClass('hidden_element');
			}
			
			// Send adding discussion to google analytics.
    		var discussionId; 
    		$(newDiscussion).find('div.ouilforum_discussion').each(function() {
    			fga.send('adddiscussion', $(this).attr('data-discussionid'));
    		});
    	    if (PARAMS.forumType == 'eachuser') {
    	    	$('#addquickdiscussion').remove();
    	    	$('#quickdiscussioncontainer').remove();
    	    }
    	    $()
    	}).fail(function(callResult) {
    		setMask('quickdiscussionmask', false, false);
    		$('#newdiscussionbody input[name="subject"]').addClass('empty_field_alert');
    		$('#newdiscussionbody textarea').addClass('empty_field_alert');
    	});
    };

    /**
     * Init reply dialog.
     */
    var initReplyDialog = function() {
		if ($('#quickreplycontainer').length == 1) {
			PARAMS.quickReply.dialog = $('#quickreplycontainer').remove();
			PARAMS.quickReply.path = PARAMS.quickReply.dialog.find('form').attr('action'); 
			PARAMS.quickReply.title = PARAMS.quickReply.dialog.find('.quickdialogtop span');
			PARAMS.quickReply.prefix = PARAMS.quickReply.title.html();
		}
		$('#region-main').on('click', 'button.of_reply', function(e) {
			e.stopPropagation();
			if ($(this).hasClass('wait_for_dialog')) {
				$('#quicknew'+PARAMS.currentDialog+' button[data-action="cancel"]').click();
				return;
			} else if (PARAMS.currentDialog != null) {
				PARAMS.pendingAction = $(this);
				$('#quicknew'+PARAMS.currentDialog+' button[data-action="cancel"]').click();
				return;
			} else {
				$(this).addClass('wait_for_dialog');
				openQuickReply($(this).closest('.ouilforum_post'));
			}
		});
		$('#region-main').on('click', '#cancelquickpost', function(e) {
			e.preventDefault();
			if (validateEmptyDialog($('#quicknewpost'))) {
				closeQuickReply(false);
			}
		});
		$('#region-main').on('click', '#sendquickpost', function(e) {
			e.preventDefault();
			sendQuickReply(false);
		});
		$('#region-main').on('click', '#quickedittitle', function(e) {
			e.preventDefault();
			$('#newposttop').addClass('editing');
			$('#quickreplysubject').focus();
		});
		$('#region-main').on('click', '#quickclosetitle', function(e) {
			e.preventDefault();
			$('#newposttop').removeClass('editing');
			$('#quickreplysubject').val('');
			$('#quickedittitle').focus();
		});
		$('#region-main').on('keydown', '#quickreplysubject', function(e) {
			if (e.keyCode == 13) {
				e.preventDefault();
				e.stopPropagation();
			}
		});
    };

    /**
     * Open the quick reply dialog.
     * @param {Node} post the post element to reply to
     */
    var openQuickReply = function(post) {
    	PARAMS.currentDialog = 'post';
    	PARAMS.quickReply.parent = getReplyParent(post, true);
    	PARAMS.quickReply.parentOrSibling = post.attr('data-postid');
    	PARAMS.quickReply.discussion = getDiscussion(post, true);
    	post.after(PARAMS.quickReply.dialog);
    	PARAMS.quickReply.dialog.find('form').attr('action', PARAMS.quickReply.path+PARAMS.quickReply.parent);
    	PARAMS.quickReply.dialog.find('form input[name="replyto"]').val(PARAMS.quickReply.parentOrSibling);
    	PARAMS.quickReply.title.html(PARAMS.quickReply.prefix+' '+post.find('span.subject .post_subject').html());
		$('#quickreplycontainer').removeClass('hidden_element').delay(1).queue(function() {
			$(this).removeClass('closed_dialog').dequeue();
		}).delay(200).queue(function() {
			$(this).find('textarea').focus();
			$(this).dequeue();
		});
    };

    /**
     * Reset the quick reply dialog.
     * @param {Node} node The dialog element
     */
    var resetQuickReply = function(node) {
    	node.find('.empty_field_alert').removeClass('empty_field_alert');
    	node.find('form input[name="replyto"]').val('');
    	node.find('#newposttop').removeClass('editing');
    	resetDialogInput(node, true);
		PARAMS.quickReply.parent = 0;
		PARAMS.quickReply.discussion = 0;
		PARAMS.quickReply.parentOrSibling = 0;
    };

    /**
     * Close the quick reply dialog.
     * @param {Bool} fast Closing method
     */
    var closeQuickReply = function(fast) {
    	var node = $('#quickreplycontainer');
    	if (node.length == 0) {
    		return;
    	}
    	PARAMS.currentDialog = null;
    	var button = node.prev('.ouilforum_post').find('.post_reply_button');
    	if (PARAMS.pendingAction && PARAMS.pendingAction[0] == button[0]) {
    		PARAMS.pendingAction = null; // Prevent reopening the dialog right after closing.
    	}
    	if (fast || PARAMS.pendingAction != null) {
    		node.addClass('closed_dialog').addClass('hidden_element');
    		resetQuickReply(node);
    		PARAMS.quickReply.dialog.find('.quick_dialog_alert').html('');
	    	node.remove();
	    	button.removeClass('wait_for_dialog');
    	} else {
			node.addClass('closed_dialog').delay(500).queue(function() {
				resetQuickReply(node);
				node.addClass('hidden_element').dequeue();
				setReplyButtons(true);
				PARAMS.quickReply.dialog.find('.quick_dialog_alert').html('');
		    	node.remove();
				button.removeClass('wait_for_dialog');
				if (PARAMS.pendingAction == null) {
					button.focus();
				}
			});
    	}
    	if (PARAMS.pendingAction != null) {
    		PARAMS.pendingAction.focus().click();
    		PARAMS.pendingAction = null;
    	}
    };

    /**
     * Init wrapper for select elements.
     */
    var initSelectWrapper = function() {
    	var hasWrapper = $('.selector_wrapper_container').length > 0;
    	if (!hasWrapper) {
    		return;
    	}
    	$('.selector_wrapper_container select').each(function() {
    		$(this).wrap('<div class="selector_wrapper"></div>');
    	});
		$('#region-main').on('focus', '.selector_wrapper select', function() {
			$(this).closest('.selector_wrapper').addClass('focused');
		});
		$('#region-main').on('blur', '.selector_wrapper select', function() {
			$(this).closest('.selector_wrapper').removeClass('focused');
		});
		// Background size and position behave differently in IE, so this is a workaround. 
		if (PARAMS.isIE) {
			var pos, isRTL = $('body').hasClass('dir-rtl');
			$('.selector_wrapper').each(function() {
				if (isRTL) {
					pos = parseInt((($(this).width())*.4)/2)*-1;
				} else {
					pos = parseInt(($(this).width())*.74);
				}
				$(this).css('background-size', '6em').css('background-position-x', pos);
			});
		}
    };
    
    /**
     * Set display compact mode.
     * @param {Bool} isCompact Is compact mode.
     */
    var setCompactMode = function(isCompact) {
    	if (PARAMS.compactMode === isCompact) {
    		return;
    	}
    	PARAMS.compactMode = isCompact;
    	if (!isCompact && sb.hasOptions()) {
    		sb.setDisplay(true);
    	}
    };

    /**
     * Sets a dialog mask.
     * @param {Int} id Mask id.
     * @param {Bool} show Show/hide.
     * @param {Bool} fast Display mode.
     */
    var setMask = function(id, show, fast) {
    	var node = $('#'+id);
    	if (node.length == 0) {
    		return;
    	}
    	if (show) {
    		if (fast) {
    			node.removeClass('hidden_element').addClass('active');
    		} else {
				node.removeClass('hidden_element').delay(1).queue(function() {
					$(this).addClass('active').dequeue();	
				});
    		}
    	} else {
    		if (fast) {
    			node.removeClass('active').addClass('hidden_element');
    		} else {
        		node.removeClass('active').delay(1).queue(function() {
        			$(this).addClass('hidden_element').dequeue();	
        		});
    		}
    	}
    };
    
    /**
     * Init elements on loading.
     */
    var initElements = function() {
    	var el = $('#d_sub_container > div');
    	if (el.length > 0) {
    		PARAMS.discussionSub = el.remove();
    		$('#d_sub_container').remove();
    	}
    	var icons = $('#for_js');
    	if (icons.length > 0) {
    		icons.find('.for_js').each(function() {
    			PARAMS.newIcons[$(this).attr('id')] = $(this).html();
    		});
    		icons.remove();
    	}
    	var blockMenu = $('aside.block-region .block_settings');
    	if (blockMenu.length) {
    		PARAMS.settingType = 'block';
    	} else {
    		blockMenu = $('#region-main-settings-menu div.menu');
    		if (blockMenu.length) {
    			PARAMS.settingType = 'menu';
    		}
    	}
    	var digestNode = blockMenu.find('.block_digest');
    	if (PARAMS.settingType == 'menu') {
        	if (digestNode.length) {
        		blockMenu.find('.subscribe_digestmode').each(function() {
        			if (!PARAMS.digestMenuClass) {
        				var menuClass = $(this)[0].className.match(/\s*(.-.-.)\s*/i);
        		    	if (menuClass !== null) {
        		    		PARAMS.digestMenuClass = menuClass[1];
        		    	}
        			}
        			$(this).removeClass('subscribe_digestmode').parent().addClass('subscribe_digestmode');
        		});
        	}
    	}
    	if (PARAMS.settingType) {
    		// Digest.
    		PARAMS.digestBlockNode = digestNode.parent();
    		if (digestNode.hasClass('hidden_element')) {
    			digestNode.removeClass('hidden_element');
    			PARAMS.digestBlockNode.addClass('hidden_element');
    			if (PARAMS.settingType == 'menu') {
    				PARAMS.digestBlockNode.parent().find('.subscribe_digestmode').addClass('hidden_element');    				
    			}
    		}
    		// Subscribe.
    		var subscribeNode;
    		if (PARAMS.settingType == 'menu') {
    			subscribeNode = blockMenu.find('.block_subscribe');
    		} else {
    			subscribeNode = blockMenu.find('.block_subscribe a');
    		}
        	if (subscribeNode.length) {
        		PARAMS.subscribeBlockNode = {
        				'node': subscribeNode,
        				'text': null
        		}
        		if (subscribeNode[0].childNodes.length == 1) {
        			PARAMS.subscribeBlockNode.text = subscribeNode[0].childNodes[0];
        		} else {
        			PARAMS.subscribeBlockNode.text = subscribeNode[0].childNodes[1];
        		}
        	}

    	}
    };
    
    /**
     * Check for browser support.
     */
    var findLimits = function() {
    	var tempElement = $('<input type="email">');
		PARAMS.useHTMLFallback = tempElement[0].type == 'text';
		PARAMS.canCopy = document.queryCommandSupported('copy');
    };
    
    /**
     * Find all posts with images and attach a post gallery button.
     * @params {String} node Optional filter for a single discussion.
     */
    var findPostImages = function(node) {
    	var path = '.ouilforum_post';
    	if (node) {
    		path = node+' .ouilforum_post';
    	}
    	$(path).each(function() {
    		var className = $(this).hasClass('closed_post') ? 'side' : '';
    		if (pgl.hasImages($(this).find('.post_content'))) {
    			var button = pgl.getLoader('postcontent'+$(this).attr('data-postid'), className);
    			$(this).find('.of_post_body').append(button);
    		}
    	});
    };

    /**
     * Init strings.
     */
    var initStrings = function() {
    	var strList = ['actionsuccess', 'actionfail', 'discussionreply', 'discussionreplies',
    	           'delete', 'cancel', 'deletesure', 'deletesureplural',
    	           'copylink:close', 'copylink:copied', 'copylink:copy', 'copylink:failed',
    	           'copylink:notsupported', 'copylink:title', 'recommendpost', 'unrecommendpost',
    	           'flag:level', 'flag:level', 'flag:menulevel', 'flag:menulevel0',
    	           'subscribeforum:no', 'subscribeforum:nolabel', 'subscribeforum:yes', 'subscribeforum:yeslabel',
    	           'tracking:no', 'tracking:nolabel', 'tracking:yes', 'tracking:yeslabel',
    	           'lockdiscussion', 'unlockdiscussion', 'confirm', 'confirmdiscardcontent',
    	           'forwardtitle', 'forwardsent', 'subscribediscussion:no', 'subscribediscussion:nolabel',
    	           'subscribediscussion:yes','subscribediscussion:yeslabel', 'opendiscussionthread', 'closediscussionthread',
    	           'copylink:gotolink', 'linktopostfield', 'forwarderror:empty', 'forwarderror:invalidemail',
    	           'confirmdiscardcontentlock', 'enabled', 'disabled', 'postingfailed', 'subscribe', 'unsubscribe'];
    	var strings = [];
    	$.each(strList, function(strId, strStr) {
    		strings.push({key: strStr, component: 'ouilforum'});
    	});
		s.get_strings(strings).done(function(s) {
			PARAMS.str.done = s[0];
			PARAMS.str.fail = s[1];
			PARAMS.str.discussionReply = s[2];
			PARAMS.str.discussionReplies = s[3].replace('{$a}', '#');
			PARAMS.str.del = s[4];
			PARAMS.str.cancel = s[5];
			PARAMS.str.delSure = s[6];
			PARAMS.str.delSurePlural = s[7].replace('{$a}', '#');
			PARAMS.str.copyLinkClose = s[8];
			PARAMS.str.copyLinkCopied = s[9];
			PARAMS.str.copyLinkCopy = s[10];
			PARAMS.str.copyLinkFailed = s[11];
			PARAMS.str.copyLinkNotSupported = s[12];
			PARAMS.str.copyLinkTitle = s[13];
			PARAMS.str.recommendPost = s[14];
			PARAMS.str.unrecommendPost = s[15];
			PARAMS.str.flag = s[16];
			PARAMS.str.flagNone = s[17];
			PARAMS.str.flagMenu = s[18];
			PARAMS.str.flagMenuNone = s[19];
			PARAMS.str.subscribeForumNo = s[20];
			PARAMS.str.subscribeForumNoLabel = s[21];
			PARAMS.str.subscribeForumYes = s[22];
			PARAMS.str.subscribeForumYesLabel = s[23];
			PARAMS.str.trackForumNo = s[24];
			PARAMS.str.trackForumNoLabel = s[25];
			PARAMS.str.trackForumYes = s[26];
			PARAMS.str.trackForumYesLabel = s[27];
			PARAMS.str.lockDiscussion = s[28];
			PARAMS.str.unlockDiscussion = s[29];
			PARAMS.str.confirm = s[30];
			PARAMS.str.confirmDiscardContent = s[31];
			PARAMS.str.forwardTitle = s[32];
			PARAMS.str.forwardSent = s[33];
			PARAMS.str.subscribeDiscussionNo = s[34];
			PARAMS.str.subscribeDiscussionNoLabel = s[35];
			PARAMS.str.subscribeDiscussionYes = s[36];
			PARAMS.str.subscribeDiscussionYesLabel = s[37];
			PARAMS.str.openDiscussionThread = s[38];
			PARAMS.str.closeDiscussionThread = s[39];
			PARAMS.str.copyLinkGoTo = s[40];
			PARAMS.str.linkToPostField = s[41];
			PARAMS.str.forwardErrorEmpty = s[42];
			PARAMS.str.forwardErrorInvalidEmail = s[43];
			PARAMS.str.confirmDiscardContentLock = s[44];
			PARAMS.str.enabled = s[45];
			PARAMS.str.disabled = s[46];
			PARAMS.str.postingFailed = s[47];
			PARAMS.str.subscribe = s[48];
			PARAMS.str.unsubscribe = s[49];
			initDelDialog();
			initLinkDialog();
			initCloseDialog();
			initForwardDialog();
			initInfoDialog();
			findPostImages();
        });
    };
    return {
		init: function(params) {
			PARAMS.forum = params.forum;
			PARAMS.forumType = params.forumtype;
			PARAMS.forumPage = params.page;
			PARAMS.singlePage = PARAMS.forumPage == 0 && $('.ouilforum_paging').length == 0;
			PARAMS.discussions = $('ul.discussionslist li.discussionslist').length;
			PARAMS.isIE = $('body').hasClass('ie');
			
			fga.init();
			fga.removeParams(['f','d','id','p']);
			initStrings();
			sb.init();
    		pgl.init($('body').hasClass('dir-rtl'));
    		$.fn.inView = function(fullView) {
    			var elementTop = $(this).offset().top;
    			var elementBottom = elementTop + $(this).outerHeight();
    			var viewTop = $(window).scrollTop();
    			var viewBottom = viewTop + $(window).height();
    			if (fullView) {
    				return elementBottom <= viewBottom && elementTop >= viewTop;
    			} else {
    				return elementBottom > viewTop && elementTop < viewBottom;
    			}
    		};
    		$.fn.setClass = function(className, state) {
    			if (state) {
    				$(this).addClass(className);
    			} else {
    				$(this).removeClass(className);
    			}
    		};

    		// Forum level actions.
    		$('#subscribeforum').click(subscribeForum);
    		$('#trackforum').click(trackForum);

    		// Posts collapse/display events.
    		$('#region-main').on('click', 'button.discussion_button', toggleDiscussion);
    		$('#region-main').on('click keyup', '.post_subject', togglePost);
    		$('#region-main').on('click', '.of_toggle_addon', function(e) {
    			e.stopPropagation();
    			$('#post'+$(this).attr('data-postid')).click();
    		});
    		// Post icons.
    		$('#region-main').on('click', '.simpleactionmenu a[data-actiontype="flag"]', flagPost);
    		$('#region-main').on('click', 'button.ouilforum_recommend', recommendPost);
    		$('#region-main').on('click', '.of_post_icons button[data-actiontype="lock"]', callLockDiscussion);
    		$('#region-main').on('click', '.of_post_icons button.pin_discussion', function() {
    			var pin = $(this).attr('data-action') == 'pin' ? 1 : -1;
    			window.location.href = 'post.php?pin='+pin+'&discussion='+$(this).attr('data-discussionid');
    		});
    
    		initDiacussionDialog();
    		initReplyDialog();
    		initElements();
    		
    		// Post menu actions.
    		$('#region-main').on('click', '.simpleactionmenu a[data-action="delete"]', function(e) {
    			e.preventDefault();
    			callDeletePost($(this).closest('.ouilforum_post'));
    		});
    		$('#region-main').on('click', '.simpleactionmenu a[data-action="link"]', function(e) {
    			e.preventDefault();
    			callLinkPost($(this));
    		});
    		$('#region-main').on('click', '.simpleactionmenu a[data-action="forward"]', function(e) {
    			e.preventDefault();
    			callForward($(this).attr('href'), $(this).closest('.ouilforum_post'));
    		});

    		$('#region-main').on('keyup', '.quicknewdialog', function(e) {
    			if (e.keyCode != 27) {
    				return;
    			}
    			$(this).find('button[data-action="cancel"]').click();
    		});
    		$('#region-main').on('click', '.subscribediscussion > button', subscribeDiscussion);
    		
    		PARAMS.toast = $('<div></div>').appendTo('body');
    		PARAMS.toast.toast();
    		initSelectWrapper();
    		findLimits();
    		setCompactMode(window.innerWidth < 768);
    		$(window).resize(function(e) {
    			setCompactMode(window.innerWidth < 768);
    		});
    		if (params.reply) {
    			var postReply = $('#post'+params.reply+'container button.of_reply');
    			if (postReply) {
    				postReply.click();
    			}
    		}
		}
	};
});