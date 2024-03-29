<?php
/**
*
*/
function rename_PlexStandards($file, $options) {
  if ($options['args']['rename']) {
    $excluded_words = array('a', 'an', 'and', 'at', 'but', 'by', 'else', 'etc', 'for', 'from', 'if', 'in', 'into', 'is', 'of', 'or', 'nor', 'on', 'to', 'that', 'the', 'then', 'when', 'with');
    $allcap_words = array("us", "usa", "HEVC", "AVC", "acc", "ac3", "eac3", "hdr", "dvd", "sdtv", "dvd-r", "hdtv", "fbi", "pd", "i", "ii", "iii", "iv", "v", "tv", "vi", "vii", "viii", "ix", "x", "xl");
    $camelcase_words = array("bluray" => "Bluray", "webrip" => "WebRip", "redux" => "Redux", "webdl" => "WebDL", 'truehd' => "TrueHD");
    $filename = [];
    $title = [];
    $specs = [];
    $firstword = true;

    //Grab {year} from Filename
    $year = null;
    preg_match_all('/\(?(19|20)\d{2}\)?/', $file['filename'], $yr, PREG_SET_ORDER);
    if (!empty($yr)) {
      $lastkey = key(array_slice($yr, -1, 1, true));
      $year = $yr[$lastkey][0];
      $year = preg_replace('/(\(|\))?/', '', $year);
      // Title should not start with a year  
      if (preg_match('/^'.$year.'/', $file['filename'])) {
        unset($year);
      }
    }
 
    // TV Show or Movie?
    if (preg_match('/\d+(e|x)\d+/i', $file['filename'])) {     
      $filename['type'] = "series";
      if (preg_match('/\[.*\]/i', $file['filename'])) {
        $filespecs = preg_split('/\[.*\]/i', $file['filename'], 0);
        preg_match_all('/\[.*\]/i', $file['filename'], $episode, PREG_PATTERN_ORDER);
        $filespecs[1] = $episode[0][0];
      }
      else {
        $filespecs = preg_split('/s?\d+(e|x)\d+\-?(e?|x?\d+)/i', $file['filename'], 0);
        preg_match_all('/s?\d+(e|x)\d+\-?(e?|x?\d+)/i', $file['filename'], $episode, PREG_PATTERN_ORDER);
        $filespecs[1] = $episode[0][0];
      }
      $filename['newtitle'] = preg_split('/(\s|\.|\-|\_)/', $filespecs[0][0]);
      $filename['year'] = isset($year) ? preg_replace('/\(|\)/', "", $year) : null;
    } 
    elseif (preg_match('/\(?(19|20)\d{2}(\.?|\-?\s?)\d{2}(\.?|\-?|\s?)\d{2}\)?/', $file['filename'])) {
      $filename['type'] = "dated";        
      $filespecs = preg_split('/\(?(19|20)\d{2}(\.?|\-?\s?)\d{2}(\.?|\-?|\s?)\d{2}\)?/', $file['filename'], 0);
      preg_match_all('/\(?(19|20)\d{2}(\.?|\-?\s?)\d{2}(\.?|\-?|\s?)\d{2}\)?/', $file['filename'], $episode, PREG_PATTERN_ORDER);
      $filespecs[1] = $episode[0][0];
      $filename['newtitle'] = $filename['year'] = isset($year) ? preg_replace('/\(|\)/', "", $year) : null;          
    }
    else {
      $filename['type'] = "movie";
      if (isset($year)) {
        $filespecs = str_replace('/(\_|\-|\./', "\s", preg_split('/\(?' . $year . '\)?/', $file['filename'], 0));
      }
    }
    
    $filename['newtitle'] = preg_split('/(\s|\.|\-|\_)/', $filespecs[0]);
    $filename['year'] = isset($year) ? preg_replace('/\(|\)/', "", $year) : null;    

    $lastkey = key(array_slice($filespecs, -1, 1, true));
    if (!empty($filespecs[$lastkey])) {
      $filename['specs'] = preg_split('/(\.|\_|\-|\s+)/', $filespecs[1]);
    }

    foreach ($filename['newtitle'] as $word) {
      $word = preg_replace('/\s+/', '', $word);
      if (empty($word)) {
        continue;
      }
      if (preg_match('/.*[A-Z].*/', $word) && !in_array($word, $allcap_words)) {
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
        } 
        elseif (array_key_exists($spec, $camelcase_words)) {        
          $specs[] = $camelcase_words["$spec"];
        } 
        else {
          $specs[] = $spec;
        }
      }
    }

    if (file_exists($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'])){
      $titlename = empty($title) ? '' : ltrim(trim(implode(' ', $title)), " ");
      if ($filename['type'] == "series") {
        if(isset($year)) {
          $titlename = trim(preg_replace('/(\(|\)|(' . $year . '))?/', '', $titlename));
        }
        $titlename .= empty($filename['year']) ? '' : " ($year)";
        $titlename .= empty($specs) ? '' : " - " . ltrim(trim(implode(' ', $specs)), " ");
      } else {
        $titlename .= empty($filename['year']) ? '' : " ($year)";
        $titlename .= empty($specs) ? '' : " - [ " . ltrim(trim(str_replace(["[","]"], '', (implode(' ', $specs ))), " ") . " ]");
      }
      if ($file['filename'] !== "$titlename") {
        rename($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $file['dirname'] . DIRECTORY_SEPARATOR . "$titlename" . "." . $file['extension']);
        $file = pathinfo($file['dirname'] . DIRECTORY_SEPARATOR . $titlename . "." . $file['extension']);
        chgrp($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $options['args']['group']);
        chmod($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $options['args']['permissions']);
        //Fix Windows CaseInsensitive filenaming issue (force delete xml on rename)
        $xml_file  = $file['dirname'] . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml";
        if (file_exists($xml_file)) {
          unlink($xml_file);
        }
      }
    }
  }
  cleanXMLDir($file['dirname'], $options, true);
  return($file);
}

