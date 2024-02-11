<?php
/**
 *   App Utility Functions
 */

function cleanXMLDir($dir, $options, $quiet=false) {
  // clean xml for nonexist media
  global $cleaned;
  if (!in_array($dir, $cleaned)) {
    chdir($dir);
    if (is_dir("./.xml")) {
      if ($dh = opendir("./.xml")) {
        while (($xmlfile = readdir($dh)) !== false) {
          if (is_dir($xmlfile)) {
            continue;
          }
          $xfile = pathinfo($xmlfile);
          $mediafile = str_replace("'", "\'", $xfile['filename']) . "." . $options['args']['extension'];
          if (!empty($xmlfile) && !file_exists("$mediafile") && file_exists("./.xml" . DIRECTORY_SEPARATOR . "$xmlfile")) {
            if(!$quiet) print ansiColor("blue") . "Cleaned XML for NonExists: $dir" . DIRECTORY_SEPARATOR . $mediafile . "\n" . ansiColor();
            unlink("./.xml" . DIRECTORY_SEPARATOR . $xmlfile);
          }
        }
      }
      array_push($cleaned, $dir);
    }
  }
}

function getXmlAttribute($object, $attribute) {
  return((string) $object[$attribute]);
}  

function setXmlFormatTag($file, $key, $value, $options, $info=[]){
  if(empty($info)) {
    $info = ffprobe($file, $options, true)[1];      
  }
  $xml_file = "." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml";
  if (file_exists($xml_file) && file_exists($file['basename'])) {
    $xml = new SimpleXMLElement($xml_file, null, true);
    foreach ($xml->format->tags->tag as $tag) {
      if ( $tag['key'][0] == "$key" ) {
        $exists = 1;
      }
    }
    if (!isset($exists)) {
      $tag = $xml->format->tags->addChild("tag");
      $tag->addAttribute("key", "$key");
      $tag->addAttribute("value", "$value");
      $xml->asXML($xml_file);
    }
  }
}

function formatXML($xml) {
  $module = "xml";
    if (moduleCheck($module)) {
      $dom = new DOMDocument($xml);
      $dom->preserveWhiteSpace = true;
      $dom->formatOutput = true;
      $xml = $dom->saveXML();
    }
    return($xml);
}


function setXmlFormatAttribute($file, $attribute, $value=true) {
  $xml_file = "." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml";
  if (file_exists($xml_file)) {
    $xml = new SimpleXMLElement($xml_file, null, true);
    $xml->format->addAttribute("$attribute", $value);
    $xml->asXML($xml_file);
  }
}

function setMediaFormatTag($file, $data) {
  $xml_file = "." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . "_tag-data.xml";
  $xml = new SimpleXMLElement("<Tags></Tags>");
  $xml->addChild("Tag");
  $xml->Tag->addChild("Targets");
  
  foreach ($data as $itemkey => $prop) {
    $simple = $xml->Tag->addChild("Simple");
    $name = $simple->addChild("Name", $prop['name']);
    $string = $simple->addChild("String", $prop['value']);
  }
  $xml->asXML($xml_file);

  $output = null;
  if (file_exists($xml_file)) {    
    $cmdln = "mkvpropedit '" . $file['basename'] . "' --tags global:'" . $xml_file . "'";    
    exec("$cmdln", $output, $status);
    if ($status) {
      print_r($output);
    }
    if (file_exists($xml_file)) unlink($xml_file);
  } 
  else {
    print ansiColor("red") ."`mkvpropedit` not found in \$PATH\nUnable to set exclude tag in " . $file['basename'] . "\n" . ansiColor();
  }
  return($status);
}