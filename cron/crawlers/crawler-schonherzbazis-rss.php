<?php
error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$MAX=250;
$counter=1;

$siteToCrawl = "schonherzbazis.hu";
$entryPoint = "/allasok/osszes";

function getContent($link) {
  $html=getpagebycurl($link);
  $pageContent = new simple_html_dom();
  $pageContent->load($html);
  $mainPageJobPostIdentifier = "div[class=section]/div[class=content]/div[class=text]/div";
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

  // site specific DOM identifiers; 2. change this part
  $mainPageJobPostIdentifier = "div[class=projectad-list-item],div[class=projectad-item-short]";
  $mainPageJobPostTitleIdentifier = "div[class=title]/a";
  $mainPageJobPostLinkIdentifier = "div[class=title]/a";
  $pubdatefilter = "div[class=bottom row]/div[class=date]";
  $descfilter = "div[class=text],div[class=require-knowledge]";
  $mainPageNextLinkIdentifier = "a[class=next]";

  $jobPostPubDate = date("Y-m-d H:i:s");
  $jobPostAuthor = $siteToCrawl;


  $html=getpagebycurl($page);
  $pageContent = new simple_html_dom();
  $pageContent->load($html);

  if (!$pageContent){
    return;
  }

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
    
    $pubdate = $jobPostPubDate;
    
    //check last date
    $jobPostPubDate_i=strtotime($jobPostPubDate);
    if ($jobPostPubDate_i < $LAD){
      continue;
    }
    
    //check future
    if ($jobPostPubDate_i > time()){
      $jobPostPubDate = date("Y-m-d 00:00:01");
    }
    
    
    $rssContentItem .= "<title>" . strip_tags(replaceCharsForRSS($jobPostTitle)) . "</title>\n";
    
    // get the URL of the entry
    $jobPostLink = $jobPostEntry->find($mainPageJobPostLinkIdentifier, 0)->href;
    
    $jobPostLink="https://" . $siteToCrawl .$jobPostLink;
    
    if (!$jobPostLink){
      continue;
    }
    
    $headers = @get_headers($jobPostLink);
    if(strpos($headers[0],'200')===false) {} else {
      $desc=getContent($jobPostLink);
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
}

getLinks("https://" . $siteToCrawl . $entryPoint);
echo "</channel>\n";
echo "</rss>\n";

?>