function rename_byCodecs($file, $options, $info) {
  $filename    = $file['filename'];
  $resolutions = array('480p', '720p', '1080p', '2160p', ' SD', ' HD', ' UHD');
  $vcodecs     = array("h264", "h.264", "h-264", "x-264", "x.264", "x264", "264", "h265", "h.265", "h-265", "x-265", "x.265", "x265", "265", "avc", "vc1", "hevc");
  $acodecs     = array('AAC', 'EAC3', 'AC3', 'AC4', 'MP3', 'OGG', 'FLAC', 'WMA', 'ddp5.1', 'ddp7.1', 'DTS-HD', 'DTS', 'TrueHD', 'PPCM', 'DST', 'OSQ', 'DCT', );
  $camelcase_words = array("bluray" => "Bluray", "webrip" => "WebRip", "redux" => "Redux", "webdl" => "WebDL", 'truehd' => "TrueHD");
  $profiles    = array('RAW-HD', 'Remux', 'BLURAY', 'WEBDL', 'WEBRIP', 'HDTV');  // Quality Profiles

  $resolution  = get_resolution($info['video']['height']);
  $vcodec      = isset($info['video']['codec_name']) ? $info['video']['codec_name'] : $options['video']['codec_long_name'];
  $vcodec      = preg_match('/hevc/', $vcodec) ? "x265" : $vcodec;
  $acodec      = isset($info['audio']['codec_name']) ? strtoupper($info['audio']['codec_name']) : strtoupper($options['audio']['codec']);
  $profile     = strtoupper($options['video']['profile']);

  $filename_set = false;
  if (preg_match("/\([1-2]\d{3}\)$/", $filename) && $options['args']['rename']) {
      $filename = $file['filename'] . " - [ $resolution $vcodec $acodec ]";
      $filename_set = true;
  }
  if (!$filename_set) {
    foreach ($profiles as $pf) {
      if (preg_match("/$pf/i", $filename)) {
        if (array_key_exists($pf, $camelcase_words)) {        
          $filename = str_ireplace($pf, $camelcase_words["$pf"], $filename);
        } else {
          $filename = str_ireplace($pf, "$profile", "$filename");
        }
        break;
      }
    }
    foreach ($resolutions as $res) {
      if (preg_match("/$res/i", $filename)) {
        $filename = str_ireplace($res, "$resolution", $filename);
        break;
      }
    } 
    foreach ($vcodecs as $vc) {
      if (preg_match("/$vc/i", $filename)) {
        $filename = str_ireplace($vc, "$vcodec", "$filename");
        break;
      }    
    }
    foreach ($acodecs as $ac) {
      if (preg_match("/$ac/i", $filename)) {
        $filename = str_ireplace($ac, "$acodec", "$filename");
        break;
      }        
    }
  }
  rename($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $file['dirname'] . DIRECTORY_SEPARATOR . $filename . "." . $file['extension']);
  $file = pathinfo($file['dirname'] . DIRECTORY_SEPARATOR . $filename . "." . $file['extension']);
  //Fix Windows CaseInsensitive filenaming issue (force delete xml on rename)
  $xml_file  = $file['dirname'] . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml";
  if (file_exists($xml_file)) {
    unlink($xml_file);
  }
  cleanXMLDir($file['dirname'], $options, true);
  return($file);
}

function remove_illegal_chars($file, $options) {
  if ($options['args']['remove_illegal_chars'] && preg_match('/\'/', $file['filename'])) {
    rename($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $file['dirname'] . DIRECTORY_SEPARATOR . str_replace("'", "", ($file['filename'])) . "." . $file['extension']);
    $file = pathinfo($file['dirname'] . "/" . str_replace("'", "", ucwords($file['filename'])) . "." . $file['extension']);
  }  
  return($file);
}


function get_resolution($scale) {
  if (empty($scale)) { print "EMPTY\n"; return; }
  switch ($scale) {
    case $scale > 180 && $scale <= 240:
      $res = "480p";
      break;
    case $scale > 240 && $scale <= 360:
      $res = "360p";
      break;
    case $scale > 360 && $scale <= 480:
      $res = "480p";
      break;
    case $scale > 480 && $scale <= 720:
      $res = "720p";
      break;
    case $scale > 720 && $scale <= 1080:
      $res = "1080p";
      break;
    case $scale > 1080 && $scale <= 1440:
      $res = "1440p";
      break;
    case $scale > 1440 && $scale <= 2160:
      $res = "2160p";
      break;
    case $scale > 2160 && $scale <= 2880:
      $res = "2880p";
      break;
    case $scale > 2160 && $scale <= 4320:
      $res = "4320p";
      break; 
  }
  return($res);
}