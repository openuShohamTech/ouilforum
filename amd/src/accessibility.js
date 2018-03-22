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
 * This class manages the accessibility notifications for a screen reader.
 *
 * @module    mod_ouilforum/accessibility
 * @package   mod_ouilforum
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.1
 */
define(['jquery'], function($) {

    /**
     * Run a delayed upadate.
     * @param {Node} node Target element
     * @param {String} text
     * @param {String} attribute
     */
    var runDelayedUpdate = function(node, text, attribute) {
    	node.attr(attribute, text);
    	endDelayedUpdate(node);
    }

    /**
     * Clears a timeout update.
     * @param {Node} node Target element
     */
    var endDelayedUpdate = function(node) {
    	if (node.srNotice) {
    		window.clearTimeout(node.srNotice);
    		node.srNotice = null;
    	}
    };

    return {
        /**
         * Sets temporary mark to indicate a failed action response.
         * @param {Node} node target node
         */
    	markActionFail: function(node) {
    		node.removeClass('action_fail');
    		node.addClass('action_fail').delay(2050).queue(function() {
    			$(this).removeClass('action_fail').dequeue();	
    		});
        },
        /**
         * Sets an attribute for a screan reader to read.
         * @param {Node} node Target node
         * @param {String} message notice text
         * @param {String} postMessage text to replace the notice text
         * @param {String} attribute The attribute to update
         */
        informScreenReader: function(node, message, postMessage, attribute) {
        	node.attr(attribute, message);
        	node.srNotice = window.setTimeout(function() { runDelayedUpdate(node, postMessage, attribute); }, 2000);
        },
        /**
         * Check if an element is available for action.
         * @param {Node} node Target node
         */
        isWaiting: function(node) {
        	return node.hasClass('wait_for_response') || node.hasClass('action_fail');
        },    /**
         * Sets waiting status to an element.
         * @param {Node} node Target node
         * @param {Bool} status  Waiting status
         */
        setWait: function(node, status) {
        	if (status) {
        		node.addClass('wait_for_response');
        	} else {
        		node.removeClass('wait_for_response');
        	}
        }
    };
});