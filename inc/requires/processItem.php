<?php
/**
 *  input dir, item, options, args, stats
 *  output: none
 *  purpose:  Process a media item to be probed, analyzed, encoded, renamed
 * 
 */

function processItem($dir, $item, $options, $args, $global)
{
  # Preprocess Checks
  $file = pathinfo("$dir" . DIRECTORY_SEPARATOR . "$item");
  // Exclusions
  if (
    isStopped($options) || !isset($file['extension']) ||
    !in_array(strtolower($file['extension']), $options['args']['extensions'])
  ) {
    return ($global);
  }

  //  Show Running Progress
  $global['processed']++;
  if ($args['show_progress']) {
    $global['total_files'] = !empty($global['total_files']) ? $global['total_files'] : 1;
    $percent               = round((($global['processed'] / $global['total_files']) * 100), 0);
    if ($percent > 0) {
      $global['percent'] = $percent;
      print "   (" . $global['processed'] . "/" . $global['total_files'] . ") " . $percent . " %\r";
    }
  }

  $curdir = getcwd();
  chdir($file['dirname']);

  // Scna and clean leftover files from pervious aborted process
  if (cleanup($file)) return($global);

  // Load Media File
  $file                 = remove_illegal_chars($file, $options);
  list($file, $info)    = ffprobe($file, $options, false);
  list($options, $info) = ffanalyze($file, $info, $options, false);

  // Set Exclude tag in media file (--exclude param given)
  if (
    !empty($options) && $options['args']['exclude'] && !$options['args']['override'] &&
    !empty($info) && !$info['format']['exclude']
  ) {
    $tag_data = [array("name" => "exclude", "value" => "1")];
    $status   = setMediaFormatTag($file, $tag_data);
    if (!$status) {
      ffprobe($file, $options, true);
    }
    return ($global);
  }

  $mtime = filemtime($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename']);
  // Check the file's timestamp (Do not process if file time is too new. Still unpacking?)
  if ($mtime > time()) {
    touch($file['basename'], time()); #file time is in the future ( created overseas || GMT ?), set it to current time (otherwise keep timestamp of file)
  }

  // PreProcess Item
  $fileorig = file_exists($file['filename'].".orig.".$file['extension']) ? pathinfo($file['filename'].".orig.".$file['extension']) : [];

  # Convert Item
  list($file, $fileorig, $options) = convertItem($file, $options, $info);

  # MKVmerge Item
  list($file, $fileorig, $options) = mkvmergeItem($file, $fileorig, $options, $info);
 
  // Process Item
  if (empty($info) || empty($options) || isStopped($options))
    return ($global);
  if ($info['format']['exclude'] && !$options['args']['override'])
    return ($global);
  if (empty($fileorig))  $fileorig = $file;  // convert and/or mkvmerge was not needed

  // Configure video options 
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
  } else {
    $options['video']['fps'] = $info['video']['fps'];
    $fps_option              = "";
  }
  if ($info['video']['hdr']) {
    $resolution = isset($options['video']['scale']) ? (int) $options['video']['scale'] : (int) $info['video']['height'];
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
    $options['args']['meta']  =
      " -metadata:s:v:0 bit_rate=" . $options['video']['vps'] .
      " -metadata:s:v:0 bps=" . $options['video']['bps'];
  } else {
    $options['args']['video'] .= " -pix_fmt " . $info['video']['pix_fmt'];
  }

  // ENCODE MEDIA

  $file = rename_byCodecs($file, $options, $info);
  $file = rename_PlexStandards($file, $options);
 
  $cmdln = "nice --adjustment=" . $args['priority'] . " ffmpeg " .
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
    "-stats_period " . $options['args']['stats_period'] . " " .
    "-y \"" . $file['filename'] . ".hevc\"";
  if ($options['args']['verbose']) {
    print "\n\n" . ansiColor("green") . "$cmdln\n\n" . ansiColor();
  }
  if (!$options['args']['test'] && !isStopped($options)) {
    print ansiColor("blue") . "HEVC Encoding: " . ansiColor("yellow") . $file['basename'] . ansiColor("yellow") . "\n";
    if (isset($options['info']['video'])) {
      $rts = preg_match('/copy/', $options['info']['video']) ? 11 : 38;
    } else {
      $rts = 38;
    }
    print charTimes($rts, " ") . "run time=" . seconds_toTime($info['format']['duration']) . ansiColor() . "\n";
    exec("$cmdln", $output, $status);
    if ($status == 255) {
      // status(255) => CTRL-C
      // Restore and Cleanup
      if(!empty($fileorig) && !empty($file)) {
        rename($fileorig['basename'], $file['basename']); 
      }
      stop($options, time());
      return ($global);
    }
  }

  // Validate Item (media swap)
  if ($options['args']['test']) {
    return ($global);  // preserve state at stop
  }
  if (file_exists($file['filename'] . ".hevc") && file_exists($file['basename'])) {
    $inforig = $info;
    rename($file['filename'] . ".hevc", $file['filename'] . "." . $options['args']['extension']);
    $file = pathinfo("$dir" . DIRECTORY_SEPARATOR . $file['filename'] . "." . $options['args']['extension']);
    if (file_exists($file['basename'])) {
      list($file, $info) = ffprobe($file, $options, true);
    }
  }
  if (empty($info))
    return ($global);
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
    isset($info['format']['size']) &&
    (int) ($inforig['format']['size']) <= ((int) $info['format']['size'])
  ) {
    $reasons[] = "original filesize is smaller by ( " . formatBytes($info['format']['size'] - filesize($fileorig['basename']), 0, false) . " )";
  }
  if (empty($reasons) || ($options['args']['override'])) {
    if (file_exists($file['basename'])) {
      if (isset($options['args']['destination'])) {
        if (file_exists($options['args']['destination'] . DIRECTORY_SEPARATOR)) {
          //move file to destination path defined in (external_ini_file)
          print ansiColor("green") . "MOVING: " . $file['filename'] . "." . $options['args']['extension'] . " to " . $options['args']['destination'] . "\n" . ansiColor();
          rename($file['filename'] . "." . $options['args']['extension'], $options['args']['destination'] . DIRECTORY_SEPARATOR . $file['filename'] . "." . $options['args']['extension']);
        } else {
          print ansiColor("red") . "Destination: " . ansiColor("white") . $options['args']['destination'] . ansiColor("red") . " does not exist." . ansiColor();
        }
      }
    }
    if (isset($fileorig) && isset($global['byteSaved']) && isset($global['reEncoded'])) {
      $global['byteSaved'] += (filesize($fileorig['basename']) - ($info['format']['size']));
      $global['reEncoded']++;
    }
    if (isset($file) && file_exists($file['basename'])) {
      set_fileattrs($file, $options);
      touch($file['filename'] . "." . $options['args']['extension'], $mtime); //retain original timestamp
    }
    if (isset($info) && isset($inforig)) {
      print ansiColor("blue") . "SIZE-STAT: " . ansiColor("green") . $file['basename'] . ansiColor("blue") . " ( " .
        "[orig] " . ansiColor("red") . formatBytes($inforig['format']['size'], 2, true) . ansiColor("blue") . " - " .
        "[new] " . ansiColor("yellow") . formatBytes($info['format']['size'], 2, true) . ansiColor('blue') . " = " .
        "[diff] " . ansiColor("green") . formatBytes(($inforig['format']['size'] - $info['format']['size']), 2, true) . ansiColor("blue") . " " .
        ")\n" . ansiColor();
    }
    if (isset($fileorig) && file_exists($fileorig['basename']) && !$options['args']['keeporiginal']) {
      unlink($fileorig['basename']);
    }

    if (file_exists($file['basename'])) {
      $file = rename_byCodecs($file, $options, $info);
      $file = rename_PlexStandards($file, $options);      
    }

    print charTimes(80, "#", "blue") . "\n\n";

  } else {
    print ansiColor("red") . "Rollback: " . $file['basename'] . ansiColor() . "\n";
    print ansiColor("yellow") . "  reason(s):\n" . ansiColor();
    foreach ($reasons as $reason) {
      print ansiColor("red") . "    $reason\n" . ansiColor();
    }
    // Conversion failed : Let's cleanup and exclude
    if (file_exists($file['filename'] . ".hevc") && file_exists($file['basename']))
      unlink($file['filename'] . ".hevc");
    if (file_exists($file['basename']) && file_exists($fileorig['basename']))
      unlink($file['basename']);
    if (!empty($fileorig) && file_exists($fileorig['basename']))
      rename($fileorig['basename'], $file['basename']);

    $info = ffprobe($file, $options, true)[1];
    $options['args']['exclude'] = true;
    print charTimes(80, "#", "blue") . "\n\n";
  }

  $tags_data = [];
  $tag_data  = [];
  if (!empty($options['args']['exclude']))
    $tags_data['exclude'] = $options['args']['exclude'];
  if (!empty($options['args']['mkvmerged']) && empty($options['args']['exclude']))
    $tags_data['mkvmerged'] = $options['args']['mkvmerged'];
  if (!empty($options['args']['audioboost']) && empty($options['args']['exclude']))
    $tags_data['audioboost'] = $options['args']['audioboost'];

  // Set ffHEVC Media Format Tags 
  if (!empty($info) && !$options['args']['override']) {
    if (file_exists($file['basename'])) {
      foreach ($tags_data as $tag => $tag_val) {
        if (!empty($tag_val) && $file['extension'] == "mkv") {
          $tag_data[] = array("name" => "$tag", "value" => "$tag_val");
        } else {
          setXmlFormatAttribute($file, "$tag", "$tag_val"); # mkvpropedit only works on mkv, send to xml
        }
      }
      if ($file['extension'] == "mkv") {
        $status = setMediaFormatTag($file, $tag_data);
        if (!$status) {
          ffprobe($file, $options, true);
        }
      }
    }
  }

  if ($options['args']['cooldown'] > 0) {
    print ansiColor("red") . "Cooldown period: " . $options['args']['cooldown'] . " seconds.\n" . ansiColor();
    sleep($options['args']['cooldown']);
  }

  // Item Complete
  chdir($curdir);
  return ($global);
}


