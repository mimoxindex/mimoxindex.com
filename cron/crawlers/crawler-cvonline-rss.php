<?php
error_reporting(E_ALL & ~E_NOTICE);

include('simple_html_dom.php');
include('common.php');

$siteToCrawl = "www.cvonline.hu";
$entryPoint = "/informatika-it";
$MAX=250;
$counter=1;
$fetchPagesCount=100;


function getContent($link) {
  $html=getpagebycurl($link);
  $pageContent = new simple_html_dom();
  $pageContent->load($html);
  $mainPageJobPostIdentifier = "div[id=job_desc],div[id=job_template],div[class=job_details],div[class=job-content]";
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

//die(getcontent("http://www.cvonline.hu/vezeto-fejleszto-c-net/1098361/j.html"));

echo setXMLHeader($siteToCrawl);

function getLinks($page) {
  global $siteToCrawl, $LIMIT, $CNT, $MAX, $counter, $fetchPagesCount;

  $mainPageJobPostIdentifier = "div[class=list-title]";
  //$mainPageJobPostIdentifier = "div[class=job]";
  //$mainPageJobPostTitleIdentifier = "div[class=job-job-columns]/div[class=list-title]/div[class=function-title]/h3/span[itemprop=title]";
  //$mainPageJobPostTitleIdentifier = "div[class=function-title]/h3/a";
  $mainPageJobPostTitleIdentifier = "div[class=function-title]";
  $mainPageJobPostLinkIdentifier = "div[class=function-title]/h3/a";
  //$mainPageJobPostLinkIdentifier2 = "div[class=title]/div[class=function_salary]/h3/a";
  $mainPageNextLinkIdentifier = "li[class=arrow]/a";
  //$descriptionfilter = "div[class=job-description]";
  $pubdatefilter = "span[itemprop=datePosted]";

  $jobPostPubDate = date("Y-m-d H:i:s");
  $jobPostAuthor = $siteToCrawl;

  $html=getpagebycurl($page);
  //die($html);
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

    $desc='';
    
    //die(replaceCharsForRSS(sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->plaintext))))));
    //die($jobPostEntry);

    $rssContentItem = "<item>\n";
    $jobPostTitle = replaceCharsForRSS(sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->find($mainPageJobPostTitleIdentifier, 0)->plaintext)))));
    
    $jobPostTitle = $jobPostEntry->find($mainPageJobPostTitleIdentifier, 0)->find("h3", 0)->find("a",0)->plaintext;
    $jobPostTitle = replaceCharsForRSS(sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostTitle)))));

    //echo($jobPostTitle."\n\n");
    //continue;

    if (!$jobPostTitle){
      continue;
    }
    //$jobdescr = sanitize_for_xml(trim(stripInvalidXml(html_entity_decode($jobPostEntry->find($descriptionfilter, 0)->plaintext))));
    
    //die($jobdescr);
    $jobdescr = "";

    $jobPostPubDate = date("Y-m-d 00:00:01");
    
    $rssContentItem .= "<title>". strip_tags($jobPostTitle) . "</title>\n";
    
    // get the URL of the entry
    $lnk = $jobPostEntry->find($mainPageJobPostTitleIdentifier, 0)->find("h3", 0)->find("a",0)->href;
    if (!$lnk){
      continue;
    }
    
    //echo($lnk."\n\n");
    //continue;
    
    $jobPostLink = "https://" . $siteToCrawl . $lnk;
    
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
    
    //die($jobdescr);
    $rssContentItem .= "<link>" . strip_tags(str_ireplace('&',"%26",$jobPostLink)) . "</link>\n";


    $rssContentItem .= "<pubDate>" . strip_tags($jobPostPubDate) . "</pubDate>\n";
    // get main content body of the entry
    $rssContentItem .= "<description><![CDATA[ " . str_replace("Pozícióleírás","",preg_replace('!\s+!', ' ', stripInvalidXml($desc))) . " ]]></description>\n";
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

  for ($x = 2; $x <= $fetchPagesCount; $x++) {
    $nextLink = "https://" . $siteToCrawl . $entryPoint."/page".$x;
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
