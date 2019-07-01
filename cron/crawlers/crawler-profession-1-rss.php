<?php
error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');
$MAX=250;
$counter=1;

$siteToCrawl = "www.profession.hu";
$entryPoint = "/allasok/it-programozas-fejlesztes/1,10";

function getContent($link) {
  $html=getpagebycurl($link);
  $pageContent = new simple_html_dom();
  $pageContent->load($html);
  $mainPageJobPostIdentifier = "div[id=adv]";
  $out="";
  foreach($pageContent->find($mainPageJobPostIdentifier) as $jobPostEntry) {
    $out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->plaintext))));
  }
  @$pageContent->clear();
  unset($pageContent);
  $pageContent = NULL;
  return $out;
}

echo setXMLHeader($siteToCrawl);

function getLinks($page) {
  global $siteToCrawl, $LIMIT, $CNT, $counter, $MAX;

  $mainPageJobPostIdentifier = "div[class=data]";
  $mainPageJobPostTitleIdentifier = "div[class=row]/div[class=position_and_company]/h2/a/strong";
  $mainPageJobPostLinkIdentifier = "div[class=row]/div[class=position_and_company]/h2/a";
  $pubdatefilter = "div[class=bottom row]/div[class=date]";
  $descfilter = "div[class=row]/div[class=list_tasks]";
  $mainPageNextLinkIdentifier = "a[class=next]";

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
    
    $pubdate = preg_replace('!\s+!', ' ', replaceCharsForRSS($jobPostEntry->find($pubdatefilter, 0)->plaintext));
    
    $curryear=date("Y");
    if (startsWith($pubdate,"ma")){
      $pubdate = str_replace("ma", "today,", $pubdate);
    } elseif(startsWith($pubdate,"tegnap")){
      $pubdate = str_replace("tegnap", "yesterday,", $pubdate);
    }
    
    $pubdate = strtotime($pubdate);
    if ($pubdate){
      if ($pubdate > (time()+(2 * 7 * 24 * 60 * 60))){
        $pubdate = $pubdate - (52 * 7 * 24 * 60 * 60);
      }
      $jobPostPubDate = date("Y-m-d H:i:s",$pubdate);
    }
    
    //check last date
    $jobPostPubDate_i=strtotime($jobPostPubDate);
    if ($jobPostPubDate_i < $LAD){
      continue;
    }
    
    //check future
    if ($jobPostPubDate_i > time()){
      $jobPostPubDate = date("Y-m-d 00:00:01");
    }
    
    //echo $jobPostPubDate,"\n";
    
    $desc = $jobPostEntry->find($descfilter, 0)->plaintext;
    
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
    
    $rssContentItem .= "<count>" . $counter . "</count>\n";
    
    $rssContentItem .= "<link>" . strip_tags(str_ireplace('&',"%26",$jobPostLink)) . "</link>\n";
    
    // set pubDate
    $rssContentItem .= "<pubDate>" . strip_tags($jobPostPubDate) . "</pubDate>\n";
    // get main content body of the entry
    
    $rssContentItem .= "<description><![CDATA[ " . preg_replace('!\s+!', ' ', stripInvalidXml($desc)) . " ]]></description>\n";
    // set author as "source of data"
    $rssContentItem .= "<author>" . strip_tags($jobPostAuthor) . "</author>\n";
    
    $rssContentItem .= "</item>\n";
    echo $rssContentItem;
    $counter++;
    if ($counter > $MAX){
      return false;
    }
  }

  //die($pageContent);

  // get next URL; can be very site specific
  foreach($pageContent->find($mainPageNextLinkIdentifier) as $link){
    //$nextLink = "https://" . $siteToCrawl . $link->href;
    //echo "\n\n","NEXTLINK:",$nextLink,"\n\n";
    // if there's another "next" URL then crawl
    $nextLink = $link->href;
    if(!empty($nextLink)) {
      if ($pageContent){
        @$pageContent->clear();
        unset($pageContent);
        $pageContent=NULL;
      }
      getLinks($nextLink);
    }
    if ($counter > $MAX){
      return false;
    }
  }
}

//main starting point
getLinks("https://" . $siteToCrawl . $entryPoint);
echo "</channel>\n";
echo "</rss>\n";

?>
