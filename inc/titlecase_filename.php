<?php
/**
 * input:  file, options
 * output: file
 * function:  renames the file to Title Case Naming Convension
 */

function titlecase_filename($file, $options) {
  $excluded_words = array('a', 'an', 'and', 'at', 'but', 'by', 'else', 'etc', 'for', 'from', 'if', 'in', 'into', 'is', 'of', 'or', 'nor', 'on', 'to', 'that', 'the', 'then', 'when', 'with');
  $allcap_words = array("us", "usa", "acc", "ac3", "eac3", "hdr", "dvd", "sdtv", "dvd-r", "hdtv", "webrip", "webdl", "remux", "fbi", "pd", "i", "ii", "iii", "iv", "v", "vi", "vii", "viii", "ix", "x", "xl");
  $allcap_words_char_count = 4;
  $words = explode(' ', $file['filename']);
  $title = array();
  $firstword = true;
  foreach ($words as $word) {
    if (!ctype_upper($word) && (strlen($word) > $allcap_words_char_count)) {
      $word = strtolower($word);
    }
    if ((!in_array($word, $excluded_words) && !in_array($word, $allcap_words) && !preg_match("/[s]\d+[e]\d+/", $word)) ||
      ($firstword && !in_array($word, $allcap_words))
    ) {
      $title[] = ucwords($word);
      $firstword = false;
    }
    elseif (in_array($word, $allcap_words) || preg_match("/[s]\d+[e]\d+/", $word)) {
      $title[] = strtoupper($word);
    }
    else {
      $title[] = $word;
    }
  }

  if (file_exists($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'])){
    $titlename = implode(' ', $title);
    rename($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $file['dirname'] . DIRECTORY_SEPARATOR . $titlename . "." . $file['extension']);
    $file = pathinfo($file['dirname'] . DIRECTORY_SEPARATOR . $titlename . "." . $file['extension']);
    chgrp($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $options['group']);
    chmod($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $options['args']['permissions']);
  }
  return($file);
}