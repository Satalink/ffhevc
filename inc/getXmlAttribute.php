<?php
/**
 *  input: xml object, attribute
 *  output: attribute value
 *  purpose: get the XML attribute value from an XML file
 */

function getXmlAttribute($object, $attribute) {
  return((string) $object[$attribute]);
}