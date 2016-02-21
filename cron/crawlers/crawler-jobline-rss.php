<?php
error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$siteToCrawl = "jobline.hu";
$entryPoint = "/allasok/it_telekommunikacio?s=50";

function getContent($link) {
//	echo "getcontent:",$link,"\n";

	$html=getpagebycurl($link);
	$pageContent = new simple_html_dom();
	$pageContent->load($html);
//	echo $html;
//<section itemprop="skills"
//	$descriptionfilter = "section[class=adv]/section[itemprop=skills]";
	$descriptionfilter = "section[itemprop=skills]";
	$out = trim(html_entity_decode($pageContent->find($descriptionfilter, 0)->plaintext, 1, "UTF-8"));
	//echo "\n-----\n".$out."\n-----\n";
	if (empty($out)){
		$out = trim(html_entity_decode($pageContent->find("ul", 0)->plaintext));
	}
//	echo $out;
//	echo "\n------\n";
	@$pageContent->clear();
	unset($pageContent);
	$pageContent = NULL;
	$html = NULL;
	return $out;
}

echo setXMLHeader($siteToCrawl);

function getLinks($page) {
	global $siteToCrawl, $LIMIT, $CNT;

	$mainPageJobPostIdentifier = "div[class=fl]";
	$mainPageJobPostTitleIdentifier = "h3[itemprop=title],h2[itemprop=title],h1[itemprop=title]";
	$mainPageJobPostLinkIdentifier = "h3[itemprop=title]/a,h2[itemprop=title]/a,h1[itemprop=title]/a";
	$mainPageNextLinkIdentifier = "a[class=grid-pager]";
	$descriptionfilter = "div[class=post-content]";
	$pubdatefilter = "small[itemprop=datePosted]";

	$jobPostPubDate = date("Y-m-d H:i:s");
	$jobPostAuthor = $siteToCrawl;

	$html=getpagebycurl($page);

	$pageContent = new simple_html_dom();
	$pageContent->load($html);
	$LAD=lastAcceptDate();
	
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
		
		//echo $jobPostTitle."\n";
		
		if (!$jobPostTitle){
			continue;
		}
		
		$pubdate = trim(str_replace(" ", "", str_replace(".","-",replaceCharsForRSS($jobPostEntry->find($pubdatefilter, 0)->plaintext))),"-");
		
		$jobPostPubDate_i=strtotime($pubdate);
		
		if (!$jobPostPubDate_i){
			$jobPostPubDate_i=time();
		}
		
		if ($jobPostPubDate_i < $LAD){
			continue;
		}
		
		//check future
		if ($jobPostPubDate_i > time()){
			$jobPostPubDate = date("Y-m-d 00:00:01");
		} else {
			$jobPostPubDate = date("Y-m-d H:i:s", $jobPostPubDate_i);
		}

		$rssContentItem .= "<title>". strip_tags($jobPostTitle) . "</title>\n";
		// get the URL of the entry
		$lnk=$jobPostEntry->find($mainPageJobPostLinkIdentifier, 0)->href;
		
		$jobPostLink = "https://" . $siteToCrawl . $lnk;
		
		//echo $lnk."\n";
		//get extra info
		if (!empty($lnk)){
			$headers = @get_headers($jobPostLink);
			//echo $jobPostLink."\n";
			//print_r($headers);
			if(strpos($headers[0],'200')===false) {} else {
				$jobdescr=getContent($jobPostLink);
			}
		} else {
			$jobdescr="";
		}
		if (!$jobPostLink){
			continue;
		}
		
		$rssContentItem .= "<link>" . strip_tags($jobPostLink) . "</link>\n";

		$rssContentItem .= "<pubDate>" . strip_tags($jobPostPubDate) . "</pubDate>\n";
		// get main content body of the entry
		$rssContentItem .= "<description><![CDATA[ " . str_replace("Elvárások","",str_replace("Requirements","",preg_replace('!\s+!', ' ', stripInvalidXml($jobdescr))) ). " ]]></description>\n";
		// set author as "source of data"
		$rssContentItem .= "<author>" . strip_tags($jobPostAuthor) . "</author>\n";

		//echo "jobPostTitle :",$trimmedTitle,", jobPostLink: ",$jobPostLink," pubdate: ",$pubdate,"\n";
		$rssContentItem .= "</item>\n";
		echo $rssContentItem;

	}

	// get next URL; can be very site specific
	foreach($pageContent->find($mainPageNextLinkIdentifier) as $link){
		$nextLink = "https://" . $siteToCrawl . $link->href;
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
