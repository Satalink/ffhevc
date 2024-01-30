<?php
/**
 * input:  file, options
 * output: file
 * function:  renames the file to Title Case Naming Convension per Plex Naming Standars
 * 
 * Plex Standards: 
 *       TV Shows: [https://support.plex.tv/articles/naming-and-organizing-your-tv-show-files/]
 *         Movies: [https://support.plex.tv/articles/naming-and-organizing-your-movie-media-files/]
 */

function titlecase_filename($file, $options) {
  if ($options['args']['rename']) {
    $excluded_words = array('a', 'an', 'and', 'at', 'but', 'by', 'else', 'etc', 'for', 'from', 'if', 'in', 'into', 'is', 'of', 'or', 'nor', 'on', 'to', 'that', 'the', 'then', 'when', 'with');
    $allcap_words = array("us", "usa", "HEVC", "AVC", "acc", "ac3", "eac3", "hdr", "dvd", "sdtv", "dvd-r", "hdtv", "fbi", "pd", "i", "ii", "iii", "iv", "v", "tv", "vi", "vii", "viii", "ix", "x", "xl");
    $camelcase_words = array("bluray" => "Bluray", "webrip" => "WebRip", "redux" => "Redux", "webdl" => "WebDL", 'truehd' => "TrueHD");
    $filename = [];
    $title = [];
    $specs = [];
    $firstword = true;

    preg_match_all('/\(?(19|20)\d{2}\)?/', $file['filename'], $yr, PREG_PATTERN_ORDER);
    if (!empty($yr[0])) {
      $lastkey = key(array_slice($yr[0], -1, 1, true));
      $year = $yr[0][$lastkey];
    }
    // TV Show or Movie?
    if (preg_match('/\d+(e|x)\d+/i', $file['filename'])) {     
      $filename['type'] = "series";
      $filespecs = preg_split('/s?\d+(e|x)\d+\-?(e?|x?\d+)/i', $file['filename'], 0);
      preg_match_all('/s?\d+(e|x)\d+\-?(e?|x?\d+)/i', $file['filename'], $episode, PREG_PATTERN_ORDER);
      $filespecs[1] = $episode[0][0];
      $filename['newtitle'] = preg_split('/(\s|\.|\-|\_)/', $filespecs[0][0]);
      $filename['year'] = isset($year) ? preg_replace('/\(|\)/', "", $year) : null;          
    } elseif (preg_match('/\(?(19|20)\d{2}(\.?|\-?\s?)\d{2}(\.?|\-?|\s?)\d{2}\)?/', $file['filename'])) {
      //TODO  Update TestCase to account for dated show names
      $filename['type'] = "dated";
      $filespecs = preg_split('/\(?(19|20)\d{2}(\.?|\-?\s?)\d{2}(\.?|\-?|\s?)\d{2}\)?/', $file['filename'], 0);
      preg_match_all('/\(?(19|20)\d{2}(\.?|\-?\s?)\d{2}(\.?|\-?|\s?)\d{2}\)?/', $file['filename'], $episode, PREG_PATTERN_ORDER);
      $filespecs[1] = $episode[0][0];
      $filename['newtitle'] = 
      $filename['year'] = isset($year) ? preg_replace('/\(|\)/', "", $year) : null;          
    } else {
      $filename['type'] = "movie";
      $filespecs = str_replace('/(\_|\-|\./', "\s", preg_split('/\(?((19|20)\d{2})\)?/', $file['filename'], 0));
    }
    $filename['newtitle'] = preg_split('/(\s|\.|\-|\_)/', $filespecs[0]);
    $filename['year'] = isset($year) ? preg_replace('/\(|\)/', "", $year) : null;    

    if (!empty($filespecs[1])) {
      $filename['specs'] = preg_split('/(\.|\-|\_|\s+)/', $filespecs[1]);
    }

    foreach ($filename['newtitle'] as $word) {
      $word = preg_replace('/\s+/', '', $word);
      if (!ctype_upper($word) && !in_array($word, $allcap_words)) {
        $word = strtolower($word);
      }
      if ((!in_array($word, $excluded_words) && !in_array($word, $allcap_words) && 
        !preg_match('/s?\d+(e|x)\d+/i', $word)) ||
        ($firstword && !in_array($word, $allcap_words))
      ) {
        $title[] = ucwords($word);
        $firstword = false;
      }
      elseif (in_array($word, $allcap_words)) {
        $title[] = strtoupper($word);
      }
      elseif ( preg_match('/s?\d+(e|x)\d+/i', $word)) {
        $title[] = '- ' . strtolower($word);
      }
      else {
        if ( !empty($word) && !preg_match('/^\s+$/', $word)){
          $title[] = $word;
        }
      }
    }

    if (!empty($filename['specs'])) {
      foreach ($filename['specs'] as $spec) { 
        $spec = strtolower($spec);      
        if (in_array($spec, $allcap_words)) {
          $specs[] = strtoupper($spec);
        } elseif (array_key_exists($spec, $camelcase_words)) {        
          $specs[] = $camelcase_words["$spec"];
        } else {
          $specs[] = $spec;
        }
      }
    }

    if (file_exists($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'])){
      $titlename = empty($title) ? '' : ltrim(trim(implode(' ', $title)), " ");
      $titlename .= empty($filename['year']) ? '' : " $year";
      if ($filename['type'] == "series") {
        $titlename .= empty($specs) ? '' : " - " . ltrim(trim(implode(' ', $specs)), " ");
      } else {
        $titlename .= empty($specs) ? '' : " - [ " . ltrim(trim(implode(' ', $specs )), " ") . " ]";
      }
      if ($file['filename'] !== "$titlename") {
        rename($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $file['dirname'] . DIRECTORY_SEPARATOR . "$titlename" . "." . $file['extension']);
        $file = pathinfo($file['dirname'] . DIRECTORY_SEPARATOR . $titlename . "." . $file['extension']);
        chgrp($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $options['group']);
        chmod($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $options['args']['permissions']);
      }
    }
  }
  return($file);
}