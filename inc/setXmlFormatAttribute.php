<?php
/**
 *  input: file, attribute -- optional value
 *  output:  none
 *  purpose:  Set an attribute in XML FFPROBE results file
 */

function setXmlFormatAttribute($file, $attribute, $value=true) {
  $xml_file = "./.xml/" . $file['filename'] . ".xml";
  if (file_exists($xml_file)) {
    $xml = new SimpleXMLElement($xml_file, null, true);
    $xml->format->addAttribute("$attribute", $value);
    $xml->asXML($xml_file);
  }
}