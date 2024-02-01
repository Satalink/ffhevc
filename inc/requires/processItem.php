<?php
/**
 *  input dir, item, options, args, stats
 *  output: none
 *  purpose:  Process a media item to be probed, analyzed, encoded, renamed
 * 
 */


function processItem($dir, $item, $options, $args, $stats) {
  $file = remove_illegal_chars(pathinfo("$dir" . DIRECTORY_SEPARATOR . "$item"), $options);
  checkProcessCount($args, $options);   //Don't melt my CPU!
  
  // Exclusions
  if (
    !isset($file['extension']) ||
    !in_array(strtolower($file['extension']), $options['args']['extensions'])
  ) {
    print ansiColor("red") . $file['basename'] ." format is not configured to be supported.\n" . ansiColor();
    return($stats);
  }
  if ($options['args']['exclude']) {
    // TODO Verify this code
    list($file, $info) = ffprobe($file, $options);
    if (!empty($info)) {
      setXmlFormatAttribute($file, "exclude");
    }
    return($stats);
  }

  // Process Non-Excluded Files
  if (isset($stats['processed'])) {  
    $stats['processed']++;
  }
  // Convert original "accepted" format to Matroska (mkv) 
  // acceptible formats configured in $options['args']['extensions']
  if ($file['extension'] !== "mkv") {
    echo ansiColor("red") . $file['filename'] . "." . $file['extension'] . "\n" . ansiColor();
    echo ansiColor("blue") . "Container Convert: " . ansiColor("red") . $file['extension'] . ansiColor("blue") . " => " . ansiColor("green") . "mkv\n" . ansiColor();
    // [quiet, panic, fatal, error, warning, info, verbose, debug]
    $subs = $file['extension'] === "mp4" ? "-sn " : ""; //Remove subs from mp4 files to prevent encoding issues
    $cmdln = "ffmpeg " . 
             "-hide_banner " .
             "-v " . $options['args']['loglev'] . " " .
             "-i '". $file['filename'] . "." . $file['extension'] . "' " .
             "-c copy " .
             "$subs" .
             "-stats " .
             "-stats_period" . $options['args']['stats_period'] .
             "-y '" . $file['filename'] . "." . $options['args']['output_format'] . "'";
    if ($options['args']['verbose']) {
      print ansiColor("green") . "$cmdln\n" . ansiColor();
    }
    if (!$options['args']['test']) {
      exec("$cmdln", $output, $status);
      if($status === 255) {
        //status(255) => CTRL-C    
        exit;
      }
      if (file_exists($file['filename'] . ".mkv")) {
        $mkv_file = pathinfo("$dir" . DIRECTORY_SEPARATOR . $file['filename'] . ".mkv");
        $mtime = filemtime($file['basename']);
        unlink($file['basename']);
        $mkv_filename = $mkv_file['filename'] . ".mkv";
        touch($mkv_filename, $mtime); //retain original timestamp
        list($file, $info) = ffprobe($mkv_file, $options);            
        $options = ffanalyze($info, $options, $args, $dir, $mkv_file);
        if (empty($options)) {
          return($stats);
        }
        $stats = processItem($dir, $mkv_filename, $options, $args, $stats);
        return($stats);
      } 
    }
  }

  $curdir = getcwd();
  chdir($file['dirname']);  

# Process Item
  $options['info']['title'] = "'" . str_replace("'", "\'", $file['filename']) . "'";
  $options['info']['timestamp'] = date("Ymd.His");

  list($file, $info) = ffprobe($file, $options);
  if (empty($info)) {
    return($stats);
  }
  $options = ffanalyze($info, $options, $args, $dir, $file);
  if (empty($options)) {
    return($stats);
  }

// check the file's timestamp (Do not process if file time is too new. Still unpacking?)
  if (filemtime($file['basename']) > time()) {
    touch($file['basename'], time()); //file time is in the future (created overseas?), set it to current time.
  }
  elseif ((filemtime($file['basename']) + $options['args']['delay']) > time() && filemtime($file['basename']) <= time() && !$options['args']['force']) {
    print $file['basename'] . " modified < " . $options['args']['delay'] . " seconds ago.";
    return($stats);
  }

//Preprocess with mkvmerge (if in path)
  if (`which mkvmerge` && !$options['args']['skip'] && !$info['format']['mkvmerged'] && !$options['args']['test'] ||
      $options['args']['force'] && !$options['args']['test']) {
    if ( $options['args']['filter_foreign'] && !empty($options['args']['language'])) {
       if ( !$info['format']['mkvmerged'] ||
           $options['args']['force']
       ) {
          $mkvmerge_temp_file = "." . $file['extension'] . "." . "merge";
          $cmdln = "mkvmerge" .
          " --language 0:" . $options['args']['language'] .
          " --language 1:" . $options['args']['language'] .
          " --video-tracks " . $info['video']['index'] .
          " --audio-tracks " . $info['audio']['index'] .
          " --track-order " . "0:".$info['video']['index'].","."1:".$info['audio']['index'] .
          " --subtitle-tracks '" . $options['args']['language'] . "'" .
          " --no-attachments" .
          " --track-tags '" . $options['args']['language'] . "'" .
          " --output '" . $file['filename'] . $mkvmerge_temp_file ."' '" . $file['basename'] . "'";
        if ($options['args']['verbose']) {
          print "\n\n". ansiColor("green") . "$cmdln\n" . ansiColor();
        }
        system("$cmdln 2>&1");
        if (file_exists($file['filename'] . $mkvmerge_temp_file)) {
          $mtime = filemtime($file['basename']);
          unlink($file['basename']);
          rename($file['filename'] . $mkvmerge_temp_file, $file['filename'] . ".mkv");
          touch($file['filename'] . ".mkv", $mtime); //retain original timestamp
          list($file, $info) = ffprobe($file, $options);
          setXmlFormatAttribute($file, "mkvmerged");
          $options = ffanalyze($info, $options, $args, $dir, $file);
          if (empty($options)) {
            return($stats);
          }
        }
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

  # CONVERT MEDIA
  $cmdln = "nice -n1 ffmpeg " . 
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
  if (!$options['args']['test']) {
    print ansiColor("blue") . "HEVC Encoding: " . ansiColor("green") . $file['basename'] . ansiColor() . ", runtime=" . seconds_toTime($info['format']['duration']) . "\n";
    exec("$cmdln", $output, $status);
    if($status === 255) {
      //status(255) => CTRL-C    
      exit;
    }
  }

 #Swap Validate
  if ($options['args']['test']) {
    return($stats);
  }
  if ($options['args']['keepowner']) {
    if (file_exists($file['basename'])) {
      $options['owner'] = fileowner($file['basename']);
    }
  }
  if (file_exists($file['basename'])) {
    rename($file['basename'], $file['filename'] . ".orig." . $file['extension']);
    $fileorig= pathinfo("$dir" . DIRECTORY_SEPARATOR . $file['filename'] . ".orig." . $file['extension']);    
  }
  if (file_exists("./.xml/" . $file['filename'] . ".xml")) {
    unlink("./.xml/" . $file['filename'] . ".xml");
  }
  if (file_exists($file['filename'] . ".hevc")) {
    rename($file['filename'] . ".hevc", $file['filename'] . $options['extension']);
  }
  $file = pathinfo("$dir" . DIRECTORY_SEPARATOR . $file['filename'] . $options['extension']);
  if (file_exists($file['basename'])) {
    set_fileattr($file, $options);
    $inforig = $info;
    list($file, $info) = ffprobe($file, $options);
  }
  if (empty($info)) {
    return($stats);
  }
  if (!$options['args']['keeporiginal'] && isset($fileorig)) {
    $mtime = filemtime($fileorig['basename']);
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
    if (empty($reasons) || ($options['args']['force'])) {
      if (file_exists($file['filename'] . $options['extension'])) {
        list($file, $info) = ffprobe($file, $options);
        $file = rename_byCodecs($file, $options, $info, $info['video']['width'], $info['video']['codec_name'], $info['audio']['codec_name']);
        $file = rename_PlexStandards($file, $options, $info);
        touch($file['filename'] . $options['extension'], $mtime); //retain original timestamp
        if (isset($options['args']['destination']) && file_exists($options['args']['destination'] . DIRECTORY_SEPARATOR)) {
          //move file to destination path defined in (external_ini_file)
          print ansiColor("green") . "MOVING: " . $file['filename'] . $options['extension'] . " to " . $options['args']['destination'] . "\n" . ansiColor();
          rename($file['filename'] . $options['extension'], $options['args']['destination'] . DIRECTORY_SEPARATOR . $file['filename'] . $options['extension']);
        }
      }
      echo ansiColor("blue") . "SIZE-STAT: " . $file['filename'] . $options['extension'] . " ( " . 
        "[orig] " . ansiColor("red") . formatBytes(filesize($fileorig['basename']), 2, true) . ansiColor("blue") . " - " .
        "[new] " . ansiColor("yellow") . formatBytes($info['format']['size'], 2, true) . ansiColor('blue') . " = " . 
        "[diff] " . ansiColor("green") . formatBytes(filesize($fileorig['basename']) - ($info['format']['size']), 2, true) . ansiColor("blue") . " " .
        ")\n" . ansiColor();
      print charTimes(80, "#", "blue") . "\n";
      if (isset($stats['byteSaved']) && isset($stats['reEncoded'])) {
        $stats['byteSaved'] += (filesize($fileorig['basename']) - ($info['format']['size']));
        $stats['reEncoded']++;
      }
      if (file_exists($fileorig['basename']) && !$options['args']['keeporiginal']) {
        unlink($fileorig['basename']);
      } 
    }
    else {
      print ansiColor("red") . "Rollback: " . $file['basename'] . ansiColor() . " : ";
      print "  reason(s):";
      foreach ($reasons as $reason) {
        print ansiColor("blue") . "$reason\n" . ansiColor();
      }
      //conversion failed : Let's cleanup and exclude
      if (file_exists($file['filename'] . ".hevc")) {
        unlink($file['filename'] . ".hevc");
      }
      if (file_exists($file['basename']) && 
          file_exists($fileorig['basename']) ) {
        unlink($file['basename']);
        rename($fileorig['basename'], $file['basename']);
        $info = $inforig;
        $file = rename_byCodecs($file, $options, $info, $info['video']['width'], $info['video']['codec_name'], $info['audio']['codec_name']);
        $file = rename_PlexStandards($file, $options, $info);
        $options['args']['exclude'] = true;
        if (file_exists("./.xml/" . $fileorig['filename'] . ".xml")) {
          //Shouldn't exist, but if it does -- delete it
          unlink("./.xml/" . $fileorig['filename'] . ".xml");
        }
      }
    }
  }

  if ($options['args']['exclude']) {
    list($file, $info) = ffprobe($file, $options);
    if (empty($info)) {
      return($stats);
    }
    setXmlFormatAttribute($file, "exclude");
  }

  if ($options['args']['cooldown'] > 0) {
    print ansiColor("red") . "Cooldown period: " . $options['args']['cooldown'] . " seconds.\n" . ansiColor();
    sleep($options['args']['cooldown']);
  }

  chdir($curdir);
  return($stats);
}