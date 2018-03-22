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
 * This class manages the actions in the move form.
 *
 * @module    mod_ouilforum/move
 * @package   mod_ouilforum
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.1
 */
define(['jquery'], function($) {
    // Private variables and functions.
	var PARAMS = {
		forum : 0
	};
	
	/**
	 * Set fields availability.
	 * @param {Bool} discussions Show/hide discussions related fields.
	 * @param {Bool} forums Show/hide forums related fields.
	 * @param {Bool} title Show/hide new title related fields.
	 */
	var toggleFields = function(discussions, forums, title) {
		if (discussions) { $('#discussionslist').show(); }
		else { $('#discussionslist').hide(); }
		if (forums) { $('#forumslist').show(); }
		else { $('#forumslist').hide(); }
		if (title) { $('#newtitle').show().attr('disabled', null); }
		else { $('#newtitle').hide().attr('disabled', true); }
	};
	
	/**
	 * Toogle fields display according to selection option in the form.
	 * @param {Int} targetType
	 */
	var toggleMoveTarget = function(targetType) {
		if (targetType == 2) {
			toggleFields(false, true, true);
		} else if (targetType == 1) {
			toggleFields(true, false, false);
		} else if (targetType == 3) {
			toggleFields(false, false, true);
		} else { // Hide fields by default.
			toggleFields(false, false, false);
		}
	};

    /**
     * Open or close a post preview.
     * @param {Event} e
     */
    var togglePostPreview = function(e) {
    	e.preventDefault();
    	var postId = $(this).attr('data-postid'), 
    	    pContainer = $('#postmain'+postId), open;
    	pContainer.toggleClass('closed_post');
    	open = !pContainer.hasClass('closed_post');
    	$(this).attr('aria-expanded', open);
    	pContainer.attr('aria-hidden', !open);

    };

	return {
		init: function(params) {
			PARAMS.forum = params.forum;
			var rad = $('input[name="movetarget"]:checked');
			if (rad.length > 0) {
				toggleMoveTarget(rad[0].value);
			}
    		$('input[name="movetarget"]').change(function(e) {
    			toggleMoveTarget(e.target.value);
    		});
    		$('#discussionslist').delegate('.post_link', 'click', togglePostPreview);
		}
	};
});