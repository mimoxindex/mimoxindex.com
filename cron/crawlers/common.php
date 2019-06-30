<?php
//maximum links from sites:
$LIMIT=1000;
$CNT=0;
  
function setXMLHeader($siteToCrawl){
  $rssContentHeader  = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
  $rssContentHeader .= "<rss version=\"2.0\" xmlns:content=\"http://purl.org/rss/1.0/modules/content/\" xmlns:wfw=\"http://wellformedweb.org/CommentAPI/\">\n";
  $rssContentHeader .= "<channel>\n";
  $rssContentHeader .= "<title>" . $siteToCrawl . "</title>\n";
  $rssContentHeader .= "<link>http://" . $siteToCrawl . "</link>\n";
  $rssContentHeader .= "<description><![CDATA[" . $siteToCrawl . " feed]]></description>\n";
  $rssContentHeader .= "<language>hu</language>\n";
  $rssContentHeader .= "<pubDate>" . date(DATE_RSS, time()) . "</pubDate>\n";
  return $rssContentHeader;
}

function replaceCharsForRSS($in=""){
  $out = preg_replace('!\s+!', ' ', $in);
  $out = str_replace('&', ' and ', $out);
  $out = trim($out, ' ');
  return $out;
}
function startsWith($haystack, $needle)
{
  return $needle === "" || strpos($haystack, $needle) === 0;
}

function endsWith($haystack, $needle)
{
  return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

function utf8encode($str) {
  $tr1=array(chr(0xc5).chr(0x91),chr(0xc5).chr(0xb1),chr(0xc5).chr(0x90),chr(0xc5).chr(0xb0));
  $tr2=array('::oobetu::','::uubetu::','::Oobetu::','::Uubetu::');
  $tr3=array('õ','û','Õ','Û');
  $str = str_replace($tr3,$tr2, $str);
  $str = utf8_encode($str);
  $str = str_replace($tr2,$tr1, $str);
  return($str);
}
function sanitize_for_xml($v) {
  // Strip invalid UTF-8 byte sequences - this part may not be strictly necessary, could be separated to another function
  $v = mb_convert_encoding(mb_convert_encoding($v, 'UTF-16', 'UTF-8'), 'UTF-8', 'UTF-16');
        
  // Remove various characters not allowed in XML
  /////$v = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '�', $v);

  return $v;
}

function stripInvalidXml($value) {
  $ret = "";
  $current;
  if (empty($value)) 
  {
    return $ret;
  }

  $length = strlen($value);
  for ($i=0; $i < $length; $i++)
  {
    $current = ord($value{$i});
    if (($current == 0x9) ||
      ($current == 0xA) ||
      ($current == 0xD) ||
      (($current >= 0x20) && ($current <= 0xD7FF)) ||
      (($current >= 0xE000) && ($current <= 0xFFFD)) ||
      (($current >= 0x10000) && ($current <= 0x10FFFF)))
    {
      $ret .= chr($current);
    }
    else
    {
      $ret .= " ";
    }
  }
  return strip_tags($ret);
}

function lastAcceptDate(){
  //3months
  $ld=(time()-(3*30*24*60*60));
  return $ld;
}


function getpagebycurl($url){
  $url=htmlspecialchars_decode($url);
  $options = array(
      CURLOPT_RETURNTRANSFER => true,     // return web page
      CURLOPT_HEADER         => false,    // don't return headers
      CURLOPT_FOLLOWLOCATION => true,     // follow redirects
      CURLOPT_ENCODING       => "",       // handle all encodings
      CURLOPT_COOKIESESSION  => true,
      CURLOPT_USERAGENT      => "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:65.0) Gecko/20100101 Firefox/65.0", // who am i
      CURLOPT_AUTOREFERER    => true,     // set referer on redirect
      CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
      CURLOPT_TIMEOUT        => 120,      // timeout on response
      CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
      CURLOPT_SSL_VERIFYPEER => false     // Disabled SSL Cert checks
    );

  $ch = curl_init($url);
  curl_setopt_array($ch, $options);
/*  
  curl_setopt($_curl, CURLOPT_SSL_VERIFYHOST, 1);
  curl_setopt($_curl, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($_curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($_curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:29.0) Gecko/20100101 Firefox/29.0');
  curl_setopt($_curl, CURLOPT_URL, $url);
*/
  $content = curl_exec($ch);
//  $err     = curl_errno( $ch );
//  $errmsg  = curl_error( $ch );
//  $header  = curl_getinfo( $ch );
  curl_close( $ch );

  return $content;
}
?>
