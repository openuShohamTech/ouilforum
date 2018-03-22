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
 * Simple action menu.
 *
 * @package    mod_ouilforum
 * @copyright  2016 The Open University of Israel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_ouilforum;

defined('MOODLE_INTERNAL') || die();

/**
 * A simple accessible menu.
 * @author liorgi
 *
 */
class simpleaction_menu {

	/**
	 * The instance number. This is unique to this instance of the action menu.
	 * @var int
	 */
	protected $instance = 0;

	/**
	 * An array of menu items.
	 * @var array
	 */
	protected $menuitems = array();

	/**
	 * An array of attributes added to the container of the action menu.
	 * Initialised with defaults during construction.
	 * @var array
	 */
	private $attributes = array();

	/**
	 * An array of attributes added to the container of the primary actions.
	 * Initialised with defaults during construction.
	 * @var array
	 */
	private $itemsattributes = array();

    /**
     * Any text to use for the toggling menu element.
     * @var string
     */
    private $triggertext = '';
	
    /**
     * Trigger icon. 
     * @var string
     */
    private $triggericon = '<b class="caret"></b>';

    /**
     * Preset list of triggers types.
     * @var array
     */
    private $triggers = array(
    		'caret'=>'<b class="caret"></b>',
    		'right'=>'<span class="corner_caret rl"></span>',
    		'left'=>'<span class="corner_caret lr"></span>'
    );
    /**
     * Attributes to add to the trigger element.
	 * Initialised with defaults during construction.
     * @var array
     */
    private $triggerattributes;
    
    /**
     * A grid state will display the items in a row. Used for icons.
     * @var bool
     */
    private $grid = false;

    /**
     * Flip menu alignment side.
     * @var bool
     */
    private $flip = false;
    
    /**
     * Optional unique id.
     * @var string
     */
    private $unique_id = '';
    
    /**
	 * Constructs the action menu with the given items.
	 *
	 * @param null|array $actions An array of actions.
	 * @param null|string $class Extra class for the menu container.
	 */
	public function __construct($actions=null, $class=null, $unique_id=null) {
		static $initialised = 0;
		$this->instance = $initialised++;
		$this->set_unique_id($unique_id);
		
		$this->attributes = array(
				'id' => 'simple-action-menu-'.$this->unique_id.$this->instance,
				'class' => 'simpleactionmenu'
		);
		$this->itemsattributes = array(
				'id' => 'simple-action-menu-'.$this->unique_id.$this->instance.'-menu',
				'aria-hidden' => 'true',
				'class' => 'menu',
				'role' => 'menu'
		);
		$this->triggerattributes = array(
			    'aria-haspopup' => 'true',
    		    'aria-owns' => $this->itemsattributes['id'],
    		    'aria-controls' => $this->itemsattributes['id'],
    		    'aria-expanded' => 'false');
		
		$this->add($actions);
		$this->add_class($class);
	}

	/**
	 * Add/overwrite attributes, except reserved types.
	 * @param array $attributes
	 */
	public function set_attributes($attributes) {
		if (empty($attributes)) {
			return;
		}
		foreach ($attributes as $key=>$value) {
			$key = trim(strtolower($key));
			if ($key === 'id' || $key === 'class' || $key === 'data-closeaction' || $key === 'data-focusopen') {
				continue;
			}
			$this->attributes[$key] = $value;
		}
	}
	
	/**
	 * Set unique id for the menu instance.
	 * @param string $id
	 * @param bool $update Update attributes (for use when unique id is set after the menu is initialized)
	 */
	public function set_unique_id($id, $update=false) {
		$id = trim($id);
		if (!empty($id)) {
			$this->unique_id = $id.'-';
			if ($update) {
				$this->itemsattributes['id'] = 'simple-action-menu-'.$this->unique_id.$this->instance.'-menu';
				$this->attributes['id'] = 'simple-action-menu-'.$this->unique_id.$this->instance;
			}
		}
	}
	
	/**
	 * Initialises JS required for the action menu.
	 * The JS is only required once as it manages all action menus on the page.
	 */
	public function initialise_js() {
		static $initialised = false;
		if (!$initialised) {
			global $PAGE;
			// This will inlude the custom jquery plugin and call it on page load.
			$PAGE->requires->js_call_amd('mod_ouilforum/callplugin', 'init', array('.simpleactionmenu'));
			$initialised = true;
		}
	}

	/**
	 * Set the grid state of the menu.
	 * @param bool $state
	 */
	public function set_grid($state=true) {
		$this->grid = $state;
	}
	
	/**
	 * Flip the alignment of the menu.
	 * @param bool $state
	 */
	public function flip_side($state=true) {
		$this->flip = $state;
	}
	
