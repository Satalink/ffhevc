<?php
/**
 *  input: file
 *  output: file
 *  purpose: Strip undesirable characters from filename
 * 
 */

function remove_illegal_chars($file, $options) {
  if ($options['args']['remove_illegal_chars'] && preg_match('/\'/', $file['filename'])) {
    rename($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $file['dirname'] . DIRECTORY_SEPARATOR . str_replace("'", "", ($file['filename'])) . "." . $file['extension']);
    $file = pathinfo($file['dirname'] . "/" . str_replace("'", "", ucwords($file['filename'])) . "." . $file['extension']);
  }  
  return($file);
}