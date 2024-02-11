<?php
/**
 *  input dir, item, options, args, stats
 *  output: none
 *  purpose:  Process a media item to be probed, analyzed, encoded, renamed
 * 
 */

function processItem($dir, $item, $options, $args, $stats, $info=[], $inforig=[]) {
 
  $file = pathinfo("$dir" . DIRECTORY_SEPARATOR . "$item");
  $mkvmerge_temp_ext = "." . $file['extension'] . "." . "merge";
  // Stop Detected
  if (file_exists($options['args']['stop'])) {
    return($stats);
  }

  // Exclusions
  if (
    !isset($file['extension']) || 
    !in_array(strtolower($file['extension']), $options['args']['extensions'])
     ) {
    return($stats);
  }

  // Set Exclude tag in media file
  if ($options['args']['exclude'] &&
      isset($info) && !$info['format']['exclude'] &&
      !$options['args']['override']
     ) {  
    $tag_data = [ array( "name" => "exclude", "value" => "1" )];
    $status = setMediaFormatTag($file, $tag_data);
    return($stats); 
  }

  //  Show Running Progress
  if ($args['show_progress']) {
    $stats['total_files'] = !empty($stats['total_files']) ? $stats['total_files'] : 1;
    $percent = round((( $stats['processed'] / $stats['total_files'] ) * 100 ), 0);
    if ($percent > 0) {
      if($percent != $stats['percent']) {
        $stats['percent'] = $percent;
        print "   (" . $stats['total_files'] . ") "  . $percent . " %\r";
      }
    }
  }  

  $curdir = getcwd();
  chdir($file['dirname']);  
  $stats['processed']++;

  // Process Non-Excluded Files
  $mtime = filemtime($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename']);
  $convorig = [];
  // Convert Media
  if ($file['extension'] !== $options['args']['extension']) {
    print ansiColor("red") . $file['filename'] . "." . $file['extension'] . "\n" . ansiColor();
    print ansiColor("blue") . "Format Convert: " . ansiColor("red") . $file['extension'] . ansiColor("blue") . " => " . ansiColor("green") . $options['args']['extension'] ."\n" . ansiColor();
    $cmdln = "ffmpeg " . 
             "-hide_banner " .
             "-v " . $options['args']['loglev'] . " " .
             "-i '". $file['filename'] . "." . $file['extension'] . "' " .
             "-c copy " .
             "-sn " .
             "-stats " .
             "-stats_period " . $options['args']['stats_period'] . " " .
             "-y '" . $file['filename'] . "." . $options['args']['extension'] . "'";
    if ($options['args']['verbose']) {
      print ansiColor("green") . "$cmdln\n" . ansiColor();
    }
    if (!$options['args']['test']) {
      exec("$cmdln", $output, $status);
      if ($status == 255) {
        //status(255) => CTRL-C
        stop($options, time());
        return($stats);
      }
      if (file_exists($file['filename'] . "." . $options['args']['extension'])) { 
        $converted_file = pathinfo("$dir" . DIRECTORY_SEPARATOR . $file['filename'] . "." . $options['args']['extension']);
        $convorig = $file;
        rename($file['basename'], $file['filename'] . ".orig." . $file['extension']);
        $file = pathinfo($converted_file['filename'] . "." . $options['args']['extension']);
        touch($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $mtime); //retain original timestamp
      }
    } 
  }

# Process Item
  $options['info']['title'] = "'" . str_replace("'", "\'", $file['filename']) . "'";
  $options['info']['timestamp'] = date("Ymd.His");
  $file = remove_illegal_chars($file, $options);
  list($file, $info) = ffprobe($file, $options);

  if (empty($info) || file_exists($options['args']['stop'])) {
    return($stats);
  }
  if (!empty($info['format']['exclude']) && !$options['args']['override']) {
    return($stats);
  }
  $options = ffanalyze($info, $options, $args, $dir, $file);
  if (empty($options) || file_exists($options['args']['stop'])) {
    return($stats);
  }

  // check the file's timestamp (Do not process if file time is too new. Still unpacking?)
  if ($mtime > time()) {
    touch($file['basename'], time()); //file time is in the future (created overseas?), set it to current time.
  }

//Preprocess with mkvmerge (if in path)
  if (`which mkvmerge 2> /dev/null` && !$options['args']['nomkvmerge']) {
       if ( !$info['format']['mkvmerged']  && !file_exists($options['args']['stop']) && !$options['args']['test']) {
          $cmdln = "mkvmerge";
          if (!empty($options['args']['language'])) {
            $cmdln .= 
            " --language 0:" . $options['args']['language'] .
            " --language 1:" . $options['args']['language'];
          }
          $cmdln .=
          " --video-tracks " . $info['video']['index'] .
          " --audio-tracks " . $info['audio']['index'] .
          " --track-order " . "0:".$info['video']['index'].","."1:".$info['audio']['index'] .
          " --no-attachments";
          if ( $options['args']['filter_foreign'] && !empty($options['args']['language'])) {
            $cmdln .= 
            " --subtitle-tracks '" . $options['args']['language'] . "'" .
            " --track-tags '" . $options['args']['language'] . "'";
          }
          $cmdln .= " --output '" . $file['filename'] . $mkvmerge_temp_ext ."' '" . $file['basename'] . "'";
        if ($options['args']['verbose'] || file_exists($options['args']['stop'])) {
          print "\n\n". ansiColor("green") . "$cmdln\n" . ansiColor();
        }
        system("$cmdln 2>&1", $status);
        if ($status == 255) {
          //status(255) => CTRL-C    
          stop($options, time());
          return($stats);
        }

        if (file_exists($file['filename'] . $mkvmerge_temp_ext ) && !file_exists($options['args']['stop'])) {
          rename($file['basename'], $file['filename'] . ".orig." . $file['extension']); 
          rename($file['filename'] . $mkvmerge_temp_ext, $file['filename'] . "." . $options['args']['extension']);
          touch($file['filename'] . "." . $options['args']['extension'], $mtime); //retain original timestamp
          list($file, $info) = ffprobe($file, $options, true);
          $tag_data = [ array("name" => "mkvmerged", "value" => "1") ];
          $status = setMediaFormatTag($file, $tag_data);
          $options = ffanalyze($info, $options, $args, $dir, $file);
          $options['args']['mkvmerged'] = true;
          if (empty($options)) {
            return($stats);
          }
        }
        if(file_exists($options['args']['stop'])) {
          exit;
        }
      }
    }
  

  if (!isset($options['args']['video'])) {
    $options['args']['video'] = "-vcodec copy";
  }

  if (preg_match('/p10/', $options['video']['pix_fmt'])) {
    $options['profile'] = "main10";
  } else {
    $options['profile'] = "main";
  }

  if ($info['video']['fps'] > $options['video']['fps']) {
    $fps_option = " -r " . $options['video']['fps'];
  }
  else {
    $options['video']['fps'] = $info['video']['fps'];
    $fps_option = "";
  }  

  if ($info['video']['hdr']) {
    $resolution = isset($options['video']['scale']) ? (int)$options['video']['scale'] : (int)$info['video']['height'];
    switch (true) {
      case $resolution <= 480:
        $ridx = 0;
        break;
      case $resolution <= 1080:
        $ridx = 1;
        break;
      default:
        $ridx = 2;
        break;        
     }
    $options['args']['video'] .=
      " -x265-params " . 
          "colorprim=" . $options['video']['hdr']['color_primary'][$ridx] .
          ":transfer=" . $options['video']['hdr']['color_transfer'][$ridx] .
          ":colormatrix=" . $options['video']['hdr']['color_space'][$ridx] .
      " -pix_fmt " . $options['video']['hdr']['pix_fmt'][$ridx] .
      " -vb " . $options['video']['vps'] .
      " -qmin " . $options['video']['vmin'] .
      " -qmax " . $options['video']['vmax'] .
      $fps_option;
    $options['args']['meta'] = 
      " -metadata:s:v:0 bit_rate=" . $options['video']['vps'] .
      " -metadata:s:v:0 bps=" . $options['video']['bps'];
  } else {
    $options['args']['video'] .= " -pix_fmt " . $info['video']['pix_fmt'];
  }
  if ($options['args']['test']) {
    $options['args']['meta'] = '';
  }
  # ENCODE MEDIA
  $cmdln = "nice --adjustment=". $args['priority'] ." ffmpeg " . 
    "-hide_banner " . 
    "-v " . $options['args']['loglev'] . " " .
    "-i \"" . $file['basename'] . "\" " .
    "-threads " . $options['args']['threads'] . " " .
    "-profile:v " . $options['profile'] . " " .
    "-f " . $options['format'] . " " .
    $options['args']['video'] . " " .
    $options['args']['audio'] . " " .
    $options['args']['subs'] . " " .
    $options['args']['map'] . " " .
    $options['args']['meta'] . " " .
    "-stats " .
    "-stats_period 2 " .
    "-y \"" . $file['filename'] . ".hevc\"";
  if ($options['args']['verbose']) {
    print "\n\n" . ansiColor("green") . "$cmdln\n\n" . ansiColor();
  }
  if (!$options['args']['test'] && !file_exists($options['args']['stop'])) {
    print ansiColor("blue") . "HEVC Encoding: " . ansiColor("green") . $file['basename'] . ansiColor("yellow") . "\n";
    print charTimes(38, " ") . "run time=" . seconds_toTime($info['format']['duration']) . ansiColor() . "\n";
    exec("$cmdln", $output, $status);
    if($status == 255) {
      //status(255) => CTRL-C 
      stop($options, time());
      return($stats);
    }
  }

 #Swap Validate
  if ($options['args']['test'] || (file_exists($options['args']['stop']) && filesize($options['args']['stop']))) {
    return($stats);
  }
  set_fileattrs($file, $options);
  if (file_exists($file['filename'] . ".hevc")) {
    if (file_exists($file['basename'])) {
      if (!file_exists($file['filename'] . ".orig." . $file['extension'])) {
        rename($file['basename'], $file['filename'] . ".orig." . $file['extension']);
      }
      $fileorig= pathinfo($file['dirname'] . DIRECTORY_SEPARATOR . $file['filename'] . ".orig." . $file['extension']);    
    }
    if (file_exists("." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $fileorig['filename'] . ".xml")) {
      unlink("." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $fileorig['filename'] . ".xml");
    }  
    rename($file['filename'] . ".hevc", $file['filename'] . "." . $options['args']['extension']);  
    $file = pathinfo("$dir" . DIRECTORY_SEPARATOR . $file['filename'] . "." . $options['args']['extension']);
    if (file_exists($file['basename'])) {
      set_fileattrs($file, $options);
      $inforig = $info;
      list($file, $info) = ffprobe($file, $options, true);
    }
  }
  if (empty($info)) {
    return($stats);
  }
  $reasons = array();
  if ($info['format']['probe_score'] < 100) {
    $reasons[] = "probe_score = " . $info['format']['probe_score'];
  }
  if (isset($inforig['video']) && !isset($info['video'])) {
    $reasons[] = "video stream is missing";
  }
  if (isset($inforig['audio']) && !isset($info['audio'])) {
    $reasons[] = "audio stream is missing";
  }
  if (
    isset($inforig['format']['size']) &&
    isset($options['video']['filesize_tollerance']) &&
    isset($info['format']['size']) &&
    (int) ($inforig['format']['size'] * $options['video']['filesize_tollerance']) < ((int) $info['format']['size'])
    ){
    $reasons[] = "original filesize is smaller by (" . formatBytes($info['format']['size'] - filesize($fileorig['basename']), 0, false) . ")";
  }
  if (empty($reasons) || ($options['args']['override'])) {
    if (file_exists($file['basename'] . $options['args']['extension'])) {
      list($file, $info) = ffprobe($file, $options, true);
      $file = rename_byCodecs($file, $options, $info);
      $file = rename_PlexStandards($file, $options);
      touch($file['filename'] . "." . $options['args']['extension'], $mtime); //retain original timestamp
      if (isset($options['args']['destination']) && file_exists($options['args']['destination'] . DIRECTORY_SEPARATOR)) {
        //move file to destination path defined in (external_ini_file)
        print ansiColor("green") . "MOVING: " . $file['filename'] . "." . $options['args']['extension'] . " to " . $options['args']['destination'] . "\n" . ansiColor();
        rename($file['filename'] . "." . $options['args']['extension'], $options['args']['destination'] . DIRECTORY_SEPARATOR . $file['filename'] . "." . $options['args']['extension']);
      }
    }

    print ansiColor("blue") . "SIZE-STAT: " . $file['basename'] . " ( " . 
      "[orig] " . ansiColor("red") . formatBytes($inforig['format']['size'], 2, true) . ansiColor("blue") . " - " .
      "[new] " . ansiColor("yellow") . formatBytes($info['format']['size'], 2, true) . ansiColor('blue') . " = " . 
      "[diff] " . ansiColor("green") . formatBytes(($inforig['format']['size'] - $info['format']['size']), 2, true) . ansiColor("blue") . " " .
      ")\n" . ansiColor();
    print charTimes(80, "#", "blue") . "\n\n";
    if (isset($fileorig) && isset($stats['byteSaved']) && isset($stats['reEncoded'])) {
      $stats['byteSaved'] += (filesize($fileorig['basename']) - ($info['format']['size']));
      $stats['reEncoded']++;
    }
    if (isset($fileorig) && file_exists($fileorig['basename']) && !$options['args']['keeporiginal']) {
      unlink($fileorig['basename']);
    }
    if (!empty($convorig) && file_exists($convorig['basename'])) {
      unlink($convorig['basename']);
    }
    $file = rename_byCodecs($file, $options, $info);
    $file = rename_PlexStandards($file, $options);
  }
  else {
    print ansiColor("red") . "Rollback: " . $file['basename'] . ansiColor() . "\n";
    print "  reason(s):\n";
    foreach ($reasons as $reason) {
      print ansiColor("red") . "    $reason\n" . ansiColor();
    }
    //conversion failed : Let's cleanup and exclude
    if (file_exists($file['filename'] . ".hevc")) {
      unlink($file['filename'] . ".hevc");
    }
    if (file_exists($file['basename']) && file_exists($fileorig['basename']) ) {
      unlink($file['basename']);
      rename($fileorig['basename'], $file['basename']);
      $info = ffprobe($file, $options, true)[1];
      $file = rename_byCodecs($file, $options, $info);
      $file = rename_PlexStandards($file, $options);
      $options['args']['exclude'] = true;
      print charTimes(80, "#", "blue") . "\n\n";
    }
  }

  $tags_data = [];  $tag_data = [];
  if (!empty($options['args']['exclude'])) $tags_data['exclude'] = $options['args']['exclude'];
  if (!empty($options['args']['mkvmerged'])) $tags_data['mkvmerged'] = $options['args']['mkvmerged'];
  if (!empty($options['args']['audioboost'])) $tags_data['audioboost'] = $options['args']['audioboost'];

  // Set ffHEVC Media Format Tags 
  if (!empty($info) && !$options['args']['override']) {
    if (file_exists($file['basename'])) {
      foreach ($tags_data as $tag => $tag_val) {
        if(!empty($tag_val)) {
          $tag_data[] = array("name" => "$tag", "value" => "$tag_val");
        }
      }
      setMediaFormatTag($file, $tag_data);
      ffprobe($file, $options, true);
    }
  }
  
  if ($options['args']['cooldown'] > 0) {
    print ansiColor("red") . "Cooldown period: " . $options['args']['cooldown'] . " seconds.\n" . ansiColor();
    sleep($options['args']['cooldown']);
  }

  chdir($curdir);
  return($stats);
}

