<?php
/**
 *   App Utility Functions
 */

function cleanXMLDir($dir, $options, $quiet = false)
{
  if (!file_exists("$dir")) return;
  // clean xml for nonexist media
  chdir($dir);
  if (file_exists("./.xml") && is_dir("./.xml")) {
    if ($dh = opendir("./.xml")) {
      while (($xmlfile = readdir($dh)) !== false) {
        if (is_dir($xmlfile)) {
          continue;
        }
        $xfile     = pathinfo($xmlfile);
        $mediafile = str_replace("'", "\'", $xfile['filename']) . "." . $options['args']['extension'];
        if (!file_exists("$mediafile") && file_exists("./.xml" . DIRECTORY_SEPARATOR . "$xmlfile")) {
          if (!$quiet)
            print ansiColor("blue") . "Cleaned XML for NonExists: $dir" . DIRECTORY_SEPARATOR . $mediafile . "\n" . ansiColor();
          unlink("./.xml" . DIRECTORY_SEPARATOR . $xmlfile);
        }
      }
    }
  }
}

function getXmlAttribute($object, $attribute)
{
  return ((string) $object[$attribute]);
}

function setXmlFormatTag($file, $key, $value, $options, $info = [])
{
  if (empty($info)) {
    $info = ffprobe($file, $options, true)[1];
  }
  $xml_file = "." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml";
  if (file_exists($xml_file) && file_exists($file['basename'])) {
    $xml = new SimpleXMLElement($xml_file, null, true);
    foreach ($xml->format->tags->tag as $tag) {
      if ($tag['key'][0] == "$key") {
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

function setXmlFormatAttribute($file, $attribute, $value = true)
{
  $xml_file = "." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml";
  $status = false;
  if (file_exists($xml_file)) {
    $xml = new SimpleXMLElement($xml_file, null, true);
    $xml->format->addAttribute("$attribute", "$value");
    $status = $xml->asXML($xml_file);
  }
  return($status);
}

function setMediaFormatTag($file, $data, $options)
{
  $xml_file = "." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . "_tag-data.xml";
  $xml      = new SimpleXMLElement("<Tags></Tags>");
  $xml->addChild("Tag");
  $xml->Tag->addChild("Targets");

  foreach ($data as $itemkey => $prop) {
    $simple = $xml->Tag->addChild("Simple");
    $name   = $simple->addChild("Name", $prop['name']);
    $string = $simple->addChild("String", $prop['value']);
  }
  $xml->asXML($xml_file);

  $output = null;
  if (`which mkvpropedit 2> /dev/null`) {
    if (file_exists($xml_file) && file_exists($file['basename'])) {
      print ansiColor("yellow") . "Setting media exlusion tag...\r" . ansiColor();
      $cmdln = "mkvpropedit '" . $file['basename'] . "' --tags global:'" . $xml_file . "'";
      chdir($file['dirname']);
      if ($options['args']['verbose']) {
        print ansiColor("green") . "$cmdln\n" . ansiColor();
      }
      exec("$cmdln", $output, $status);
      if (preg_match('/error/i', $output[0])) {
        setXmlFormatAttribute($file, "exclude");
      }
      if (file_exists($xml_file)) unlink($xml_file);
    } else {
      print ansiColor("red") . "Unable to set media exclusion tag on " . $file['basename'] . "\n";
      print "From (dir): " . getcwd() . "\n" . ansiColor();
    }
  } else {
    print ansiColor("red") . "`mkvpropedit` not found in \$PATH\nUnable to embed exclude tag in " . $file['basename'] . "\n" . ansiColor();
    setXmlFormatAttribute($file, "exclude");
  }
  return ($status);
}