<?php

// Helper classes
require_once 'simple_html_dom.php';

// Initialize
session_start();
set_time_limit(7200);   // lift the 30 sec limit

// List of URLs that have been designated as non-existent

# Uncomment to clear session: # 
# $_SESSION = array();
 

// Debug f()
function pr($x) {
	
	echo "\n\n<br><br>";
	
	if(is_array($x) || is_object($x)) {
		print_r($x);
	} else { echo $x; }
	
	echo "\n\n<br><br>";
}

// Cache page f()
// Depends on simple_html_dom.php
function get_page($url) {
	$cache_dir = 'cache';
	
	$max_filesize = 1000000; // 1MB
	// TO DO: Go back for these later if you skip them
	
	$translate = array(
		'/' => '_SL_',
		':' => '_CO_',
		'?' => '_QM_',
		'&' => '_AMP_',
		'=' => '_EQ_',
		'.' => '_P_',
	);
	
	$filename = str_replace(array_keys($translate),array_values($translate),$url);
	$file_dest = $cache_dir.'/'.$filename;
	 
	if(!file_exists($file_dest)) {
		$str = '';
		$str = @file_get_contents($url);
		
		// Error correcting attempts
		while(empty($str)) {
			$n++;
			pr('Pull of '.$url.' failed.  Waiting 5 seconds...');
			sleep(5);
			pr('Attempting another pull of '.$url);
			$str = @file_get_contents($url);
			if($n > 10) { die('FAILED after ten attempts to get '.$url.'.  Giving up.'); }
		}
		 
		file_put_contents($file_dest,$str);
	}
	
	if(filesize($file_dest) > $max_filesize) {
		pr('File exceeds '.(round($max_filesize/1000000,2)).'MB.  Will not open as HTML-parsable.');
		return false;
	} 	
	
	return file_get_html($file_dest);
	
}

// Retrieve cache array f()
function get_cached_data($key) {
	$cache_dir = 'cache';
	$filename = $key;
	$file_dest = $cache_dir.'/'.$filename;
	
	if(file_exists($file_dest)) {
		$str = file_get_contents($file_dest);
		if(empty($str)) { return false; }
		else { return unserialize($str); }
	} else {
		return false;
	}
}
// Create cache array f()
function set_cached_data($key,$data=array()) {
	$cache_dir = 'cache';
	$filename = $key;
	$file_dest = $cache_dir.'/'.$filename;
	pr('writing file: '.$file_dest);
	
	return file_put_contents($file_dest,serialize($data));
	 
}
/*-----------------------------------------*/

// Parent class
class Object {
	var $fields   = '';
	var $data 	  = array(); // final array containing all items and their field/value pairs
	var $base_url = 'http://malegislature.gov';
	var $uri	  = '';
	var $data_dir = 'data';
	var $data_ext = '.json';
	
	// Chamber: [upper|lower|joint]
	var $chambers = array('senate' => 'upper','house' => 'lower','joint' => 'joint');
			
	function __construct() {
		$this->make_fields_array();
		$this->base = $this->base_url.$this->uri;
		$this->current_session = $this->get_session_from_date(date('Y'));	
	}
	protected function generate_part_filename($str,$part_n,$total_parts_n) {
		return $str.'_part_'.$part_n.'_of_'.$total_parts_n;
	}
	/**
	*
	* New sessions begin around Jan 5th of each year
	*/
	protected function get_session_from_date($date) {
		//2011 = 187th session
		$years    = range(2011-187,date('Y'));
		$sessions = range(0,date('Y')-2011+187);
		
		$this->session_map = array_combine($years,$sessions);
		$session_n = $this->session_map[date('Y',strtotime($date))];
		return $session_n;
	}

	protected function make_fields_array() {
		$schema = array_filter(explode(',',$this->fields),'trim');
		return $schema;
	}
	
