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
 * This class manages the calls for google analytics.
 * Working through this class will assure there will be no errors if GA isn't initialized for thie site.
 *
 * @module    mod_ouilforum/ga
 * @package   mod_ouilforum
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.1
 */
define(['jquery'], function($) {

	var forumGA = {
		available: false,
		pageParams: '',
		pageParamsArray: {},
		path: {
			addpost: '/vp/ouilforum/addpost/postid=',
			adddiscussion: '/vp/ouilforum/adddiscussion/discussionid='
		}
	}

	/**
	 * Check if google analytics is available and extract parameters from its path.
	 */
	var initGA = function() {
		forumGA.available = typeof ga === 'function';
		if (!forumGA.available) {
			return;
		}
		ga(function(tracker) {
			var pageParams = tracker.get('page');
			var paramsPos = pageParams.indexOf('?');
			if (paramsPos == -1) {
				paramsPos = pageParams.indexOf('&');
			}
			if (paramsPos > -1) {
				forumGA.pageParams = pageParams.substr(paramsPos+1);
				if (forumGA.pageParams.length > 0) {
					var values = forumGA.pageParams.split('&');
					$.each(values, function(vId, vParam) {
						var keyValue = vParam.split('=');
						forumGA.pageParamsArray[keyValue[0]] = keyValue[1];
					});
					forumGA.pageParams = '&'+forumGA.pageParams;
				}
			}
		});
	};

	/**
	 * Send a pageview call.
	 * @param {String} pathType Path name
	 * @param {Int} id Item id
	 */
	var sendGA = function(pathType, id) {
		if (!forumGA.available) {
			return;
		}
		if (typeof forumGA.path[pathType] === 'undefined') {
			return;
		}
	    ga('send', 'pageview', forumGA.path[pathType] + id + forumGA.pageParams);
	};

	/**
	 * Add or edit parameters in the path url.
	 * @param {Object} Parameters in a key-value structure.
	 */
	var setParamsGA = function(params) {
		if (!forumGA.available) {
			return;
		}
		$.each(params, function(paramKey, paramValue) {
			var pVal = trim(paramValue);
			if (pVal.length > 0) { // Must have a value.
				forumGA.pageParamsArray[paramKey] = paramValue;
			}
		});
		buildPath();
	};
	
	/**
	 * Remove parameters from the path url.
	 * @param {Array} Parameters names
	 */
	var removeParamsGA = function(params) {
		if (!forumGA.available) {
			return;
		}
		$.each(params, function(paramKey, paramValue) {
			delete forumGA.pageParamsArray[paramValue];
		});
		buildPath();
	};

	/**
	 * Build the path url.
	 */
	var buildPath = function() {
		forumGA.pageParams = '&'+$.param(forumGA.pageParamsArray);
		if (forumGA.pageParams.length == 1) {
			forumGA.pageParams = '';
		}
	};
	
	return {
		init: function() {
			initGA();
		},
		hasGA: function() {
			return forumGA.available;
		},
		setParams: function(params) {
			setParamsGA(params);
		},
		removeParams: function(params) {
			removeParamsGA(params);
		},
		send: function(pathType, id) {
			sendGA(pathType, id);
		}
	};
});