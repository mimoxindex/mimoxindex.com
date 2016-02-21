<?php
//error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$siteToCrawl = "www.itjobswatch.co.uk";
$entryPoint = "";

$basiclink="http://www.itjobswatch.co.uk/default.aspx?page=1&sortby=0&orderby=0&q=&id={ID}&lid=2618";

$resultdict=array();

function getLinks($page) {
	global $siteToCrawl, $LIMIT, $CNT, $resultdict;
	
	
	//echo $page."\n";
	// site specific DOM identifiers; 2. change this part
	$mainPageJobPostIdentifier = "td[class=c2]/a";
	$mainPageNextLinkIdentifier = "a[class=next]";

	$html=getpagebycurl($page);
	$pageContent = new simple_html_dom();
	$pageContent->load($html);

	if (!$pageContent){
		return;
	}
	foreach($pageContent->find($mainPageJobPostIdentifier) as $jobPostEntry) {
		$jobPostTitle = replaceCharsForRSS(sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->plaintext)))));
		$resultdict[$jobPostTitle]=1;
	}
	
	
	// get next URL; can be very site specific
	foreach($pageContent->find($mainPageNextLinkIdentifier) as $link){
		$nextLink = "http://" . $siteToCrawl . $link->href;
		if(!empty($nextLink)) {
			@$pageContent->clear();
			unset($pageContent);
			$pageContent=NULL;
			getLinks($nextLink);
		}
	}
	
}

function getSelectLink($page){
	global $siteToCrawl, $LIMIT, $CNT, $resultdict, $basiclink;
	$out=array();
	$html=getpagebycurl($page);
	$pageContent = new simple_html_dom();
	$pageContent->load($html);

	if (!$pageContent){
		return;
	}
	foreach($pageContent->find('div[class=selectCnt]/select') as $element) {
		if ($element->id == 'lstCategories'){
			foreach ($element->find('option[value]') as $e){
				//echo $e->value."\n";
				//echo $e->plaintext."\n";
				$l=str_replace("{ID}",$e->value,$basiclink);
				$out[$e->plaintext]=$l;
			}
		}
	}
	return $out;
}

$searchar=getSelectLink("http://" . $siteToCrawl . $entryPoint);

//old one:
//getLinks("http://" . $siteToCrawl . $entryPoint);


//new:
//echo '"'.$siteToCrawl."-".date('Y-m-d').'"'."\r\n";
echo '"TERM";"CATEGORY"'."\r\n";
foreach ($searchar as $group => $grouplink){
	unset($resultdict);
	$resultdict=array();
	getLinks($grouplink);
	ksort($resultdict);
	foreach ($resultdict as $k=>$v){
		echo '"'.htmlspecialchars_decode($k).'";"'.htmlspecialchars_decode($group).'"'."\r\n";
	}
}

?>