	// Recursive method to build hierarchical data array
	// TO DO: ensure it is for multiple items
	protected function populate_child_data() {
		
		foreach($this as $property => $value) {
			if(is_object($this->{$property}) && isset($this->{$property}->data)) {
				$this->{$property}->populate_child_data();
				$this->data[strtolower($property)] = $this->{$property}->data;
			}		
		}
	}
	/**
	* Save serialized scraped data to disk
	*
	*/
	protected function store($filename,$data) {
		$file_dest = $this->data_dir.'/'.$filename.$this->data_ext;
		
		$data_str = json_encode($data);
		
		pr('Storing data to file: '.$file_dest);
		
		return file_put_contents($file_dest,$data_str);
	}
	/**
	* Check to see if the data is saved to disk
	*
	*/
	protected function is_stored($filename) {
		$file_dest = $this->data_dir.'/'.$filename.$this->data_ext;
		
		return file_exists($file_dest);
	}	
	
	
}



// Bill
class Bill extends Object  {
	 var $fields = 'session, chamber, bill_id, title';
	
	 var $uri = '/Bills/SearchResults?';
	 
	 var $get_params = array(
		'perPage' 				=> 50,
		'Input.GeneralCourtId'  =>  1,
		'pg'					=>  1
	 );
	 
	 // Custom map for GET param value to legislative session number: Input.GeneralCourtId=1
	 var $get_GeneralCourtId = array(
	 	1 => 187,
		2 => 186
	 );
	 
	 function get_index_page_urls() {
	 	$html	    = new simple_html_dom();
	 	$get_params = $this->get_params;
		
		// Full (paginated) listings of all bills under one legislative session
		// (passed by an index id)
		$pages_per_session 	   = array();
		$index_start_pages	   = array();
		foreach($this->get_GeneralCourtId as $id => $n_session) {
			// Switch session
			$get_params['Input.GeneralCourtId'] = $id;
			
			// Get page 1 search results
			$index_start_pages[$n_session] = $this->base_url.$this->uri.http_build_query($get_params);
			
			$html = get_page($index_start_pages[$n_session]);
			
			// Determine the max number of pages of results by scraping this value
			// Extract from "page 1 of 999"
			$arr = explode(' of ',$html->find('div.searchPageCount',0)->plaintext); 
			 
			$pages_per_session[$n_session] = trim($arr[1]);
		}
	    $index_pages = array();
		
  
		// Cache URL list
		if(!$urls = get_cached_data(__CLASS__.'.urls')) {
    		
			$urls = array();
			
	    	// Collect all urls
			// Then build a for loop (for each session) based on total pages
			foreach($pages_per_session as $n_session => $n_pages) {
				for($i=1;$i<=$n_pages;$i++) {
					
					$get_params['pg'] = $i;
					$get_params['Input.GeneralCourtId'] = array_search($n_session,$this->get_GeneralCourtId);
					 
					$url = $this->base_url.$this->uri.http_build_query($get_params);
					
					pr('crawling index page '.$i.' of '.$n_pages.' for session: '.$n_sesssion.'.... URL: '.$url);
					  
					$html = get_page($url);
					
					foreach($html->find('td.searchResultLeftCol li.billNum a') as $a) {
						if(strpos($a->href,'/Bills/'.$n_session.'/') !== false && array_search($this->base_url.$a->href,$urls) === false) {
							pr('adding '.$this->base_url.$a->href.'...');
							$urls[] = $this->base_url.$a->href;
						}
					}
					
					// Clean up to conserve memory
					$html->clear(); 
					unset($html);
					 
				}
			}
			set_cached_data(__CLASS__.'.urls', $urls);
		}
		
		return $urls;
	 }
	 
