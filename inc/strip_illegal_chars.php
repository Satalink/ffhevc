<?php
/**
 *  input: file
 *  output: file
 *  purpose: Strip undesirable characters from filename
 * 
 */

function strip_illegal_chars($file) {
  if (preg_match('/\'/', $file['filename'])) {
    rename($file['dirname'] . "/" . $file['basename'], $file['dirname'] . "/" . str_replace("'", "", ($file['filename'])) . "." . $file['extension']);
    $file = pathinfo($file['dirname'] . "/" . str_replace("'", "", ucwords($file['filename'])) . "." . $file['extension']);
  }
  return($file);
}