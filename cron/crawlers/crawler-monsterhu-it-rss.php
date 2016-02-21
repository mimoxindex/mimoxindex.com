<?php

error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$siteToCrawl = "allasok.monster.hu";
$entryPoint = "/allas/IT-Szoftverfejleszt%C3%A9s_4";
$z=2;

echo setXMLHeader($siteToCrawl);

function getContent($link) {
	$html=getpagebycurl($link);
	$pageContent = new simple_html_dom();
	$pageContent->load($html);
	$mainPageJobPostIdentifier = "div[align=center],div[id=monsterAppliesContentHolder]";
	$out="";
	foreach($pageContent->find($mainPageJobPostIdentifier) as $jobPostEntry) {
		$out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->plaintext))));
	}
	@$pageContent->clear();
	unset($pageContent);
	$pageContent = NULL;
	return $out;
}

function getLinks($page) {
	global $siteToCrawl, $LIMIT, $CNT, $entryPoint, $z;

	$mainPageJobPostIdentifier = "tr[class=odd],tr[class=even]";
	$mainPageJobPostTitleIdentifier = "td[class=firstColumn]/label[class=skip]";
	$mainPageJobPostLinkIdentifier = "a[class=slJobTitle]";
	
	$pubdatefilter = "div[class=fnt20],div[class=fnt40]";
	$descfilter = "div[class=row]/div[class=list_tasks]";
	
	$mainPageNextLinkIdentifier = "a[class=nextLink]";

	$jobPostPubDate = date("Y-m-d H:i:s");
	$jobPostAuthor = $siteToCrawl;


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
		
		//echo $jobPostTitle."\n";
		
		if (!$jobPostTitle){
			continue;
		}
		
		$pubdate = preg_replace('!\s+!', ' ', replaceCharsForRSS($jobPostEntry->find($pubdatefilter, 0)->plaintext));
		$pubdate = strtolower($pubdate);
		
		//echo $pubdate,"\n";
		
		if (startsWith($pubdate,"ma")){
			$pubdate = str_replace("ma", "today,", $pubdate);
		} elseif(startsWith($pubdate,"meghirdetve: ma")){
			$pubdate = str_replace("meghirdetve: ma", "today,", $pubdate);
		} elseif(startsWith($pubdate,"tegnap")){
			$pubdate = str_replace("tegnap", "yesterday,", $pubdate);
		} elseif(startsWith($pubdate,"meghirdetve: 1 napja")){
			$pubdate = str_replace("meghirdetve: 1 napja", "yesterday,", $pubdate);
		} elseif(startsWith($pubdate,"1 napja")){
			$pubdate = str_replace("1 napja", "yesterday,", $pubdate);
		} elseif(startsWith($pubdate,"2 napja")){
			$pubdate = str_replace("2 napja", "day before yesterday,", $pubdate);
		} elseif(startsWith($pubdate,"meghirdetve: 2 napja")){
			$pubdate = str_replace("meghirdetve: 2 napja", "day before yesterday,", $pubdate);
		} elseif(startsWith($pubdate,"meghirdetve: 3 napja")){
			$pubdate = str_replace("meghirdetve: 3 napja", "three days ago,", $pubdate);
		}
		$pubdate = strtotime($pubdate); 
		
		//echo $pubdate."\n";
		
		if ($pubdate){
			if ($pubdate > (time()+(2 * 7 * 24 * 60 * 60))){
				$pubdate = $pubdate - (52 * 7 * 24 * 60 * 60);
			}
			$jobPostPubDate = date("Y-m-d H:i:s",$pubdate);
		}
		//echo $jobPostPubDate,"\n";
		
		//check last date
		$jobPostPubDate_i=strtotime($jobPostPubDate);
		if ($jobPostPubDate_i < $LAD){
			continue;
		}
		
		//check future
		if ($jobPostPubDate_i > time()){
			$jobPostPubDate = date("Y-m-d 00:00:01");
		}
		
		$desc = $jobPostEntry->find($descfilter, 0)->plaintext;
		//echo $desc."\n";
		
		$rssContentItem .= "<title>" . strip_tags(replaceCharsForRSS($jobPostTitle)) . "</title>\n";
		
		// get the URL of the entry
		$jobPostLink = $jobPostEntry->find($mainPageJobPostLinkIdentifier, 0)->href;
		
		if (!$jobPostLink){
			continue;
		}
		
		$headers = @get_headers($jobPostLink);
		if(strpos($headers[0],'200')===false) {} else {
			$extracontent=getContent($jobPostLink);
			$desc.="\n".$extracontent;
		}
		
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
		$nextLink = "http://" . $siteToCrawl . $entryPoint."?pg=".$z;
		$z++;
		echo $nextLink."\n";
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