	 function crawl_pages() {
	 	$html	    = new simple_html_dom();
	 	$get_params = $this->get_params;
		 
		$urls = $this->get_index_page_urls();
		
		#pr($urls);
		
		$split_after_n_rows = 300;
		$total_parts = ceil(count($urls) / $split_after_n_rows);
		$n_part = 1;
		
		// Do not re-scrape data we already have completed.		
		while($this->is_stored($this->generate_part_filename(__CLASS__,$n_part,$total_parts))) {
			pr('SKIP '.$split_after_n_rows.' records.  Data part '.$n_part.' exists.');
			$n_part++;
			$urls = array_slice($urls,$split_after_n_rows-1);
		} 
		
		pr(count($urls).' pages left to scrape.');
		
		// Crawl each bill page
		foreach($urls as $n => $url) {
			 
			// Check to see if we've reached the split mark.
			// If so, split results array into 1 JSON file, and destroy the var to 
			// conserve memory
			if($n != 0 && $n%$split_after_n_rows == 0) {
				$this->store($this->generate_part_filename(__CLASS__,$n_part,$total_parts),$this->data);
				$n_part++;
				unset($this->data);
				$this->data = array();
			} 
			
			 
		 	// Reset row container
			$v = array(); 
			
			#if($n < 209) { continue; }
			#if($n > 215) { break; }
			
			pr('On '.$n.': '.$url);
			
			$html = get_page($url);
			
			if(!$html) {
				pr('SKIP!  Error pulling '.$url.'.  File might be too large to process.');
				continue; 
			}
			
			// Session
			foreach($this->get_GeneralCourtId as $n_session) {
				if(strpos(strtolower($url), '/'.$n_session.'/') !== false) {
					$v['session'] = $n_session; 			
				}
			}
			
			// Chamber
			foreach($this->chambers as $label => $code) {
				if(strpos(strtolower($url), '/'.$label.'/') !== false) {
					$v['chamber'] = $code; 			
				}
			}
			
			// Bill ID
			$e = $html->find('title', 0);
			$v['bill_id'] = trim(str_replace('Bill','',$e->plaintext));
			
			
			// Title
			$e = $html->find('div#Column1 h1 span', 0);
			$v['title'] = trim($e->plaintext);
			
			// add_action(actor, action, date, type=None, **kwargs)
			$v['actions'] = array();
			foreach($html->find('div#billHistory table.dataTable tbody tr') as $tr) {
				if($tr->find('td[headers=bDate]',0)) {
					$v['actions'][] = array(
						'date'   => trim($tr->find('td[headers=bDate]',0)->plaintext),
						'actor'  => trim($tr->find('td[headers=bBranch]',0)->plaintext),
						'action' => trim($tr->find('td[headers=bAction]',0)->plaintext),
					);
				}
			}
			
			
			// add_sponsor(type: [primary|cosponsor], name, **kwargs)
			$v['sponsors'] = array();
			
			// primary
			$v['sponsors'][] = array(
				'type' => 'primary',
				'name' => trim($html->find('div#Column1 div p a',0)->title)
			);
			
			// cosponsors
			$e = $html->find('div#billSummary p',0);
			
			if(!is_object($e)) {
				pr('SKIP: Parsing error.  Probably a 404 page: '.$url);
				continue;
			}
			foreach($e->find('a') as $a) {
				$v['sponsors'][] = array(
					'type' => 'cosponsor',
					'name' => trim($a->title)
				);
			}
			
			
			// add_vote(chamber, date, motion, passed, yes_count, no_count, other_count, type='other', **kwargs)
			foreach($v['actions'] as $action_data) {
				$str = $action_data['action'];
				#pr('y and n??? :'.strtolower($str));
				if(strpos(strtolower($str),'yeas') !== false && strpos(strtolower($str),'nays') !== false) {
					 
					$parts = explode(' ',$str);
					$parts_lower = array_map('strtolower',$parts);
					
					$yes_key = array_search('yeas',$parts_lower);
					$no_key = array_search('nays',$parts_lower);
				    
					$passed = NULL;
					
					$adopted_key  = array_search('adopted',$parts_lower);
					$rejected_key = array_search('rejected',$parts_lower);
					
					if($adopted_key !== false) {
						$passed = true;
						$motion = join(' ',array_slice($parts,0,$adopted_key));
					} elseif($rejected_key !== false) {
						$passed = false;
						$motion = join(' ',array_slice($parts,0,$rejected_key));
					}
					
					$v['votes'][] = array(
						'chamber'   	=> $this->chambers[strtolower($action_data['actor'])],
						'date'	    	=> $action_data['date'],
						'motion'    	=> $motion,
						'passed'    	=> $passed,
						'yes_count' 	=> $parts[$yes_key-1],
						'no_count' 	 	=> $parts[$no_key-1],
						'other_count'   => ''
					);
				}
			}
			
			// add_title(title) -- in case of alternative titles
			// none available as of 5/5/2011
			
			// add_version(name, url, **kwargs) -- in case of alternative text
			// none available as of 5/5/2011
			
			// add_document(name, url, **kwargs) -- supplementary docs
			// none available as of 5/5/2011
			
			// add_source(url, retrieved=None, **kwargs)
			$v['sources'] = array('url' => $url);
			
			 
			
			// Clean up to conserve memory
			$html->clear(); 
			unset($html);
			
			$this->data[$n] = $v;
			
			pr('mem: '.memory_get_usage(true)); 
				
		}
		pr($this->data);
		
		// Save JSON array to disk (last piece)
		$this->store($this->generate_part_filename(__CLASS__,$n_part,$total_parts),$this->data);
	}
}


 
/**
 * Legislator
 * associated: source
 * fields missing: term, first_name, last_name, middle_name
 */ 
