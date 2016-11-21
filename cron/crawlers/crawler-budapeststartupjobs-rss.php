<?php
error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$siteToCrawl = "budapest.startup.dreamjo.bs";
$entryPoint = "/en/allasok";

echo setXMLHeader($siteToCrawl);

function getLinks($page) {
	global $siteToCrawl, $LIMIT, $CNT;

	// site specific DOM identifiers; 2. change this part
	$mainPageJobPostIdentifier = "div[class=job-listing-row]";
	$mainPageJobPostTitleIdentifier = "div[class=job-listing-name]";
	$mainPageJobPostLinkIdentifier = "div[class=job-listing-view]/a";
	$mainPageNextLinkIdentifier = "a[class=next]";
	$descriptionfilter = "div[class=job-listing-keyword]";
	$pubdatefilter = "span[class=post-date]";
	$jobPostPubDate = date("Y-m-d H:i:s");
	$jobPostAuthor = $siteToCrawl;
	
	$html=getpagebycurl($page);
	$pageContent = new simple_html_dom();
	$pageContent->load($html);
	$LAD=lastAcceptDate();

	


	// loop through item entries on main page
	foreach($pageContent->find($mainPageJobPostIdentifier) as $jobPostEntry) {
		if ($CNT >= $LIMIT){
			echo "<!-- CNT LIMIT -->";
		 	echo "</channel>\n";
			echo "</rss>\n";
			die();
		}
		$CNT++;

		$rssContentItem = "<item>\n";
		$jobPostTitle = replaceCharsForRSS(sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->find($mainPageJobPostTitleIdentifier, 0)->plaintext)))));
		
		

		if (!$jobPostTitle){
			continue;
		}
		$jobdescr = sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->find($descriptionfilter, 0)->plaintext))));

		$jobPostPubDate = date("Y-m-d 00:00:01");

		$rssContentItem .= "<title>". strip_tags($jobPostTitle) . "</title>\n";
		// get the URL of the entry
		$lnk=$jobPostEntry->find($mainPageJobPostLinkIdentifier, 0)->href;
		
		$jobPostLink = $lnk;

		//die($jobPostTitle . $jobdescr . $lnk);
		
		//get extra info
		$headers = @get_headers($jobPostLink);
		if(strpos($headers[0],'200')===false) {} else {
			$extracontent=getContent($jobPostLink);
			$jobdescr.="\n".$extracontent;
		}
		
		if (!$jobPostLink){
			continue;
		}
		
		$rssContentItem .= "<link>" . strip_tags($jobPostLink) . "</link>\n";
		$rssContentItem .= "<pubDate>" . strip_tags($jobPostPubDate) . "</pubDate>\n";
		// get main content body of the entry
		$rssContentItem .= "<description><![CDATA[ " . str_replace("Read More","",preg_replace('!\s+!', ' ', stripInvalidXml($jobdescr))) . " ]]></description>\n";
		// set author as "source of data"
		$rssContentItem .= "<author>" . strip_tags($jobPostAuthor) . "</author>\n";

		//echo "jobPostTitle :",$trimmedTitle,", jobPostLink: ",$jobPostLink," pubdate: ",$pubdate,"\n";
		$rssContentItem .= "</item>\n";
		
		echo $rssContentItem;
	}

	// get next URL; can be very site specific
	foreach($pageContent->find($mainPageNextLinkIdentifier) as $link){
		$nextLink = $link->href;
		//echo $nextLink, "\n";
		// if there's another "next" URL then crawl
		if(!empty($nextLink)) {
			@$pageContent->clear();
			unset($pageContent);
			$pageContent=NULL;
			getLinks($nextLink);
		}
	}
}

function getContent($link) {
	$html=getpagebycurl($link);
	$pageContent = new simple_html_dom();
	$pageContent->load($html);
	$mainPageJobPostIdentifier = "div[class=grid]";
	$descriptionfilter = "div[class=grid-item]";
	$descriptionfilter2 = "div[class=grid-item--box]";
	$out="";
	foreach($pageContent->find($mainPageJobPostIdentifier) as $jobPostEntry) {
		//$out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->find($descriptionfilter, 0)->plaintext))));
		//$out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->find($descriptionfilter2, 0)->plaintext))));
		$out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->plaintext))));
	}
	@$pageContent->clear();
	unset($pageContent);
	$pageContent = NULL;
	return $out;
}

//main starting point
getLinks("http://" . $siteToCrawl . $entryPoint);
echo "</channel>\n";
echo "</rss>\n";

?>
