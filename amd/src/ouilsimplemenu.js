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
 * This class handles the ouilsimplemenu module.
 *
 * @module    mod_ouilforum/ouilsimplemenu
 * @package   mod_ouilforum
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.1
 */
!function(root, factory) {
	if (typeof define === 'function' && define.amd) {
		define(['jquery'], factory);
	} else {
		factory(root.jQuery);
	}
}(this, function($) {
	'use strict';

	var defaults = {};

	var OuilSimpleMenu = function(element, options) {
		this.params = {
			opened: false,
			trigger: null,
			UL: null,
			maxIndex: 0,
			closeOnClick: false,
			focusOnOpen: false
		};
		this.element = $(element);
		this.options = $.extend(true, {}, defaults, options);
		this.init(this);
	};

	OuilSimpleMenu.prototype = {
			
		init: function(self) {
			self.params.closeOnClick = self.element.attr('data-closeaction') == 'true';
			self.params.focusOnOpen = self.element.attr('data-focusopen') == 'true';
			self.params.trigger = self.element.find('button:first');
			self.params.UL = self.element.find('ul');
			self.params.maxIndex = self.params.UL.children().length-1;
			self.setItems();
			self.setEvents();
		},
		
		/**
		 * Set appropriate role attributes.
		 */
		setItems: function() {
			this.params.UL.find('a').attr('role', 'menuitem');
		},

		/**
		 * Handle events for the menu.
		 */
		setEvents: function() {
			var O = this;
			O.params.trigger.on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				O.toggleMenu();
			});
			O.element.keydown(function(e) {
				if (e.keyCode == 27) {
					O.params.opened = false;
					O.openCloseMenu(false);
					O.params.trigger.focus();
				} else if (e.keyCode == 38 || e.keyCode == 40) {
					e.preventDefault();
					e.stopPropagation();
					var step = e.keyCode == 38 ? -1 : 1;
					O.moveFocus(step);
				}
			});
			O.params.UL.find('a').on('click', function() {
				if (O.params.closeOnClick) {
					O.params.opened = false;
					O.openCloseMenu(false);
					O.params.trigger.focus();
				}
			});
		},

		/**
		 * Toggle menu state.
		 */
		toggleMenu: function() {
			this.params.opened = !this.params.opened;
			this.openCloseMenu(this.params.opened);
		},

		/**
		 * Set menu state.
		 * @param {bool} open New state
		 */
		openCloseMenu: function(open) {
			var O = this;
			O.params.trigger.attr('aria-expanded', open);
			O.params.UL.attr('aria-hidden', !open);
			if (open) {
				O.params.UL.addClass('show-menu');
				if (O.params.focusOnOpen) {
					O.params.UL.find('a:first').focus();
				}
				$('html').on('click.simplemenu', function(e) { O.hideIfOutside($(e.target)); });
				$('html').on('focus.simplemenu', 'body', function(e) { O.hideIfOutside($(e.target)); });
			} else {
				O.params.UL.removeClass('show-menu');
				$('html').off('.simplemenu');
			}
		},

		/**
		 * Close menu when exiting.
		 * @param {Node} el Currently active element
		 */
		hideIfOutside: function(el) {
			if (el.parents('#'+this.element[0].id).length == 0) {
				this.params.opened = false;
				this.openCloseMenu(false);
			}
		},

		/**
		 * Set focus to an element in the menu.
		 * @param {int) index
		 */
		setFocus: function(index) {
			this.params.UL.find('li:eq('+index+') a').focus();
		},
		
		/**
		 * Move focus between elements in the menu.
		 * @param {int} step Move direction
		 */
		moveFocus: function(step) {
			// No need to iterate when there's only one menu item.
			if (this.params.maxIndex == 0) {
				this.setFocus(0);
				return;
			}
			var pos = this.params.UL.find(':focus').parent().index(), newPos;
			if (pos == -1) {
				newPos = step == -1 ? this.params.maxIndex : 0;
			} else {
				newPos = pos+step;
				if (newPos < 0) {
					newPos = this.params.maxIndex;
				} else if (newPos > this.params.maxIndex) {
					newPos = 0;
				}
			}
			this.setFocus(newPos);
		},
	}

	$.fn.ouilsimpleMenu = function(options) {

		return this.each(function() {
			var $this = $(this);
			$this.data('ouilsimpleMenu', new OuilSimpleMenu($this, options));
		});
	};
});