class Legislator extends Object {
	var $fields   = 'term, chamber, district, full_name, first_name, last_name, middle_name, party, url';
	var $uri = '/People/Profile/';
	
	function get_index_page_urls() {
		// Person's three initials plus 0-9 (for dupe initials)
		// ex: /People/Profile/AMF0
		
		$i = 0;
		$html = new simple_html_dom();
		
		 
		// Cache URL list
		if(!$urls = get_cached_data(__CLASS__.'.urls')) {
			
			$urls = array();
			
			// First get lists of House and Senate members
			$senate = $this->base_url.'/People/Senate';
			$house  = $this->base_url.'/People/House';
			
			foreach(array($house,$senate) as $url) {
				$html = file_get_html($url);
				foreach($html->find('a') as $a) {
					#pr($a->href);
					
					if(strpos($a->href,$this->uri) !== false && array_search($this->base_url.$a->href,$urls) === false) {
						pr('adding to indexpages array...');
						$urls[] = $this->base_url.$a->href;
					}
				}
				
				// Clean up to conserve memory
				$html->clear(); 
				unset($html);
			}
			set_cached_data(__CLASS__.'.urls', $urls);
		}
		
		return $urls;
	}
	
	
	function crawl_pages() {
		// Person's three initials plus 0-9 (for dupe initials)
		// ex: /People/Profile/AMF0
		
		$i = 0;
		$html = new simple_html_dom();
		 
		$urls = $this->get_index_page_urls();
		
		#pr($urls);
		
		// Crawl each member page
		foreach($urls as $n => $url) { 
		 	// Reset row container
			$v = array();
			 
			#pr('On: '.$url);
			
			$html = get_page($url);
			
			// Term
			// If this refers to the length of the term, this is NOT AVAILABLE ON MEMBER PAGES as of 5/2/2011
			// If it refers to what session number it is (eg "187th"), then use that.
			$v['term'] = $this->current_session;
			
			// Chamber
			$e = $html->find('div.bioDescription ul', 0);
			foreach($e->find('li') as $li) {
				if(strpos($li->innertext,'mahouse') !== false) {
					$v['chamber'] = 'lower';
				} elseif(strpos($li->innertext,'masenate') !== false) {
					$v['chamber'] = 'upper';
				}
			}
			// Party
			$matches = array();
			$e = $html->find('div.bioDescription > div', 0);
			preg_match('/([^,]+)\,/', $e->plaintext, $matches);	

			$v['party'] = trim($matches[1]);
			
			// Name
			$e = $html->find('title', 0);
			 
			$v['full_name'] = trim(ltrim(trim(str_ireplace('Member Profile','',$e->plaintext)),'-'));
			
			// District
			// to do: clean up 10% that dont have a period to match
			// alternative matching tokens: ','  '-'  '&mdash;'  'consist'/i 'district'/i 
			$v['district'] = NULL;
			
			$e = $html->find('div#District div.widgetContent', 0); 
			$haystack = substr($e->innertext,0,110);
			
			#pr($haystack);
			
			// Manual match
			$manual_match = array(
			 'Middlesex and Essex',
			 'Worcester, Hampden, Hampshire and Franklin', 
			 'Middlesex, Suffolk and Essex',
			 'Berkshire, Hampshire and Franklin',
			 'Norfolk, Bristol and Plymouth'
			);
			foreach($manual_match as $manual_district) {
				if(substr_count($haystack,$manual_district)) {
					$v['district'] = $manual_district;
				}
			}
			if(!$v['district']) {
				$regex_attempts = array(
					'/([^.]+)\./',
					'/([^,]+),/'
				);
				$v['district'] = '(could not parse)';
				
				foreach($regex_attempts as $regex) {
					$matches = array();
					preg_match($regex, $haystack, $matches);		
					#pr($matches);
					if(!empty($matches[1]) && !substr_count(strtolower($matches[1]),'consisting')) {
						$v['district'] = trim(str_ireplace('district','',$matches[1]));
						break;
					}
				}
				 
				// Manual cleanup
				 // all caps to capzd words
				if(strtoupper($v['district']) == $v['district']) {
					$v['district'] = ucwords(strtolower($v['district']));
				}
				if(substr_count($v['district'], 'Frist')) {
					$v['district'] = str_replace('Frist','First',$v['district']);
				}
				
				// Thirty-sixth Middlesex
				// 9th to Ninth
			 
			}
			// no punc: Middlesex and Essex Consisting of Malden
			
			if($v['district'] == '(could not parse)') {
				pr('trouble: ');
				pr($haystack);
			}
			// Sources (only one)
			$v['sources'] = array(
				array('url' => $url)
			);
			
			
			// Clean up to conserve memory
			$html->clear(); 
			unset($html);
			
			$this->data[$n] = $v;
			 
				
		}
		pr($this->data);
		
		// Save serialized array to disk
		$this->store(__CLASS__,$this->data);
	}
}

