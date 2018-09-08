<?php

error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$siteToCrawl = "hu.indeed.com";
$entryPoint = "/Developer-jobs";
$z=10;
$c=1;

echo setXMLHeader($siteToCrawl);

function getContent($link) {
	$html=getpagebycurl($link);
	$pageContent = new simple_html_dom();
	$pageContent->load($html);
	$mainPageJobPostIdentifier = "div,span,h1,h2";
	$out="";
	foreach($pageContent->find($mainPageJobPostIdentifier) as $jobPostEntry) {
		$out .= sanitize_for_xml(stripInvalidXml(html_entity_decode($jobPostEntry->plaintext)));
	}
	$out = trim(preg_replace('!\s+!', ' ', $out));
	echo $out;
	@$pageContent->clear();
	unset($pageContent);
	$pageContent = NULL;
	return $out;
}

function getLinks($page) {
	global $siteToCrawl, $LIMIT, $CNT, $entryPoint, $z, $c;

	$mainPageJobPostIdentifier = "h2[class=jobtitle]";
	$mainPageJobPostTitleIdentifier = "/a";
	$mainPageJobPostLinkIdentifier = "div[class=jobTitle]/a";
	
	$pubdatefilter = "div[class=extras]/div[class=postedDate]";
	$descfilter = "div[class=row]/div[class=list_tasks]";
	
	$mainPageNextLinkIdentifier = "div[class=pagingWrapper]";

	$jobPostPubDate = date("Y-m-d H:i:s");
	$jobPostAuthor = $siteToCrawl;

	echo $page;

	$html=getpagebycurl($page);
	
//	$html = file_get_contents("test.html");
	$html = str_ireplace('class="slJobTitle"'," ",$html);
	
	$pageContent = new simple_html_dom();
	$pageContent->load($html);
	
	if (!$pageContent){
		return;
	}

	$LAD=lastAcceptDate();
	
	// loop through item entries on main page
	foreach($pageContent->find($mainPageJobPostIdentifier) as $jobPostEntry) {
		
		if ($CNT >= 500){
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

		$jobPostPubDate = date("Y-m-d 00:00:01");
		
		$desc='';
		

		$rssContentItem .= "<title>" . strip_tags(replaceCharsForRSS($jobPostTitle)) . "</title>\n";
		
		// get the URL of the entry
		$jobPostLink = $jobPostEntry->find($mainPageJobPostTitleIdentifier, 0)->href;
		
		
		if (!$jobPostLink){
			continue;
		}
		
		//check redirect site
		$jobPostLink = "https://" . $siteToCrawl . $jobPostLink;
		$headers = @get_headers($jobPostLink,1);
		
		if(strpos($headers[0],'302')===false) {} else {
			$loc = $headers["Location"];
			if (is_array($loc)) {
				$jobPostLink = end($loc);
			} else {
				$jobPostLink = $loc;
			}
		}
		
		$headers = @get_headers($jobPostLink,1);
		if(strpos($headers[0],'200')===false) {} else {
			$extracontent=getContent($jobPostLink);
			$desc.="\n".$extracontent;
		}
		
		//var_dump($jobPostLink);
		echo $c,". " ,$jobPostTitle."\n";
		echo $c,". ",  $jobPostLink."\n";
		echo "######"."\n";
		$c++;
		continue;
		

		/*
		echo $CNT,"\n";
		echo $jobPostTitle."\n----\n";
		echo $pubdate."\n----\n";
		echo $jobPostLink."\n------\n";
		echo $desc,"\n########\n";
		
		continue;
		*/
		
		$rssContentItem .= "<link>" . strip_tags($jobPostLink) . "</link>\n";
		
		// set pubDate
		$rssContentItem .= "<pubDate>" . strip_tags($jobPostPubDate) . "</pubDate>\n";
		// get main content body of the entry
		
		$rssContentItem .= "<description><![CDATA[ " . preg_replace('!\s+!', ' ', stripInvalidXml($desc)) . " ]]></description>\n";
		// set author as "source of data"
		$rssContentItem .= "<author>" . strip_tags($jobPostAuthor) . "</author>\n";
		
		$rssContentItem .= "</item>\n";
		echo $rssContentItem;
	}
	


	// get next URL; can be very site specific
	foreach($pageContent->find($mainPageNextLinkIdentifier) as $link){
		$nextLink = "https://" . $siteToCrawl . $entryPoint."&start=".$z;
		$z++;
		if(!empty($nextLink)) {
			@$pageContent->clear();
			unset($pageContent);
			$pageContent=NULL;
			getLinks($nextLink);
		}
		
	}
}


getLinks("https://" . $siteToCrawl . $entryPoint);
echo "</channel>\n";
echo "</rss>\n";

?>
