<?php

error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$siteToCrawl = "www.workania.hu";
$entryPoint = "/allas/?search_anywhere=devops";
$MAX=250;
$counter=1;
$fetchPagesCount=100;

echo setXMLHeader($siteToCrawl);

function getContent($link) {
  $html=getpagebycurl($link);
  $pageContent = new simple_html_dom();
  $pageContent->load($html);
  $mainPageJobPostIdentifier = "div[id=detail]";
  $out="";
  foreach($pageContent->find($mainPageJobPostIdentifier) as $jobPostEntry) {
    $out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->plaintext))));
  }
  //die($out);
  @$pageContent->clear();
  unset($pageContent);
  $pageContent = NULL;
  return $out;
}

function getLinks($page) {
  global $siteToCrawl, $LIMIT, $CNT, $entryPoint, $z, $MAX, $counter, $fetchPagesCount;

  $mainPageJobPostIdentifier = "li[class=list-row]";
  $mainPageJobPostTitleIdentifier = "span[class=title]";
  $mainPageJobPostLinkIdentifier = "div[class=jobTitle]/a";
  
  $pubdatefilter = "div[class=extras]/div[class=postedDate]";
  $descfilter = "div[class=row]/div[class=list_tasks]";
  
  $mainPageNextLinkIdentifier = "div[class=pagingWrapper]";

  $jobPostPubDate = date("Y-m-d H:i:s");
  $jobPostAuthor = $siteToCrawl;


  $html=getpagebycurl($page);
  
//  $html = file_get_contents("test.html");
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
    
    $jobPostPubDate = date("Y-m-d 00:00:01");
    
    $desc='';

    $rssContentItem .= "<title>" . strip_tags(replaceCharsForRSS($jobPostTitle)) . "</title>\n";
    
    // get the URL of the entry
    $jobPostLink = "https://" . $siteToCrawl . urldecode(html_entity_decode ($jobPostEntry->find("h2",0)->find("a",0)->href));
    
    //echo($jobPostLink."\n");
    //continue;
    
    if (!$jobPostLink){
      continue;
    }
    
    //echo($jobPostLink."\n");
    
    for ($x = 0; $x <= 10; $x++) {
      //echo "$x redirect...\n";
      $headers = @get_headers($jobPostLink,1);
      //print_r($headers);
      if ( strpos($headers[0],'302')===false ) {break;} else {
        $loc = $headers["Location"];
        //echo("---------------------------\n");
        //print_r($loc);
        //echo("---------------------------\n");
        if (is_array($loc)) {
          $jobPostLink = end($loc);
        } else {
          $jobPostLink = $loc;
        }
        if (!strpos($jobPostLink, $siteToCrawl)) {
            $jobPostLink = "https://" . $siteToCrawl . $jobPostLink;
        }
      }
    }
    /*
    echo("---------------------------\n");
    echo($jobPostLink."\n\n");
    echo("---------------------------\n");
    */
    //$headers = @get_headers($jobPostLink,1);
    //print_r($headers);
    
    for ($y = 0; $y <= 6; $y++) {
      $extracontent=getContent($jobPostLink);
      if ($extracontent){
        $desc.="\n".$extracontent;
        break;
      } else {
        sleep($y);
        continue;
      }
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

  for ($x = 2; $x <= $fetchPagesCount; $x++) {
    $nextLink = "https://" . $siteToCrawl . $entryPoint."&page_num=".$x;
    $headers = @get_headers($nextLink,1);
    if(strpos($headers[0],'200')===false) { break; } else {
      $extracontent=getContent($jobPostLink);
      $desc.="\n".$extracontent;
      $z++;
      if(!empty($nextLink)) {
        if ($pageContent){
          @$pageContent->clear();
          unset($pageContent);
          $pageContent=NULL;
        }
        getLinks($nextLink);
      }
    }
    if ($counter > $MAX){
      return false;
    }
  }
}


getLinks("https://" . $siteToCrawl . $entryPoint);
echo "</channel>\n";
echo "</rss>\n";

?>