	/**
	 * Adds a class to the menu container.
	 * 
	 * @param string $class
	 */
	public function add_class($class=null) {
		$class = trim($class);
		if (empty($class) || strpos($this->attributes['class'], $class) !== false) {
			return;
		}
		$this->attributes['class'].= ' '.$class;
	}
	
	/**
	 * Define if the menu will close after an item has been clicked.
	 * By default the menu will remain opened.
	 * 
	 * @param bool $status
	 */
	public function close_on_click($status=false) {
		if ($status === true) {
			$this->attributes['data-closeaction'] = 'true';
		}
	}
	
	/**
	 * Define if the first item will receive focus when the menu opens.
	 * By default no item will be selected.
	 * 
	 * @param bool $status
	 */
	public function focus_on_open($status=false) {
		if ($status === true) {
			$this->attributes['data-focusopen'] = 'true';
		}
	}
	
	/**
	 * Adds items to the action menu. To add a single item, use {@link simpleaction_menu::add_item()} instead.
	 *
	 * @param array $action
	 */
	public function add($action) {
		if (empty($action)) {
			return;
		}
		if (!is_array($action)) {
			$action = array($action);
		}
		foreach ($action as $item) {
			$this->add_item($item);
		}
	}

	/**
	 * Adds a single action to the action menu.
	 *
	 * @param moodle_url|pix_icon|string $action
	 */
	public function add_item($action=null) {
		if (empty($action) || (is_object($action) && (!$action instanceof moodle_url && !$action instanceof pix_icon))) {
			return;
		}
		$this->menuitems[] = $action;
	}

	/**
	 * Sets the title of the menu trigger element.
	 * 
	 * @param string $trigger
	 */
	public function set_menu_title($trigger) {
		$this->triggertext = $trigger;
	}
	
	/**
	 * Sets the attributes for the menu trigger element.
	 * 
	 * @param array $attributes
	 */
	public function set_menu_attributes($attributes=array()) {
		foreach ($attributes as $key=>$value) {
			$this->triggerattributes[$key] = $value;
		}
//		$label = trim($label);
//		if (!empty($label)) {
//			$this->triggerattributes['aria-label'] = $label;
//		}
	}
	
	/**
	 * Overwrite the default trigger icon at the end of the trigger text.
	 * 
	 * @param null|string|pix_icon $icon
	 */
	public function set_menu_icon($icon=null) {
		$this->triggericon = $icon;
	}

	/**
	 * Set the trigger icon from a list. 
	 * Available values are: 'caret', 'right', 'left'.
	 * @param string $key
	 */
	public function select_menu_icon($key=null) {
		if (isset($this->triggers[$key])) {
			$this->triggericon = $this->triggers[$key];
		}
	}
	/**
	 * Returns the primary actions ready to be rendered.
	 * 
	 * @return array
	 */
	public function get_items() {
		return $this->menuitems;
	}
	
	/**
	 * Gets rendered output of the ftigger element.
	 * 
	 * @param core_renderer $output If null, default output will be used instead
	 * @return string
	 */
	public function get_trigger(\core_renderer $output=null) {
		global $OUTPUT;
		if ($output === null) {
			$output = $OUTPUT;
		}
		if ($this->triggericon instanceof renderable) {
			$pixicon = $output->render($this->triggericon);
		} else {
			$pixicon = $this->triggericon;
		}
		return \html_writer::tag('button', '<span class="simpleactionmenu_title">'.$this->triggertext.'</span>'.$pixicon, $this->triggerattributes);
	}

	/**
	 * Returns a rendered output of the menu.
	 * If the menu has no items, will return an empty result.
	 * 
	 * @param core_renderer $output optional output renderer
	 * @return string
	 */
	public function render(\core_renderer $output=null) {
		if (count($this->menuitems) == 0) {
			return '';
		}
		global $OUTPUT, $PAGE;
		if ($output === null) {
			if ($OUTPUT instanceof core_renderer) {
				$output = $OUTPUT;
			} else {
				$output = new \core_renderer($PAGE, null);
			}
		}
		$this->initialise_js();
	
		if ($this->grid) {
			$this->add_class('grid');
		}
		if ($this->flip) {
			$this->add_class('flip_side');
		}
		$menu = \html_writer::start_tag('div', $this->attributes);
		$menu.= $this->get_trigger($output);
		$menu.= \html_writer::start_tag('ul', $this->itemsattributes);
		foreach ($this->get_items() as $action) {
			if ($action instanceof renderable) {
				$content = $output->render($action);
			} else {
				$content = $action;
			}
			$menu.= \html_writer::tag('li', $content, array('role' => 'presentation'));
		}
		$menu.= \html_writer::end_tag('ul');
		$menu.= \html_writer::end_tag('div');
		return $menu;
	}
}