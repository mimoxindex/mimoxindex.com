<?php
error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$siteToCrawl = "www.cvonline.hu";
$entryPoint = "/informatika-it?nr_per_page=50";

function getContent($link) {
	$html=getpagebycurl($link);
	$pageContent = new simple_html_dom();
	$pageContent->load($html);
	$mainPageJobPostIdentifier = "div[id=job_desc]";
	///$mainPageJobPostIdentifier = "div[id=job_details]/div[id=job_main]";
	$out="";
	foreach($pageContent->find($mainPageJobPostIdentifier) as $jobPostEntry) {
		$f = $jobPostEntry->plaintext;
		//echo $f;
		$f = str_replace("A megfelelő működéshez  Javascript engedélyezése szükséges","",$f);
		$f = str_replace("Jelentkezés e-mail címen:","",$f);
		$f = str_replace("Munkavégzéhelye:","",$f);
		$f = str_replace("Jelentkezés","",$f);
		$f = str_replace("Állás","",$f);
		$f = str_replace("továbbküldése","",$f);
		$f = str_replace("ismerősnek","",$f);
		$f = str_replace("Nyomtatás","",$f);
		$out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($f))));
	}
	
	@$pageContent->clear();
	unset($pageContent);
	$pageContent = NULL;
	return $out;
}

echo setXMLHeader($siteToCrawl);

function getLinks($page) {
	global $siteToCrawl, $LIMIT, $CNT;

	$mainPageJobPostIdentifier = "div[class=job-columns]";
	//$mainPageJobPostTitleIdentifier = "div[class=job-job-columns]/div[class=list-title]/div[class=function-title]/h3/span[itemprop=title]";
	$mainPageJobPostTitleIdentifier = "div[class=function-title]/h3/a";
	$mainPageJobPostLinkIdentifier = "div[class=function-title]/h3/a";
	//$mainPageJobPostLinkIdentifier2 = "div[class=title]/div[class=function_salary]/h3/a";
	$mainPageNextLinkIdentifier = "li[class=arrow]/a";
	//$descriptionfilter = "div[class=job-description]";
	$pubdatefilter = "span[itemprop=datePosted]";

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

		//die(replaceCharsForRSS(sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->plaintext))))));
		//die($jobPostEntry);

		$rssContentItem = "<item>\n";
		$jobPostTitle = replaceCharsForRSS(sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->find($mainPageJobPostTitleIdentifier, 0)->plaintext)))));
		

		if (!$jobPostTitle){
			continue;
		}
		//$jobdescr = sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->find($descriptionfilter, 0)->plaintext))));
		
		//die($jobdescr);
		$jobdescr = "";

		/*
		$pubdate = replaceCharsForRSS($jobPostEntry->find($pubdatefilter, 0)->plaintext);
		*/
		
		

		//$pubdate=str_replace(".","-",$pubdate);
		//$pubdate=trim($pubdate,"-");
		//if ($pubdate){
			//$jobPostPubDate=$pubdate." 00:00:01";
		//}
		
		//check last date
		//$jobPostPubDate_i=strtotime($jobPostPubDate);
		//if ($jobPostPubDate_i < $LAD){
		//	continue;
		//}
		
		//check future
		//if ($jobPostPubDate_i > time()){
		//	$jobPostPubDate = date("Y-m-d 00:00:01");
		//}

		$jobPostPubDate = date("Y-m-d 00:00:01");
		
		$rssContentItem .= "<title>". strip_tags($jobPostTitle) . "</title>\n";
		
		// get the URL of the entry
		$lnk=$jobPostEntry->find($mainPageJobPostLinkIdentifier2, 0)->href;
		if (!$lnk){
			$lnk=$jobPostEntry->find($mainPageJobPostLinkIdentifier, 0)->href;
		}
		
		$jobPostLink = "http://" . $siteToCrawl . $lnk;
		
		if (!$jobPostLink){
			continue;
		}
		
		$headers = @get_headers($jobPostLink);
		if(strpos($headers[0],'200')===false) {} else {
			$extracontent=getContent($jobPostLink);
			$jobdescr.="\n".$extracontent;
		}
		

		//die($jobdescr);
		$rssContentItem .= "<link>" . strip_tags($jobPostLink) . "</link>\n";


		$rssContentItem .= "<pubDate>" . strip_tags($jobPostPubDate) . "</pubDate>\n";
		// get main content body of the entry
		$rssContentItem .= "<description><![CDATA[ " . str_replace("Pozícióleírás","",preg_replace('!\s+!', ' ', stripInvalidXml($jobdescr))) . " ]]></description>\n";
		// set author as "source of data"
		$rssContentItem .= "<author>" . strip_tags($jobPostAuthor) . "</author>\n";

		//echo "jobPostTitle :",$trimmedTitle,", jobPostLink: ",$jobPostLink," pubdate: ",$pubdate,"\n";
		$rssContentItem .= "</item>\n";
		
		echo $rssContentItem;
	}

	// get next URL; can be very site specific
	foreach($pageContent->find($mainPageNextLinkIdentifier) as $link){
		$nextLink = "http://" . $siteToCrawl . $link->href;
		die($nextLink);
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

getLinks("http://" . $siteToCrawl . $entryPoint);
echo "</channel>\n";
echo "</rss>\n";

?>
