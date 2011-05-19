<?php
/**
* WORK TIME LOG
* 2011-05-02 
* 10:40AM - Start
* 02:00PM - Legislators complete
*
* 05:00PM - Start
* 06:30PM - Committees/members complete
*
* 2011-05-03
* 05:45PM - Start
* 06:15PM - Set up for bills
*
* 2011-05-04
* 11:00PM - Start
* 12:00AM - Collected all bill URLs for all leg sessions (~8,000)
*
* 2011-05-05
* 06:45PM - Start
* 08:15PM - Bill complete (w/ sponsors,actions,votes)
*
* 2011-05-06
* 10:00PM - Start
* 11:30PM
*/

require 'scrape_classes.php';


// CALLING SCRAPING ACTIONS //
// (either GET['model'], eg: http://domain.com/scrape.php?model=Legislator
//  or direct command line argument, eg: php scrape.php Legislator //

if(empty($_GET['model']) && empty($argv[1])) {
	pr('Error: No model specified.');
} else {
	!empty($_GET['model'])? $model = $_GET['model']: $model = $argv[1];
	
	switch($model) {
		case 'Bill':
			// Scrape Bills
			$B = new Bill();
			$B->crawl_pages();
		
		break;
		
		case 'Legislator':
			// Scrape Legislators
			$L = new Legislator();
			$L->crawl_pages();
		break;
		
		case 'Committee':
			// Scrape Committees
			$C = new Committee();
			$C->crawl_pages();
		break;
		
		default: 
			pr('Error: Incorrect model specified.');
		break;
	}
}


 

?>
