<?php
//error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$MAX=250;
$counter=1;

$siteToCrawl = "jobline.hu";
$entryPoint = "/allasok/it_telekommunikacio?s=50";


function getContent($link) {
//  echo "getcontent:",$link,"\n";

  $html=getpagebycurl($link);
  $pageContent = new simple_html_dom();
  $pageContent->load($html);
//  echo $html;
//<section itemprop="skills"
//  $descriptionfilter = "section[class=adv]/section[itemprop=skills]";
  $descriptionfilter = "section[itemprop=PositionInfo]";
  $descriptionfilter3 = "ul";
  $descriptionfilter4 = "div[class=m-modular_content]";
    $out="";
    foreach($pageContent->find($descriptionfilter) as $jobPostEntry) {
        $f = $jobPostEntry->plaintext;
        //echo $f;
        $out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($f))));
    }
  if (empty($out) || count($out) < 50){
      foreach($pageContent->find($descriptionfilter3) as $jobPostEntry) {
          $f = $jobPostEntry->plaintext;
          $out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($f))));
      }
  }
  if (empty($out) || count($out) < 50){
      foreach($pageContent->find($descriptionfilter4) as $jobPostEntry) {
          $f = $jobPostEntry->plaintext;
          $out .= sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($f))));
      }
  }
  @$pageContent->clear();
  unset($pageContent);
  $pageContent = NULL;
  $html = NULL;
  return $out;
}

//die(getContent("https://jobline.hu/allas/business_analyst_csoportvezeto/ZL-0744"));

echo setXMLHeader($siteToCrawl);

function getLinks($page) {
  global $siteToCrawl, $LIMIT, $CNT, $counter, $MAX;

  $mainPageJobPostIdentifier = "article[class=m-job_item]";
  //$mainPageJobPostIdentifier = "h2[class=job-title]";
  $mainPageJobPostTitleIdentifier = "h2[class=job-title]";
  $mainPageJobPostLinkIdentifier = "span[href]";
  $mainPageNextLinkIdentifier = "a[class=next]";
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

    echo($jobPostEntry."\n");
    continue;

    $rssContentItem = "<item>\n";
    $jobPostTitle = replaceCharsForRSS(sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->find($mainPageJobPostTitleIdentifier, 0)->plaintext)))));
    
    
    //var_dump($jobPostEntry);
    //echo $jobPostTitle.$CNT."\n";
    //echo $jobPostTitle."\n";
    //echo "-----------"."\n";
    
    
    if (!$jobPostTitle){
      continue;
    }
    
      

    /*
    $pubdate = trim(str_replace(" ", "", str_replace(".","-",replaceCharsForRSS($jobPostEntry->find($pubdatefilter, 0)->plaintext))),"-");

    die($jobPostTitle);   
    
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

    */
    $jobPostPubDate = date("Y-m-d 00:00:01");

    $rssContentItem .= "<title>". strip_tags($jobPostTitle) . "</title>\n";
    // get the URL of the entry
    //$lnk=$jobPostEntry->find($mainPageJobPostLinkIdentifier, 0)->href;
    $lnk = $jobPostEntry->find($mainPageJobPostTitleIdentifier."/a", 0)->href;
    

    $jobPostLink = "https://" . $siteToCrawl . $lnk;
    

    //echo $lnk."\n";
    //get extra info
    if (!empty($lnk)){
      $headers = @get_headers($jobPostLink);
      //echo $jobPostLink."\n";
      //continue;
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
    
    $rssContentItem .= "<count>" . $counter . "</count>\n";
    
    $rssContentItem .= "<link>" . strip_tags(str_ireplace('&',"%26",$jobPostLink)) . "</link>\n";

    $rssContentItem .= "<pubDate>" . strip_tags($jobPostPubDate) . "</pubDate>\n";
    // get main content body of the entry
    $rssContentItem .= "<description><![CDATA[ " . str_replace("Elvárások","",str_replace("Requirements","",preg_replace('!\s+!', ' ', stripInvalidXml($jobdescr))) ). " ]]></description>\n";
    // set author as "source of data"
    $rssContentItem .= "<author>" . strip_tags($jobPostAuthor) . "</author>\n";

    //echo "jobPostTitle :",$trimmedTitle,", jobPostLink: ",$jobPostLink," pubdate: ",$pubdate,"\n";
    $rssContentItem .= "</item>\n";
    echo $rssContentItem;
    $counter++;
    if ($counter > $MAX){
      return false;
    }
  }

  // get next URL; can be very site specific
  foreach($pageContent->find($mainPageNextLinkIdentifier) as $link){
    $nextLink = "https://" . $siteToCrawl . $link->href;
    if(!empty($nextLink)) {
      if ($pageContent){
        @$pageContent->clear();
        unset($pageContent);
        $pageContent=NULL;
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
