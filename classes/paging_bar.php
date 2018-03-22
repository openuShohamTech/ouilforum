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
 * Forum paging bar.
 *
 * @package    mod_ouilforum
 * @copyright  2016 The Open University of Israel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ouilforum;

defined('MOODLE_INTERNAL') || die();

/**
 * Paging bar for the forum.
 */
class paging_bar {

	/**
	 * @var int The maximum amout of entries to display per page
	 */
	public $perpage;
	
	/**
	 * @var int The total amount of entries
	 */
	public $totalamount;
	
	/**
	 * @var int The current page
	 */
	public $currpage;
	
	/**
	 * @var string|moodle_url The base url for each page entry
	 */
	public $base_url;
	
	/**
	 * @var string The page variable that is added to the url
	 */
	public $pagevar;
	
	/**
	 * @var int The maximum amounts of links. Extra navigation is added to handle larger amount
	 */
	public $maxpaging;
	
	/**
	 * @var array An array of strings containing the links
	 */
	public $pagelinks = array();
	
	/**
	 * @var int A unique id for every instance of the paging bar in the page
	 */
	private static $unique_id = 0;
	
	/**
	 * Constructor paging_bar with only the required params.
	 *
	 * @param int $totalamount The total number of entries
	 * @param int $currpage The current page. Default 0
	 * @param int $perpage The number of entries per page. Default 25
	 * @param string|moodle_url $base_url url of the current page
	 * @param string $pagevar name of page parameter that holds the page number
	 * @param int $maxpaging Maximum amount of page numbers in the bar
	*/
	public function __construct($totalamount=0, $currpage=0, $perpage=25, $base_url='/', $pagevar='page', $maxpaging=10) {
		$this->totalamount	= $totalamount;
		$this->currpage		= $currpage;
		$this->perpage		= $perpage;
		$this->base_url		= $base_url;
		$this->pagevar		= $pagevar;
		$this->maxpaging	= $maxpaging;
		self::$unique_id++;
	}
	
	/**
	 * Build and render the paging bar.
	 * @param int $totalamount
	 * @param int $currpage
	 * @param intr $perpage
	 * @param string $base_url
	 * @param string $pagevar
	 * @param int $maxpaging
	 * @return string
	 */
	public static function print_paging_bar($totalamount=0, $currpage=0, $perpage=25, $base_url='/', $pagevar='page', $maxpaging=10) {
		$pagingbar = new paging_bar($totalamount, $currpage, $perpage, $base_url, $pagevar, $maxpaging);
		return $pagingbar->render();
	}
	
	/**
	 * Prepares the paging bar for output.
	 */
	public function prepare() {
		$str = array(
				'first' => get_string('pagingbar:first', 'ouilforum'),
				'last'  => get_string('pagingbar:last', 'ouilforum'),
				'next'  => get_string('pagingbar:next', 'ouilforum'),
				'prev'  => get_string('pagingbar:previous', 'ouilforum')
		);
		if (!$this->totalamount)
			return;

		if ($this->currpage < 0) {
			$this->currpage = 0;
		}
		if ($this->perpage <= 0) {
			$this->perpage = 1;
		}
		if ($this->maxpaging <= 0) {
			$this->maxpaging = 1;
		}
		if ($this->totalamount > $this->perpage) {
			$currpage = $this->currpage + 1;

			// Set first and previous links.
			if ($currpage > $this->maxpaging)
				$this->pagelinks[] = \html_writer::link(new \moodle_url($this->base_url), '&lt;&lt;', 
														array('title'=>$str['first'], 'aria-label'=>str['first'], 'rel'=>'first'));
			if ($this->currpage > 0) {
				$this->pagelinks[] = \html_writer::link(new \moodle_url($this->base_url, array($this->pagevar=>($this->currpage-1))), '&lt;', 
														array('title'=>$str['prev'], 'aria-label'=>$str['prev'], 'rel'=>'previous'));
			}
	
			// Find last page.
			if ($this->totalamount > 0) {
				$lastpage = ceil($this->totalamount / $this->perpage);
			} else {
				$lastpage = 1;
			}
	
			$displaycount = $displaypage = 0;
			$currpage = $page_group = floor($this->currpage / $this->maxpaging)*$this->maxpaging;
	
			// Populate paging links.
			while ($displaycount < $this->maxpaging && $currpage < $lastpage) {
				$displaypage = $currpage + 1;
	
				if ($this->currpage == $currpage) {
					$this->pagelinks[] = '<span tabindex="0" aria-label="page '.$displaypage.', current page" class="currentpage">'.$displaypage.'</span>';
				} else {
					$this->pagelinks[] = \html_writer::link(new \moodle_url($this->base_url, array($this->pagevar=>$currpage)), $displaypage);
				}
				$displaycount++;
				$currpage++;
			}
	
			// Set next and last links.
			$lastpageactual = $lastpage - 1;
			if ($this->currpage < $lastpageactual) {
				$this->pagelinks[] = \html_writer::link(new \moodle_url($this->base_url, array($this->pagevar=>($this->currpage+1))), '&gt;', 
														array('title'=>$str['next'], 'aria-label'=>$str['next'], 'rel'=>'next'));
			}
			if ($lastpage > $page_group+$this->maxpaging) {
				$this->pagelinks[] = \html_writer::link(new \moodle_url($this->base_url, array($this->pagevar=>($lastpage-1))), '&gt;&gt;', 
														array('aria-label'=>$str['last'], 'rel'=>'last', 
															'title'=>get_string('page', 'ouilforum').' '.$lastpage));
			}
		}
	}
	
	/**
	 * Render the paging bar
	 * @return string
	 */
	public function render() {
		$output = '<ul role="navigation" aria-labelledby="paginglabel'.self::$unique_id.'">';
		$this->prepare();
	
		if ($this->totalamount > $this->perpage) {
			foreach ($this->pagelinks as $link) {
				$output.= '<li>'.$link.'</li>';
			}
			$output = '<span id="paginglabel'.self::$unique_id.'" class="for-sr">'.get_string('pagingbar', 'ouilforum').'</span>'.$output;
			return \html_writer::tag('div', $output, array('class' => 'ouilforum_paging align_end'));
		}
	}
}