/**
 * Committee
 * associated: members
 * 
 */ 
class Committee extends Object {
	var $fields = 'chamber,committee,subcommittee';
	var $uri = '/Committees/';
	
	function get_index_pages() {
		// 3 types: /[Session number]/Joint -OR- /[House|Senate]
		// ex: /Committees/187/Joint
		
		$i = 0;
		$html = new simple_html_dom();
		 
		// Cache URL list
		if(!$urls = get_cached_data(__CLASS__.'.urls')) {
			
			$urls = array();
			
			// First get lists of all committees
			$index_pages = array(
				$this->base_url.$this->uri.$this->current_session.'/Joint',
				$this->base_url.$this->uri.'House',
				$this->base_url.$this->uri.'Senate',
			);
			foreach($index_pages as $url) {
				$html = file_get_html($url);
				foreach($html->find('.widgetContent a') as $a) {
					pr($a->href);
					
					if(strpos($a->href,$this->uri) !== false && array_search($this->base_url.$a->href,$urls) === false) {
						pr('adding...');
						$urls[] = $this->base_url.$a->href;
					}
				}
				
				// Clean up to conserve memory
				$html->clear(); 
				unset($html);
			}
			set_cached_data(__CLASS__.'.urls', $urls);
		}
		return $urls;
	}
	
	
	function crawl_pages() {
		// 3 types: /[Session number]/Joint -OR- /[House|Senate]
		// ex: /Committees/187/Joint
		
		$i = 0;
		$html = new simple_html_dom();
		 
		$urls = $this->get_index_page_urls();
		 
		#pr($urls);
		
		// Crawl each committee page
		foreach($urls as $n => $url) { 
		 	// Reset row container
			$v = array(); 
			
			pr('On: '.$url);
			
			$html = get_page($url);
			
			// Committee (name)
			$e = $html->find('title', 0);
			 
			$v['committee'] = trim($e->plaintext);
			 
			foreach($this->chambers as $label => $code) {
				if(strpos(strtolower($v['committee']), $label.' committee') !== false) {
					$v['chamber'] = $code; 			
				}
			}
			
			// Members (ranking)  fields: legislator (name), role (position)
			$e = $html->find('div.membersGally');
			
			foreach($e as $e1) {
				foreach($e1->find('div.describe') as $div) {
					// Get name and rank
					#pr($div->innertext);
					$v['members'][] = array(
						'legislator' => $div->find('a',0)->plaintext,
						'role'		 => str_replace('<br>',' ',$div->find('b',0)->innertext)
					);
				}
			}
			
			// Members (non-ranking)
			$e = $html->find('div.membersGallyList');
			
			foreach($e as $e1) {
				foreach($e1->find('li') as $li) {
					// Get name
					#pr($li->innertext);
					$v['members'][] = array(
						'legislator' => $li->find('a',0)->plaintext
					);
				}
			}
			 
			 
			// URL
			$v['url'] = $url;
			
			
			// Clean up to conserve memory
			$html->clear(); 
			unset($html);
			
			$this->data[$n] = $v;
			 
				
		}
		pr($this->data);
		
		// Save serialized array to disk
		$this->store(__CLASS__,$this->data);
	}
}
?>