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
 * This class manages stagebutton component.
 * This component can change its behavior from a standard button to a menu button. 
 *
 * @module    mod_ouilforum/stagebutton
 * @package   mod_ouilforum
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.1
 */
define(['jquery', 'core/str'], function($, s) {
    // Private variables and functions.
	var PARAMS = {
		str: {},
		dialog: null,
		stageDialog: {
			node: null,
			containerId: null,
			functionName: null
		}
	};

	/**
	 * Update dialog title.
	 * @param {Node} node Dialog node
	 * @param {String} newtitle new title string
	 */
	var updateTitle = function(node, newTitle) {
		node.find('.stage_title').html(newTitle);
	};
	/**
	 * Sets the buttons stage according to the main button state.
	 * @param {Node} node Container node
	 * @param {Bool} status Button active status
	 */
    var setStageButtons = function(node, status) {
    	var buttonActivate = node.find('button[data-action="activate"]');
    	var buttonDeactivate = node.find('button[data-action="deactivate"]');
    	if (status) {
    		buttonActivate.attr('aria-pressed', true);
    		buttonDeactivate.removeAttr('aria-pressed');
    	} else {
    		buttonDeactivate.attr('aria-pressed', true);
    		buttonActivate.removeAttr('aria-pressed');
    	}
    };

    /**
     * Sets the dialog display mode.
     * @param {Node} node Dialog node
     * @param {Bool} forceClose Set to true when forcing the dialog to close
     */
    var setStageButtonDisplay = function(node, forceClose) {
		if (node.hasClass('hidden_element') && !forceClose) {
			node.removeClass('hidden_element').find('.stage_title').focus();
			$('html').on('click.stagedialog', function(e) {
				hideIfOutsideStageDialog($(e.target));
			});
			$('html').on('focus.stagedialog', 'body', function(e) {
				hideIfOutsideStageDialog($(e.target));
			});
		} else if (!node.hasClass('hidden_element') || forceClose) {
			node.addClass('hidden_element');
			if (!forceClose) {
				node.prev().focus();
			}
			$('html').off('.stagedialog');
			clearDialog();
		}
    };

    /**
     * Hide the dialog if the focus is outside the container.
     * @param {Node} el Active element
     */
    var hideIfOutsideStageDialog = function(el) {
		if (el.parents('#'+PARAMS.stageDialog.id).length == 0) {
			setStageButtonDisplay(PARAMS.stageDialog.node, true);
		}
    };

    /**
     * Build the dialog template element.
     */
    var initStageDialog = function() {
		PARAMS.dialog = $('<div class="stage_button_options hidden_element">'+
				'<span class="stage_title" tabindex="-1"></span>'+
				'<div><button data-action="activate">'+PARAMS.str.activate+'</button>'+
				'<button data-action="deactivate">'+PARAMS.str.deactivate+'</button></div></div>');
    	$('.stage_button_container').each(function() {
    		addDialog($(this));
    	});
    };

    /**
     * Add a dialog to container node.
     * @param {Node} node Container node
     */
    var addDialog = function(node) {
    	var buttonState = node.find('button').attr('data-buttonstate') == 'on';
    	var dialogLabel = node.find('.stage_label').remove();
    	var optionsDialog = PARAMS.dialog.clone();
    	optionsDialog.find('.stage_title').html(dialogLabel.html());
    	setStageButtons(optionsDialog, buttonState);
    	node.append(optionsDialog);
    };
    
    /**
     * Clear stage dialog object.
     */
    var clearDialog = function() {
		PARAMS.stageDialog.node = null;
		PARAMS.stageDialog.id = null;
		PARAMS.stageDialog.functionName = null;
    };
    
    /**
     * Init strings.
     */
    var initStrings = function() {
		s.get_strings([
            {
            	key:        'button:activate',
            	component:  'ouilforum'
            },
            {
            	key:        'button:deactivate',
            	component:  'ouilforum'
            }
		]).done(function(s) {
			PARAMS.str.activate = s[0];
			PARAMS.str.deactivate = s[1];
			initStageDialog();
        });
    };

    return {
		init: function() {
			initStrings();
    		$('#region-main').on('keyup', '.stage_button_options', function(e) {
    			if (e.keyCode != 27) {
    				return;
    			}
    			if (PARAMS.stageDialog.node) {
    				setStageButtonDisplay(PARAMS.stageDialog.node);
    			}
    		});
    		$('#region-main').on('click', '.stage_button_options button', function() {
    			if ($(this).attr('aria-pressed') == 'true' || !PARAMS.stageDialog.functionName) {
    				return;
    			}
    			PARAMS.stageDialog.functionName($(this).closest('.stage_button_options').prev());
    		});
		},
		/**
		 * Set options for the active dialog.
		 * @param {Node} node dialog container node.
		 * @param {String} id Container id
		 * @param {Function} functionName The function to run after user's action.
		 */
		setOptions: function(node, id, functionName) {
			PARAMS.stageDialog.node = node;
			PARAMS.stageDialog.id = id;
			PARAMS.stageDialog.functionName = functionName;
		},
		/**
		 * Is the dialog attached to a node.
		 */
		hasOptions: function() {
			return PARAMS.stageDialog.node != null;
		},
		/**
		 * Reset dialog.
		 */
		clearOptions: function() {
			clearDialog();
		},
		/**
		 * Toggle dialog display.
		 * @param {Bool} forceClose Force dialog to close.
		 */
		setDisplay: function(forceClose) {
			setStageButtonDisplay(PARAMS.stageDialog.node, forceClose);
		},
		/**
		 * Set the buttons status according to main buuton in the container.
		 * @param {Node} node Container node.
		 * @param {Bool} status The button status.
		 */
		setState: function(node, state) {
			setStageButtons(node, state);
		},
		/**
		 * Add a dialog to container node.
		 * @param {Node} node Container node.
		 */
		addDialog: function(node) {
			addDialog(node);
		},
		/**
		 * Update dialog title.
		 * @param {Node} node Dialog node
		 * @param {String} newtitle new title string
		 */
		updateTitle: function(node, newTitle) {
			updateTitle(node, newTitle);
		}
	};
});