<?php
/**
 *  input: file
 *  output: file
 *  purpose: Strip undesirable characters from filename
 * 
 */

function strip_illegal_chars($file, $options) {
  if (preg_match('/\'/', $file['filename'])) {
    rename($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $file['dirname'] . DIRECTORY_SEPARATOR . str_replace("'", "", ($file['filename'])) . "." . $file['extension']);
    $file = pathinfo($file['dirname'] . "/" . str_replace("'", "", ucwords($file['filename'])) . "." . $file['extension']);
  }  
  if ($options['args']['dot2space'] && preg_match('/.*\..*\([12][0-9]{3}\)/', $file['filename'])) {
    $title = explode("(", $file['filename'])[0];
    if (preg_match('/\./', $title)) {
      rename($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $file['dirname'] . DIRECTORY_SEPARATOR . str_replace(".", " ", ($file['filename'])) . "." . $file['extension']);
    }
  }
  return($file);
}