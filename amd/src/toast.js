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
 * This class manages the messages display on the screen.
 *
 * @module    mod_ouilforum/toast
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

	var defaults = {
		text: '',
		durationShow: 2000,
		durationWait: 2000,
		durationFade: 1000
	};
	var methods = {
		init: function(options) {
			this.options = $.extend(true, {}, defaults, options);
			this.css({
				'position': 'fixed',
				'left': '50%',
				'max-width': '400px',
				'min-width': '150px',
				'bottom': '100px',
				'text-align': 'center',
				'opacity': '0',
				'display': 'none',
				'border': '1px solid #1b1b1b',
				'background-color': 'rgba(27,27,27,.9)',
				'padding': '10px 15px',
				'color': '#fff',
				'font-size': '1.4em',
				'word-break': 'break-all',
				'border-radius': '40px',
				'box-shadow': '0px 0px 1px 1px rgba(27,27,27,.9)',
				'-webkit-box-shadow': '0px 0px 1px 1px rgba(27,27,27,.9)',
				'z-index': '200'
			});
			$(this).addClass('toast_container').html(this.options.text).attr('aria-live', 'assertive').attr('role', 'dialog');
		},
		show: function(newText) {
			if (typeof newText != 'undefined') {
				this.options.text = newText;
				$(this).html(this.options.text);
			}
			setCenter($(this));
			var isAnimated = $(this).is(':animated');
			if (!isAnimated) {
				$(this).
					clearQueue().
					stop().
					show().
					animate({
						opacity: 1
					}, this.options.durationShow).
					animate({opacity: 1}, this.options.durationWait).
					animate({
						opacity: 0
					}, {
						duration: this.options.durationFade,
						complete: function() {
							$(this).hide();
						}});
			} else {
				$(this).
				clearQueue().
				stop().
				animate({
					opacity: 1
				}, this.options.durationShow).
				animate({opacity: 1}, this.options.durationWait).
				animate({
					opacity: 0
				}, {
					duration: this.options.durationFade,
					complete: function() {
						$(this).hide();
					}});
			}
		},
		hide: function() {
			$(this).
			clearQueue().
			stop().
			animate({
				opacity: 0
			}, {
				duration: this.options.durationFade,
				complete: function() {
					$(this).hide();
				}});
		},
		text: function(content) {
			this.options.text = content;
			$(this).html(this.options.text);
		},
		duration: function(duration) {
			this.options.duration = duration;
		}
	};

	function setCenter(node) {
		node.css('marginLeft', (-parseInt(node.css('width'))/2)+'px');
	};
	$.fn.toast = function(methodOrOptions) {
		if (methods[methodOrOptions]) {
			return methods[methodOrOptions].apply(this, Array.prototype.slice.call(arguments, 1));
		} else if (typeof methodOrOptions === 'object' || !methodOrOptions) {
			// Default to "init".
			return methods.init.apply(this, arguments);
		} else {
			$.error('Method '+methodOrOptions+' does not exist on jQuery.tooltip');
		}
	};
});