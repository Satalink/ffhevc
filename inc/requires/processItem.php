<?php
/**
 *  input dir, item, options, args, stats
 *  output: none
 *  purpose:  Process a media item to be probed, analyzed, encoded, renamed
 * 
 */

function processItem($dir, $item, $options, $args, $stats, $info=[]) {
 
  $file = pathinfo("$dir" . DIRECTORY_SEPARATOR . "$item");  
  // Stop Detected
  if (file_exists($args['stop'])) {
    return($stats);
  }
  // Exclusions
  if (
    !isset($file['extension']) || 
    !in_array(strtolower($file['extension']), $options['args']['extensions'])
     ) {
    return($stats);
  }

  $stats['processed']++;

  if ($options['args']['exclude']) {  // --exclude flag
    setXmlExclude($file, $options);
    return($stats);
  }

  //  Show Running Progress
  if ($args['show_progress']) {
    $stats['total_files'] = !empty($stats['total_files']) ? $stats['total_files'] : 1;
    $percent = round((( $stats['processed'] / $stats['total_files'] ) * 100 ), 0);
    if ($percent > 0) {
      if($percent != $stats['percent']) {
        $stats['percent'] = $percent;
        print "   " . $percent . " %\r";
      }
    }
  }  


  // Process Non-Excluded Files
  //
  // Convert original "accepted" format to $options['args']['extension'] ? Matroska (mkv) 
  // acceptible formats configured in $options['args']['extensions']
  if ($file['extension'] !== $options['args']['extension']) {
    print ansiColor("red") . $file['filename'] . "." . $file['extension'] . "\n" . ansiColor();
    print ansiColor("blue") . "Container Convert: " . ansiColor("red") . $file['extension'] . ansiColor("blue") . " => " . ansiColor("green") . $options['args']['extension'] ."\n" . ansiColor();
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
             "-y '" . $file['filename'] . "." . $options['args']['extension'] . "'";
    if ($options['args']['verbose']) {
      print ansiColor("green") . "$cmdln\n" . ansiColor();
    }
    if (!$options['args']['test']) {
      exec("$cmdln", $output, $status);
      if ($status == 255) {
        //status(255) => CTRL-C
        stop($args, time());
        return($stats);
      }
      if (file_exists($file['filename'] . "." . $options['args']['extension']) && !file_exists($args['stop'])) {
        $mkv_file = pathinfo("$dir" . DIRECTORY_SEPARATOR . $file['filename'] . "." . $options['args']['extension']);
        $mtime = filemtime($file['basename']);
        unlink($file['basename']);
        $mkv_filename = $mkv_file['filename'] . "." . $options['args']['extension'];
        touch($mkv_filename, $mtime); //retain original timestamp
        list($file, $info) = ffprobe($mkv_file, $options);
        $options = ffanalyze($info, $options, $args, $dir, $mkv_file);
        if (empty($options)) {
          return($stats);
        }
        $stats = processItem($dir, $mkv_filename, $options, $args, $stats, $info);
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
  if ($info['format']['exclude']) {
    return($stats);
  }
  $file = remove_illegal_chars($file, $options);
  if (empty($info) || file_exists($args['stop'])) {
    return($stats);
  }
  $options = ffanalyze($info, $options, $args, $dir, $file);
  if (empty($options) || file_exists($args['stop'])) {
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
       if (( !$info['format']['mkvmerged'] || $options['args']['force'] ) && !file_exists($args['stop'])) {
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
        if ($options['args']['verbose'] || file_exists($args['stop'])) {
          print "\n\n". ansiColor("green") . "$cmdln\n" . ansiColor();
        }
        system("$cmdln 2>&1", $status);
        if ($status == 255) {
          //status(255) => CTRL-C    
          stop($args, time());
          return($stats);
        }

        if (file_exists($file['filename'] . $mkvmerge_temp_file ) && !file_exists($args['stop'])) {
          $mtime = filemtime($file['basename']);
          unlink($file['basename']);
          rename($file['filename'] . $mkvmerge_temp_file, $file['filename'] . "." . $options['args']['extension']);
          touch($file['filename'] . "." . $options['args']['extension'], $mtime); //retain original timestamp
          list($file, $info) = ffprobe($file, $options);
          setXmlFormatAttribute($file, "mkvmerged");
          $options = ffanalyze($info, $options, $args, $dir, $file);
          if (empty($options)) {
            return($stats);
          }
        }
        if(file_exists($args['stop'])) {
          exit;
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
  if (!$options['args']['test'] && !file_exists($args['stop'])) {
    print ansiColor("blue") . "HEVC Encoding: " . ansiColor("green") . $file['basename'] . ansiColor("yellow") . ", runtime=" . seconds_toTime($info['format']['duration']) . ansiColor() . "\n";
    exec("$cmdln", $output, $status);
    if($status === 255) {
      if ($status == 255) {
        //status(255) => CTRL-C 
        stop($args, time());
        return($stats);
      }
    }
  }

 #Swap Validate
  if ($options['args']['test'] || file_exists($args['stop'])) {
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
  if (file_exists("./.xml" . DIRECTORY_SEPARATOR . $fileorig['filename'] . ".xml")) {
    unlink("./.xml" . DIRECTORY_SEPARATOR . $fileorig['filename'] . ".xml");
  }
  if (file_exists($file['filename'] . ".hevc")) {
    rename($file['filename'] . ".hevc", $file['filename'] . "." . $options['args']['extension']);
  }
  $file = pathinfo("$dir" . DIRECTORY_SEPARATOR . $file['filename'] . "." . $options['args']['extension']);
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
      if (file_exists($file['filename'] . $options['args']['extension'])) {
        list($file, $info) = ffprobe($file, $options);
        $file = rename_byCodecs($file, $options, $info);
        $file = rename_PlexStandards($file, $options, $info);
        touch($file['filename'] . "." . $options['args']['extension'], $mtime); //retain original timestamp
        if (isset($options['args']['destination']) && file_exists($options['args']['destination'] . DIRECTORY_SEPARATOR)) {
          //move file to destination path defined in (external_ini_file)
          print ansiColor("green") . "MOVING: " . $file['filename'] . "." . $options['args']['extension'] . " to " . $options['args']['destination'] . "\n" . ansiColor();
          rename($file['filename'] . "." . $options['args']['extension'], $options['args']['destination'] . DIRECTORY_SEPARATOR . $file['filename'] . "." . $options['args']['extension']);
        }
      }
      print ansiColor("blue") . "SIZE-STAT: " . $file['basename'] . " ( " . 
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
      if (file_exists($file['basename']) && file_exists($fileorig['basename']) ) {
        unlink($file['basename']);
        rename($fileorig['basename'], $file['basename']);
        $info = ffprobe($file, $options)[1];
        $file = rename_byCodecs($file, $options, $info);
        $file = rename_PlexStandards($file, $options, $info);
        $options['args']['exclude'] = true;
        print charTimes(80, "#", "blue") . "\n";
      }
    }
  }

  if ($options['args']['exclude']) {
    if (empty($info)) {
      $info = ffprobe($file, $options)[1];
    }
      $options = setXmlExclude($file, $options, $info);
  }

  if ($options['args']['cooldown'] > 0) {
    print ansiColor("red") . "Cooldown period: " . $options['args']['cooldown'] . " seconds.\n" . ansiColor();
    sleep($options['args']['cooldown']);
  }

  chdir($curdir);
  return($stats);
}
