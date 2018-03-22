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
 * This class manages forum post gallery module.
 * This is a very simple lightbox gallery, specially made for images in a post.
 * The gallery also detects formulas (MathJax and Wiris).
 *
 * @module    mod_ouilforum/postgallery
 * @package   mod_ouilforum
 * @copyright 2018 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.1
 */
define(['jquery', 'core/str', 'jqueryui'], function($, s) {
	var PARAMS = {
		isRTL: null,
		loader: null,
		gallery: {
			dialog: null,
			imageHolder: null,
			spanHolder: null,
			contentContainer: null,
			buttonPrev: null,
			buttonNext: null,
			buttonClose: null,
			navCounter: null
		},
		str: {},
		urls: [],
		images: 0,
		position: 0,
		keyNext: null,
		keyPrev: null,
		isFirst: false,
		isLast: false
	};

	/**
	 * Init the gallery parameters.
	 * @params {Bool} isRTL Check the page direction and sets the navigation keys accordingly.
	 */
    var initGallery = function(isRTL) {
    	PARAMS.isRTL = isRTL;
		initStrings();
		if (isRTL) {
			PARAMS.keyPrev = 39;
			PARAMS.keyNext = 37;
		} else {
			PARAMS.keyPrev = 37;
			PARAMS.keyNext = 39;
		}
		initEvents();
    };

    /**
     * Init the dialog node.
     */
    var initDialog = function() {
    	var startFont, endFont;
    	if (PARAMS.isRTL) {
    		startFont = 'fa-chevron-right';
    		endFont = 'fa-chevron-left';
    	} else {
    		startFont = 'fa-chevron-left';
    		endFont = 'fa-chevron-right';
    	}
		PARAMS.gallery.dialog = $('<div class="pgl_main" tabindex="-1" role="dialog"><div class="pgl_body">'+
				'<div class="pgl_image"><div class="pgl_image_container"><img src="" class="pgl_display hidden_element"><span class="pgl_display hidden_element"></span>'+
				'<button class="pgl_close fa fa-close" title="'+PARAMS.str.buttonClose+'"></button>'+
				'<div class="pgl_image_footer"><span class="pgl_counter"></span></div></div></div>'+
				'<button class="pgl_nav pgl_button_start fa '+startFont+'" title="'+PARAMS.str.buttonPrev+'"></button>'+
				'<button class="pgl_nav pgl_button_end fa '+endFont+' title="'+PARAMS.str.buttonNext+'"></button></div></div>');
		PARAMS.gallery.contentContainer = PARAMS.gallery.dialog.find('.pgl_image_container');
		PARAMS.gallery.imageHolder = PARAMS.gallery.dialog.find('img');
		PARAMS.gallery.spanHolder = PARAMS.gallery.dialog.find('span.hidden_element');
		PARAMS.gallery.buttonPrev = PARAMS.gallery.dialog.find('.pgl_button_start');
		PARAMS.gallery.buttonNext = PARAMS.gallery.dialog.find('.pgl_button_end');
		PARAMS.gallery.buttonClose = PARAMS.gallery.dialog.find('.pgl_close');
		PARAMS.gallery.navCounter = PARAMS.gallery.dialog.find('.pgl_counter');
    };
    
    /**
     * Init strings.
     */
    var initStrings = function() {
		s.get_strings([
		    {
   		    	key:       'pgl:loadbutton',
                component: 'ouilforum'
            },
            {
                key:       'pgl:buttonclose',
                component: 'ouilforum'
            },
            {
            	key:       'pgl:buttonnext',
               	component: 'ouilforum'
            },
            {
               	key:       'pgl:buttonprev',
               	component: 'ouilforum'
            }
   		]).done(function(s) {
   			PARAMS.str.loadButton = s[0];
   			PARAMS.str.buttonClose = s[1];
   			PARAMS.str.buttonNext = s[2];
   			PARAMS.str.buttonPrev = s[3];
   			initDialog();
        });	
    };
    
    /**
     * Attach the gallery and collect all images sources.
     * @param {Node} sourceNode Target node.
     */
    var loadGallery = function(sourceNode) {
    	var mathNode;
    	$('#'+sourceNode).find('img:not(.emoticon), span.MathJax').each(function() {
    		type = this.nodeName === 'IMG' ? 'image' : 'span';
    		if (type === 'image') {
        		PARAMS.urls.push({
        			'type': type,
        			'src': $(this).attr('src'),
        			'alt': $(this).attr('alt'),
        			'formula': ($(this).attr('src').toLowerCase().search('/filter/wiris/') > -1 ||
        					$(this).attr('src').toLowerCase().search('/filter/tex/') > -1)
        		});
    		} else {
    			if ($(this).parent().attr('id') !== 'MathJax_Zoom') {
    				mathNode = $(this).parent().clone(true);
    				mathNode.find('#MathJax_ZoomFrame').remove();
    				mathNode.find('span.MathJax').removeAttr('tabindex');
		    		PARAMS.urls.push({
		    			'type': type,
		    			'html': mathNode,
		    			'formula': true
		    		});
    			}
    		}
    	});
    	PARAMS.images = PARAMS.urls.length;
    	setImage(0, false);
    	$('body').addClass('noscroll').append(PARAMS.gallery.dialog);
    	PARAMS.gallery.dialog.show('fade', function() {
    		PARAMS.gallery.dialog.focus();
    	});
    };
    
    /**
     * Set special display for formula images.
     * @param {Int} imageIndex 
     */
    var setFormulaImage = function(imageIndex) {
    	if (PARAMS.urls[imageIndex].type == 'image') {
	    	if (PARAMS.urls[imageIndex].formula) {
	    		PARAMS.gallery.imageHolder.addClass('formula');
	    	} else {
	    		PARAMS.gallery.imageHolder.removeClass('formula');
	    	}
    	} else {
	    	if (PARAMS.urls[imageIndex].formula) {
	    		PARAMS.gallery.spanHolder.addClass('formula');
	    	} else {
	    		PARAMS.gallery.spanHolder.removeClass('formula');
	    	}
    	}
    };
    
    /**
     * Remove the gallery and clean its content.
     */
    var closeGallery = function() {
    	PARAMS.gallery.dialog.hide('fade', function() {
    		PARAMS.gallery.dialog.remove();
    		PARAMS.gallery.imageHolder.attr('src', '').attr('alt', '');
    		PARAMS.gallery.spanHolder.html('');
    		PARAMS.gallery.navCounter.html('');
    		PARAMS.urls = [];
    		PARAMS.images = 0;
    		PARAMS.index = 0;
    		PARAMS.position = 0;
    		PARAMS.isfirst = false;
    		PARAMS.isLast = false;
    		PARAMS.loader.focus();
    		PARAMS.loader = null;
    	});
    	$('body').removeClass('noscroll');
    };
    
    /**
     * Set inage source.
     * @param {Int} index Image index in the list.
     * @param {Bool} fade Sets transition mode between images.
     */
    var setImage = function(index, fade) {
    	if (index < 0 || index >= PARAMS.images) {
    		return;
    	}
    	PARAMS.position = index;
    	var isImage = PARAMS.urls[index].type === 'image';
    	if (fade) {
    		PARAMS.gallery.contentContainer.fadeOut(50, function() {
    			setFormulaImage(index);
    			if (isImage) {
    				PARAMS.gallery.spanHolder.addClass('hidden_element');
    				PARAMS.gallery.spanHolder.html('');
    				PARAMS.gallery.imageHolder.attr('src', PARAMS.urls[index].src).attr('alt', PARAMS.urls[index].alt);
    				PARAMS.gallery.imageHolder.removeClass('hidden_element');
    			} else {
    				PARAMS.gallery.imageHolder.addClass('hidden_element');
    				PARAMS.gallery.imageHolder.attr('src', '').attr('alt', '');
    				PARAMS.gallery.spanHolder.removeClass('hidden_element');
    				PARAMS.gallery.spanHolder.append(PARAMS.urls[index].html);
    			}
    		}).fadeIn(100);
    	} else {
    		if (isImage) {
				PARAMS.gallery.spanHolder.addClass('hidden_element');
				PARAMS.gallery.spanHolder.html('');
    			PARAMS.gallery.imageHolder.attr('src', PARAMS.urls[index].src).attr('alt', PARAMS.urls[index].alt);
    			PARAMS.gallery.imageHolder.removeClass('hidden_element');
    		} else {
				PARAMS.gallery.imageHolder.addClass('hidden_element');
				PARAMS.gallery.imageHolder.attr('src', '').attr('alt', '');
				PARAMS.gallery.spanHolder.removeClass('hidden_element');
				PARAMS.gallery.spanHolder.append(PARAMS.urls[index].html);
    		}
    		setFormulaImage(index);
    	}
    	if (index == 0) {
    		if (PARAMS.gallery.buttonPrev.is(':focus')) {
    			PARAMS.gallery.dialog.focus();
    		}
    		PARAMS.gallery.buttonPrev.addClass('hidden_element');
    		PARAMS.isFirst = true;
    	} else {
    		PARAMS.gallery.buttonPrev.removeClass('hidden_element');
    		PARAMS.isFirst = false;
    	}
    	if (index == PARAMS.urls.length-1) {
    		if (PARAMS.gallery.buttonNext.is(':focus')) {
    			PARAMS.gallery.dialog.focus();
    		}
    		PARAMS.gallery.buttonNext.addClass('hidden_element');
    		PARAMS.isLast = true;
    	} else {
    		PARAMS.gallery.buttonNext.removeClass('hidden_element');
    		PARAMS.isLast = false;
    	}
    	PARAMS.gallery.navCounter.html((index+1)+' / '+PARAMS.images);
    };
    
    /**
     * Handle input keys. The navigation keys are set according to the page direction.
     * @param {Int} key Key code.
     */
    var handleKeys = function(key) {
    	if (key == 27) {
    		closeGallery();
    	} else if (key == PARAMS.keyNext) {
    		setImage(PARAMS.position+1, true);
    	} else if (key == PARAMS.keyPrev) {
    		setImage(PARAMS.position-1, true);
    	}
    };
    
    /**
     * Return the loading button.
     * @param {String} target The id of the target node.
     * @param {String} className Optional extra classes.
     */
    var getButtonLoader = function(target, className) {
    	if (className.length > 0) {
    		className = ' '+className;
    	}
    	return  '<button class="pgl_loader'+className+'" data-pgltarget="'+target+'" title="'+
    	PARAMS.str.loadButton+'"><i class="fa fa-picture-o"></i></button>';
    };
    
    /**
     * Init events for the gallery.
     */
    var initEvents = function() {
    	$('body').on('click', '.pgl_loader', function() {
    		PARAMS.loader = $(this);
    		loadGallery($(this).attr('data-pgltarget'));
    	});
    	$('body').on('click', '.pgl_close', closeGallery);
    	$('body').on('click', '.pgl_main .pgl_button_start', function() {
    		setImage(PARAMS.position-1, true);
    		if (PARAMS.isFirst) {
    			PARAMS.gallery.buttonNext.focus(); // Move focus to the other navigation button.
    		}
    	});
    	$('body').on('click', '.pgl_main .pgl_button_end', function() {
    		setImage(PARAMS.position+1, true);
    		if (PARAMS.isLast) {
    			PARAMS.gallery.buttonPrev.focus(); // Move focus to the other navigation button.
    		}
    	});
    	$('body').on('keyup', '.pgl_main', function(e) {
    		handleKeys(e.keyCode);
    	});
    	// Set a focus trap to avoid tabbing out of the gallery when it's active.
    	$('body').on('keydown', '.pgl_main .pgl_button_end', function(e) {
    		if (e.keyCode === 9 && !e.shiftKey) {
    			e.preventDefault();
    			PARAMS.gallery.buttonClose.focus();
    		}
    	});
    	$('body').on('keydown', '.pgl_main .pgl_button_start', function(e) {
    		if (e.keyCode === 9 && !e.shiftKey && PARAMS.isLast) {
    			e.preventDefault();
    			PARAMS.gallery.buttonClose.focus();
    		}
    	});
    	$('body').on('keydown', '.pgl_main .pgl_close', function(e) {
    		if (e.keyCode === 9) {
    			if (PARAMS.images == 1) {
        			e.preventDefault();
    			} else {
    				if (e.shiftKey) {
    					e.preventDefault();
        				if (PARAMS.isLast) {
        					PARAMS.gallery.buttonPrev.focus();
        				} else {
        					PARAMS.gallery.buttonNext.focus();
        				}
    				}
    			}
    		}
    	});
    };
    
    return {
    	/**
    	 * Init the gallery.
    	 * @params {Bool} isRTL The page direction.
    	 */
    	init: function(isRTL) {
    		initGallery(isRTL);
    	},
    	/**
    	 * Check if the node contains images.
    	 * @param {Node} node Target node.
    	 */
    	hasImages: function(node) {
    		return (node.find('img:not(.emoticon)').length > 0 || node.find('span.filter_mathjaxloader_equation').length > 0);
    	},
    	/**
    	 * Get the gallery loading button.
    	 * @params {String} target Target node for the gallery.
    	 */
    	getLoader: function(target, className) {
    		return getButtonLoader(target, className);
    	}
    };
});