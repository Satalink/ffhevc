#!/usr/bin/php
<?php
$VERSION = 20231221.1747;

//Initialization and Command Line interface stuff
$self = explode('/', $_SERVER['PHP_SELF']);
$stop = "/tmp/hevc.stop";
$application = end($self);
$dirs = array();
$cleaned = array();
$args = array();
$stats = array(
 'processed' => 0,
 'reEncoded' => 0,
 'byteSaved' => 0
);

function getDefaultOptions($args) {
  $options = array();
  $HOME = getenv("HOME");

  $external_paths_file = "${HOME}/bin/hevc_paths.ini";

//Format: (don't end line with comma in external file)
  /* example hevc_paths.ini key locations configurations
   *
   *
    #"key" => "/path/to/video/library|audioChannels|audioK|vmin|vmax|quality_factor|scale|fps|contrast|brightness|saturation|gamma|/optional/destination/path"
    "gopro" => "/cygdrive/e/Videos/GoPro|2|128k|10|32|2|720|30|1.07|0.02|1.02|0.95"
    "goarchive" => "/cygdrive/e/Videos/GoPro/archived|2|64k|10|38|0.75|640|18|1|0|1|1"
    "tv" => "/cygdrive/e/TV_Shows|6|384k|1|38|0.75|720|25|1|0|1|1"
    "mov" => "/cygdrive/e/Movies|8|512k|1|36|0.75|2160|30|1|0|1|1"
    "newtv" => "/cygdrive/c/Temp/TV_Shows|6|384k|1|38|0.75|720|25|1|0|1|1|/cygdrive/e/TV_Shows"
    "new" => "/cygdrive/c/Temp/Movies|7|512k|2|36|0.75|2160|30|1|0|1|1|/cygdrive/e/Movies"
   *
   * This allows for paths to be added without modifying the script
   * (i.e. update script without mucking up configured paths)
   *
   */
  $options['locations'] = array();
  if (file_exists($external_paths_file)) {
    $data = file($external_paths_file);
    foreach ($data as $line) {
      if (preg_match('/^#/', $line)) {
        continue;  // skip commented out lines
      }
      list($keyname, $path) = explode("=>", $line);
      $keyname = trim(str_replace("\"", "", $keyname));
      $path = trim(str_replace("\"", "", $path));
      $options['locations'][$keyname] = $path;
    }
  }
  else {
# directory config format is:
#"key" => "/path/to/video/library|audioChannels|audioK|vmin|vmax|quality_factor|scale|fps|contrast|brightness|saturation|gamma/optional/destination/path"
    $options['locations'] = array(
      "tv" => "/path/to/TV_Shows|6|384k|1|38|0.75|720|25|1|0|1|1|/optional/desitnation/path",
      "mov" => "/path/to/Movies|8|640k|1|33|0.9|2160|30|1|0|1|1|/optional/desitnation/path"
    );
  }

//Default configuration for video/audio encoding
#Presets for Plex Direct Play  (use nvenc if you have GTX-1060+ or GTX-960+ Card)
  $opt['H264'] = array("main", "matroska", ".mkv", "libx264", "yuv420p", "avc");
  $opt['H264-nvenc'] = array("main", "matroska", ".mkv", "h264_nvenc", "yuv420p", "avc");
  $opt['H265'] = array("main10", "matroska", ".mkv", "libx265", "yuv420p10le", "hevc");
  $opt['hevc-nvenc-mkv'] = array("main10", "matroska", ".mkv", "hevc_nvenc", "yuv420p10le", "hevc");
  $opt['hevc-nvenc-mp4'] = array("main10", "mp4", ".mp4", "hevc_nvenc", "p010le", "hevc");

//---EASY CONFIG SELECT---//
  $my_config = $opt['hevc-nvenc-mkv'];
//---EASY CONFIG SELECT---//
  $options = array_merge($options, setOption($my_config));

  /* Edit below settings to your preferences
   *
   *    NOTE: External Paths Config OVERRIDES THESE VALUES
   */

  $options['owner'] = "";
  $options['group'] = "Administrators";
  $options['video']['quality_factor'] = 1.29;  //Range 1.00 to 3.00 (QualityBased: vmin-vmax overrides VBR Quality. Bitrate will not drop below VBR regardless of vmin-vmax settings)
  $options['video']['filesize_tollerance'] = 1.05;  // If the re-encoded file is greater than original by x%, keep re-encoded file (should be greater than or equal to 1.00)
  $options['video']['vmin'] = "1";
  $options['video']['vmax'] = "35";  // The lower the value, the higher the quality/bitrate
  $options['video']['max_streams'] = 1;
  $options['video']['fps'] = 29.97;
  $options['video']['scale'] = 2160;  // max video resolution setting
  $options['video']['contrast'] = 1;
  $options['video']['brightness'] = 0;
  $options['video']['saturation'] = 1;
  $options['video']['gamma'] = 1;
  $options['video']['hdr']['color_range'] = array("tv"); // "tv", "pc"
  $options['video']['hdr']['codec'] = "hevc_nvenc";  // = "libx265" If you're video card does not support HDR;
  $options['video']['hdr']['pix_fmt'] = array("p010le","p010le","p010le");
  $options['video']['hdr']['color_primary'] = array("bt601|","bt709","bt2020");
  $options['video']['hdr']['color_transfer'] = array("bt601","bt709","smpte2084");
  $options['video']['hdr']['color_space'] = array("bt601","bt709","bt2020nc");
  $options['audio']['codecs'] = array("aac", "ac3", "ac-3", "eac3",);   //("aac", "ac3", "libopus", "mp3") allow these c  $options['video']['hdr']['codec'] = "libx265";zodesc (if bitrate is below location limits)
  $options['audio']['codec'] = "eac3";  // ("aac", "ac3", "libfdk_aac", "libopus", "mp3", "..." : "none")
  $options['audio']['channels'] = 6;
  $options['audio']['bitrate'] = "720k"; // default fallback maximum bitrate (bitrate should never be higher than this setting)
  $options['audio']['quality_factor'] = 1.01; // give bit-rate some tollerance (384114 would pass okay for 384000)
  $options['audio']['sample_rate'] = 48000;
  $options['audio']['max_streams'] = 1;  //Maximum number of audio streams to keep

  $options['args']['application'] = $args['application'];
  $options['args']['verbose'] = false;
  $options['args']['test'] = false;
  $options['args']['yes'] = false;
  $optiosn['args']['keys'] = false;
  $options['args']['force'] = false;
  $options['args']['skip'] = false;
  $options['args']['override'] = false;
  $options['args']['followlinks'] = false;
  $options['args']['exclude'] = false;  // if true, the xml file will be flagged exclude for the processing the media.
  $options['args']['keeporiginal'] = false; //if true, the original file will be retained. (renamed as filename.orig.ext)
  $options['args']['keepowner'] = true;  // if true, the original file owner will be used in the new file.
  $options['args']['deletecorrupt'] = false; // if true, corrupt files will be automatically deleted. (can be annoying if you're not fully automated)
  $options['args']['permissions'] = 0664; //Set file permission to (int value).  Set to False to disable.
  $options['args']['language'] = "eng";  // Default language track
  $options['args']['filter_foreign'] = true; // filters out all other language tracks that do not match default language track (defined above) : requires mkvmerge in $PATH
  $options['args']['delay'] = 15; // File must be at least [delay] seconds old before being processes (set to zero to disable) Prevents process on file being assembled or moved.
  $options['args']['cooldown'] = 0; // used as a cool down period between processing - helps keep extreme systems for over heating when converting an enourmous library over night (on my liquid cooled system, continuous extreme load actually raises the water tempurature to a point where it compromises the systems ability to regulate tempurature.
  $options['args']['loglev'] = "info";  // [quiet, panic, fatal, error, warning, info, verbose, debug]
  $options['args']['timelock'] = false;  //Track if file has a timestamp that is in the future
  $options['args']['threads'] = 0;
  $options['args']['maxmuxqueuesize'] = 4096;
  $options['args']['pix_fmts'] = array(//acceptable pix_fmts
    "yuv420p",
    "yuv420p10le",
    "p010le",
  );
  $options['args']['cmdlnopts'] = array(
    "help",
    "yes",
    "keys",
    "test",
    "force",
    "nomkvmerge",
    "override",
    "followlinks",
    "exclude",
    "keeporiginal",
    "keepowner",
    "permissions",
    "language::",
    "filterforeign",
    "abitrate::",
    "acodec::",
    "achannels::",
    "asamplerate::",
    "pix_fmt::",
    "vbr::",
    "vmin::",
    "vmax::",
    "fps::",
    "scale::",
    "quality::"
  );
  return($options);
}

$args['application'] = $application;
$options = getDefaultOptions($args);  //Reset options

if (count($argv) > 1) {
  $args['index'] = $i = 0;
  foreach ($argv as $key => $arg) {
    if (is_file($arg)) {
      $file = pathinfo($arg);
      if ($file['basename'] == $application) {
        unset($file);
      }
    }
    elseif (preg_match('/^--/', $arg)) {
      $args[] = $arg;
      if (!$args['index']) {
        $args['index'] = $i;
        if ($args['index'] > 1) {
          exit("\n\033[01;31m  Invalid First Option: \"$arg $i\"\n\033[0m  Type $>\033[01;32m$application --help\n\033[0m");
        }
      }
    }
    elseif (preg_match('/^-/', $arg)) {
      exit("\n\033[01;31m  Unknown option: \"$arg\"\n\033[0m  Type $>\033[01;32m$application --help\n\033[0m");
    }
    else {
      if (array_key_exists($arg, $options['locations'])) {
        $args['key'] = $arg;
      }
    }
    $i++;
  }

//Single File Processing
  if (!empty($file)) {
    $options = getLocationOptions($options, $args, $file['dirname']);
    processItem($file['dirname'], $file['basename'], $options, $args, $stats);
    exit;
  }

//Defined Key Scan and Process
  if (isset($args['key'])) {
    $location = str_replace(" ", "\ ", $options['locations'][$args['key']]);
    $key = $args['key'];
    $dirs["$key"] = explode("|", $location)[0];
    $options = getLocationOptions($options, $args, explode("|", $location)[0]);
  }
  elseif ($args['index'] > 0) {
    $dir = getcwd();
    $options = getLocationOptions($options, $args, $dir);
    if (!empty($options['args']) && array_key_exists("key", $options['args'])) {
      $args['key'] = $options['args']['key'];
    }
    $dirs = array($args['key'] => $dir);
  }
  else {
    print "\n\033[01;31mDefined Locations:\033[0m\n";
    print_r($options['locations']);
    exit("\033[01;31m  Unknown location: \"$argv[1]\"\n\033[01;32m  Edit \$options['locations'] to add it OR create and define external_paths_file.ini within the script.\033[0m");
  }
}
else {
//no arguments, use current dir to parse location settings
  $dir = getcwd();
  $options = getLocationOptions($options, $args, $dir);
  $args['key'] = null;
  if (!empty($options['args']) && array_key_exists("key", $options['args'])) {
    $args['key'] = $options['args']['key'];
  }
  $dirs = array($args['key'] => $dir);
}

/* ----------MAIN------------------ */

//Only run one instance of this script.
exec("ps -efW|grep -v grep|grep ffmpeg", $ffmpid);
exec("ps -efW|grep -v grep|grep mkvmerge", $mkvmpid);
if (!empty($ffmpid) && !$options['args']['force']) {
  exit("FFMPEG already running on another process: $ffmpid[0]\n");
}
elseif (!empty($mkvmpid)  && !$options['args']['force']) {
  exit("MKVMERGE already running on another process: $mkvmpid[0]\n");
}
//If stop file is detected and no other processes detected, delete it and continue.
if (file_exists("$stop")) {
  unlink("$stop");
}

foreach ($dirs as $key => $dir) {
  processRecursive($dir, $options, $args, $stats);
  if ($stats['processed']) {
    print "Scanned Videos: \033[01;33m" . $stats['processed'] . "\033[0m\n";
  }
  if ($stats['reEncoded']) {
    print "Re-Encoded    : \033[01;34m" . $stats['reEncoded'] . "\033[0m\n";
  }
  if ($stats['byteSaved']) {
    print "Space Saved   : \033[01;32m" . formatBytes($stats['byteSaved'], 0, true). "\033[0m\n";
  }
}

/* ## ## ## STATIC FUNCTIONS ## ## ## */

function processRecursive($dir, $options, $args, $stats) {
  global $stats;
  $result = array();
  $list = array_slice(scandir("$dir"), 2);
  foreach ($list as $index => $item) {
    global $stop;
    if (file_exists("$stop")) {
      unlink("$stop");
      exit("STOP FILE DETECTED: $stop");
    }
   
    if (!$options['args']['followlinks'] && is_link($dir . DIRECTORY_SEPARATOR . $item )) {
      //print "SKIPPED SYMLINK: ${dir}" . DIRECTORY_SEPARATOR . "${item}\n";
      continue;  //skip symbolic links
    }
    if (is_dir($dir . DIRECTORY_SEPARATOR . $item)) {
      if (!preg_match("/_UNPACK_/", $item)) {
        cleanXMLDir($dir, $options);
        $result["$dir" . DIRECTORY_SEPARATOR . "$item"] = processRecursive($dir . DIRECTORY_SEPARATOR . $item, $options, $args, $stats);
      }
    }
    else {
      processItem($dir, $item, $options, $args, $stats);
    }
  }
}

function processItem($dir, $item, $options, $args, $stats) {
  global $stats;
  $extensions = getExtensions();
  $file = strip_illegal_chars(pathinfo("$dir" . DIRECTORY_SEPARATOR . "$item"));
  $file = titlecase_filename($file, $options);
  if (
    !isset($file['extension']) ||
    !in_array(strtolower($file['extension']), $extensions)
  ) {
    return;
  }
  if ($options['args']['exclude']) {
    $info = ffprobe($file, $options);
    if (empty($info)) {
      return;
    }
    setXmlFormatAttribute($file, "exclude");
    return;
  }
  if (isset($stats['processed'])) {  
    $stats['processed']++;
  }
  $curdir = getcwd();
  chdir($file['dirname']);

# Process Item
  $options['info']['title'] = "'" . str_replace("'", "\'", $file['filename']) . "'";
  $options['info']['timestamp'] = date("Ymd.His");

  $info = ffprobe($file, $options);
  if (empty($info)) {
    return;
  }

  $options = ffanalyze($info, $options, $args, $dir, $file);
  if (empty($options)) {
    return;
  }

// check the file's timestamp (Do not process if file time is too new. Still unpacking?)
  if (filemtime($file['basename']) > time()) {
    touch($file['basename'], time()); //file time is in the future (created overseas?), set it to current time.
    print $file['basename'] . " modified in the future (was reset to now) Try again in " . $options['args']['delay'] . " seconds.";
    $options['args']['timelock'] = true;
    return;
  }
  elseif ((filemtime($file['basename']) + $options['args']['delay']) > time() && filemtime($file['basename']) <= time() && !$options['args']['force']) {
    print $file['basename'] . " modified < " . $options['args']['delay'] . " seconds ago.";
    return;
  }


//Preprocess with mkvmerge (if in path)
  if (`which mkvmerge` && !$options['args']['skip'] && !$options['args']['test']) {
    if ( $options['args']['filter_foreign'] && !empty($options['args']['language'])) {
       if ( !$info['format']['mkvmerged'] ||
           $options['args']['force']
       ) {
          $cmdln = "mkvmerge" .
          " --language 0:" . $options['args']['language'] .
          " --language 1:" . $options['args']['language'] .
          " --video-tracks " . $info['video']['index'] .
          " --audio-tracks " . $info['audio']['index'] .
          " --track-order " . "0:".$info['video']['index'].","."1:".$info['audio']['index'] .
          " --subtitle-tracks '" . $options['args']['language'] . "'" .
          " --no-attachments" .
          " --track-tags '" . $options['args']['language'] . "'" .
          " --output '" . $file['filename'] . ".mkvm' '" . $file['basename'] . "'";
        print "\n\n\033[01;32m${cmdln}\033[0m\n";
        system("${cmdln} 2>&1");
        if (file_exists($file['filename'] . ".mkvm")) {
          $mtime = filemtime($file['basename']);
          unlink($file['basename']);
          rename($file['filename'] . ".mkvm", $file['filename'] . ".mkv");
          touch($file['filename'] . ".mkv", $mtime); //retain original timestamp
          $info = ffprobe($file, $options);
          setXmlFormatAttribute($file, "mkvmerged");
          $options = ffanalyze($info, $options, $args, $dir, $file);
          if (empty($options)) {
            return;
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
  $cmdln = "nice -n1 ffmpeg -v " .
    $options['args']['loglev'] . " " .
    "-i \"" . $file['basename'] . "\" " .
    "-threads " . $options['args']['threads'] . " " .
    "-profile:v " . $options['profile'] . " " .
    "-f " . $options['format'] . " " .
    $options['args']['video'] . " " .
    $options['args']['audio'] . " " .
    $options['args']['subs'] . " " .
    $options['args']['map'] . " " .
    $options['args']['meta'] . " " .
    "-y \"" . $file['filename'] . ".hevc\"";

  print "\n\n\033[01;32m${cmdln}\033[0m\n\n";

  if ($options['args']['test']) {
    exit;
  }
  exec("${cmdln}", $output, $status);
  if($status === 255) {
    //status(255) => CTRL-C    
    exit;
  }

 #Swap Validate
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
    $info = ffprobe($file, $options);
  }
  if (empty($info)) {
    return;
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
      echo "================================================================================\n";
      echo "\033[01;34mSTAT: " . $file['filename'] . $options['extension'] . " ( " . 
        formatBytes(filesize($fileorig['basename']), 2, true) . 
        " [orig] - " . formatBytes($info['format']['size'], 2, true) . 
        " [new] = \033[01;32m" . formatBytes(filesize($fileorig['basename']) - ($info['format']['size']), 2, true) . "\033[01;34m [diff] )\033[0m\n";
      if (isset($stats['byteSaved']) && isset($stats['reEncoded'])) {
        $stats['byteSaved'] += (filesize($fileorig['basename']) - ($info['format']['size']));
        $stats['reEncoded']++;
      }
      if (file_exists($fileorig['basename'])) {
        unlink($fileorig['basename']);
      } 
      if (file_exists($file['filename'] . $options['extension'])) {
        touch($file['filename'] . $options['extension'], $mtime); //retain original timestamp
        if (isset($options['args']['destination'])) {
          //move file to destination path defined in (external_ini_file)
          print "\033[01;32mMOVING: " . $file['filename'] . $options['extension'] . " to " . $options['args']['destination'] . "\033[0m\n";
          rename($file['filename'] . $options['extension'], $options['args']['destination'] . DIRECTORY_SEPARATOR . $file['filename'] . $options['extension']);
          set_fileattr($file, $options);
          titlecase_filename($file, $options);
        }
      }
    }
    else {
      print "Rollback: " . $file['basename'] . " : ";
      print "  reason(s):";
      foreach ($reasons as $reason) {
        print "\033[01;34m$reason\033[0m\n";
      }
      //conversion failed : Let's cleanup and exclude
      if (file_exists($file['filename'] . ".hevc")) {
        unlink($file['filename'] . ".hevc");
      }
      if (file_exists($file['filename'] . "." . $options['extension']) && 
          file_exists($fileorig['basename']) ) {
        unlink($file['filename'] . "." . $options['extension']);
        rename($fileorig['basename'], $file['basename']);
      }
      if (file_exists("./.xml/" . $file['filename'] . ".xml")) {
        unlink("./.xml/" . $file['filename'] . ".xml");
        $options['args']['exclude'] = true;
      }
    }
  }

  if ($options['args']['exclude']) {
    $info = ffprobe($file, $options);
    if (empty($info)) {
      return;
    }
    setXmlFormatAttribute($file, "exclude");
  }

  if ($options['args']['cooldown'] > 0) {
    print "\033[01;31mCooldown period: " . $options['args']['cooldown'] . " seconds. \033[0m\n";
    sleep($options['args']['cooldown']);
  }

  chdir($curdir);
  return;
}

function ffanalyze($info, $options, $args, $dir, $file) {
  $options['args']['video'] = '';
  $options['args']['audio'] = '';
  $options['args']['meta'] = '';
  $options['args']['map'] = '';

  if (!isset($info)) {
    $options = array();
    return($options);
  }

  if ($info['format']['exclude'] && !$options['args']['override']) {
    if ($options['args']['verbose']) {
      print "\033[01;35m " . $file['filename'] . "\033[01;31m Excluded! \033[0m  Delete .xml folder or use --override option to override.\n";
    }
    $options = array();
    return($options);
  }

//Container MetaData
  $meta_duration = "";
  if (!empty($info['format']['duration'])) {
    $t = round($info['format']['duration']);
    $duration = sprintf('%02d:%02d:%02d', ($t / 3600), ($t / 60 % 60), $t % 60);
    $meta_duration = " -metadata duration=" . $duration;
  }
  $options['args']['meta'] .= " -metadata title=" . $options['info']['title'] .
    $meta_duration .
    " -metadata creation_date=" . $options['info']['timestamp'] .
    " -metadata encoder= ";

//Video
//Dynamicly adjust video bitrate to size +
  if (!empty($info['video'])) {
    $codec_name = $options['video']['codec_name'];
    $options['video']['vps'] = round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) . "k";
    $options['video']['bps'] = (round((($info['video']['height'] + $info['video']['width']) * $options['video']['quality_factor']), -2) * 1000);

    if (!isset($info['video']['bitrate'])) {
      //$info['video']['bitrate'] = $options['video']['bps'];
      $info['video']['bitrate'] = 0;
    }

    if (
      preg_match(strtolower("/$codec_name/"), $info['video']['codec']) &&
      (in_array($info['video']['pix_fmt'], $options['args']['pix_fmts'])) &&
      ($info['video']['height'] <= $options['video']['scale']) &&
      ($info['video']['bitrate'] !== 0) &&
      ($info['video']['bitrate'] <= $options['video']['bps']) &&
      (!$options['args']['override'])
    ) {
      $options['args']['video'] = "-vcodec copy";
    }

  // var_dump($info['video']);
  // var_dump($options['video']);
  // var_dump($options['args']['video']);
  // exit;

  
    if (!preg_match("/copy/i", $options['args']['video'])) {
      $pf_key = array_search($info['video']['pix_fmt'], $options['args']['pix_fmts']);
      print "\033[01;32mVideo Inspection ->\033[0m" .
        $info['video']['codec'] . ":" . $options['video']['codec_name'] . "," .
        $info['video']['pix_fmt'] . "~=" . $options['args']['pix_fmts'][$pf_key] . "," .
        $info['video']['height'] . "<=" . $options['video']['scale'] . "," .
        $info['video']['bitrate'] . "<=" . $options['video']['bps'] . "," . // give some tollerance
        $options['video']['quality_factor'] . "," . $options['args']['override'] . "\n";
      list($ratio_w, $ratio_h) = explode(":", $info['video']['ratio']);
      
      if ($info['video']['height'] > $options['video']['scale']) {
        //hard set info to be used for bitrate calculation based on scaled resolution
        $info['video']['width'] = (($info['video']['width'] * $options['video']['scale']) / $info['video']['height']);
        $info['video']['height'] = $options['video']['scale'];
        $info['video']['bitrate'] = $options['video']['vps'];
        $scale_option = "scale=-1:" . $options['video']['scale'];
        //Recalculate target video bitrate based on projected output scale
        $options['video']['vps'] = round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) . "k";
        $options['video']['bps'] = (round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) * 1000);
      }
      else {
        $scale_option = null;
      }

      if ($info['video']['fps'] > $options['video']['fps']) {
        $fps_option = " -r " . $options['video']['fps'];
      }
      else {
        $options['video']['fps'] = $info['video']['fps'];
        $fps_option = "";
      }

      $options['args']['video'] = '' .
        "-vcodec " . $options['video']['codec'] .
        " -vb " . $options['video']['vps'] .
        " -qmin " . $options['video']['vmin'] .
        " -qmax " . $options['video']['vmax'] .
        " -max_muxing_queue_size " . $options['args']['maxmuxqueuesize'] .
        $fps_option;

      if (isset($scale_option)) {
        $options['args']['video'] .= " -vf \"" . $scale_option . "\"";
      }

      if (
        (isset($options['video']['contrast']) && $options['video']['contrast'] != 1) ||
        (isset($options['video']['brightness']) && $options['video']['brightness'] != 0) ||
        (isset($options['video']['satuation']) && $options['video']['satuation'] != 1) ||
        (isset($options['video']['gamma']) && $options['video']['gamma'] != 1)
      ) {
        $options['args']['video'] .= " -vf \"eq=" .
          "contrast=" . $options['video']['contrast'] .
          ":brightness=" . $options['video']['brightness'] .
          ":saturation=" . $options['video']['saturation'] .
          ":gamma=" . $options['video']['gamma'] . " ";
      }

      $options['args']['meta'] .= " -metadata:s:v:0 language=" . $options['args']['language'] .
        " -metadata:s:v:0 codec_name=" . $options['video']['codec'] .
        " -metadata:s:v:0 bit_rate=" . $options['video']['vps'] .
        " -metadata:s:v:0 bps=" . $options['video']['bps'] .
        " -metadata:s:v:0 title= ";
    }
    else {
      $options['args']['meta'] .= " -metadata:s:v:0 language=" . $options['args']['language'] .
        " -metadata:s:v:0 codec_name=" . $options['video']['codec'] .
        " -metadata:s:v:0 bit_rate=" . $options['video']['vps'] .
        " -metadata:s:v:0 bps=" . $options['video']['bps'] .
        " -metadata:s:v:0 title= ";
    }
    $options['args']['map'] .= "-map 0:v? ";
  }

  //Audio
  if ($options['audio']['bitrate'] == 0 || stripos($info['audio']['title'], "comment")) {
    $info['audio'] = null;
  }
  if (!empty($info['audio'])) {
    $title = "Default Track";
    if (!empty($info['audio']['title'])) {
      $title = isset($info['audio']['title']) ? $info['audio']['title'] : "Default Track";
    }
    if (!stripos($title, "comment")) {
      if (
        in_array($info['audio']['codec'], $options['audio']['codecs']) &&
        (int) ((filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000) * $options['audio']['quality_factor']) &&
        $info['audio']['channels'] <= $options['audio']['channels'] &&
        !$options['args']['override']
      ) {
        $options['args']['audio'] = "-acodec copy";
        $options['audio']['channels'] = $info['audio']['channels'];
      }
      if (preg_match("/copy/i", $options['args']['audio'])) {
        if ($info['audio']['bitrate'] == 0) {
          $info['audio']['bitrate'] = "";
          $info['audio']['bps'] = "";
        }
        elseif (
          isset($info['audio']['bitrate']) &&
          !empty($info['audio']['bitrate'])
        ) {
          if (is_numeric($info['audio']['bitrate'])) {
            $info['audio']['bps'] = $info['audio']['bitrate'];
            $info['audio']['bitrate'] = (int) round(($info['audio']['bitrate'] / 1000)) . "k";
          }
          else {
            $info['audio']['bps'] = (int) (filter_var($info['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000);
          }
        }
        else {
          $info['audio']['bps'] = "";
        }
        $options['args']['meta'] .= " -metadata:s:a:0 language=" . $options['args']['language'] . " " .
          " -metadata:s:a:0 codec_name=" . $info['audio']['codec'] .
          " -metadata:s:a:0 channels=" . $info['audio']['channels'] .
          " -metadata:s:a:0 bit_rate=" . $info['audio']['bitrate'] .
          " -metadata:s:a:0 sample_rate=" . $info['audio']['sample_rate'] .
          " -metadata:s:a:0 bps=" . $info['audio']['bps'] .
          " -metadata:s:a:0 title= ";
      }
      else {
        print "\033[01;32mAudio Inspection ->\033[0m" .
          $info['audio']['codec'] . ":" . $options['audio']['codec'] . "," .
          $info['audio']['bitrate'] . "<=" . (filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000) . "\n";
        if (is_numeric($info['audio']['bitrate'])) {
          $info['audio']['bps'] = $info['audio']['bitrate'];
          $info['audio']['bitrate'] = (int) round(($info['audio']['bitrate'] / 1000)) . "k";
        }
        elseif (!empty($info['audio']['bitrate']) && is_integer($info['audio']['bitrate'])) {
          $info['audio']['bps'] = (int) (filter_var($info['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000);
        }
        else {
          $info['audio']['bps'] = (int) (filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000);
          $info['audio']['bitrate'] = $options['audio']['bitrate'];
        }

        $options['audio']['bps'] = (int) (filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000);
        //Don't upsample source audio
        if ($info['audio']['bps'] < $options['audio']['bps']) {
          $options['audio']['bitrate'] = $info['audio']['bitrate'];
          $options['audio']['bps'] = $info['audio']['bps'];
        }
        if ($info['audio']['channels'] < $options['audio']['channels'] || !isset($options['audio']['channels'])) {
          $options['audio']['channels'] = $info['audio']['channels'];
        }
        if ($info['audio']['sample_rate'] < $options['audio']['sample_rate'] || !isset($options['audio']['sample_rate'])) {
          $options['audio']['sample_rate'] = $info['audio']['sample_rate'];
        }
        //Set audio args
        if (
          isset($options['audio']['codec']) &&
          isset($options['audio']['channels']) &&
          isset($options['audio']['bitrate']) &&
          isset($options['audio']['sample_rate'])
        ) {
          $options['args']['audio'] = "-acodec " . $options['audio']['codec'] .
            " -ac " . $options['audio']['channels'] .
            " -ab " . $options['audio']['bitrate'] .
            " -ar " . $options['audio']['sample_rate'] .
            " -async 1";
        }
        else {
          $options['args']['audio'] = "-acodec copy";
          $options['audio']['channels'] = isset($info['audio']['channels']) ? $info['audio']['channels'] : '';
          $options['audio']['bitrate'] = isset($info['audio']['bitrate']) ? $info['audio']['bitrate'] : 0;
          $options['audio']['sample_rate'] = isset($info['audio']['sample_rate']) ? $info['audio']['sample_rate'] : '';
        }
        $options['args']['meta'] .= " -metadata:s:a:0 language=" . $options['args']['language'] . " " .
          " -metadata:s:a:0 codec_name=" . $options['audio']['codec'] .
          " -metadata:s:a:0 channels=" . $options['audio']['channels'] .
          " -metadata:s:a:0 bit_rate=" . $options['audio']['bitrate'] .
          " -metadata:s:a:0 sample_rate=" . $options['audio']['sample_rate'] .
          " -metadata:s:a:0 bps=" . $options['audio']['bps'] .
          " -metadata:s:a:0 title=" . isset($info['audio']['title']) ? $info['audio']['title'] : "Default Track";
      }
      $options['args']['map'] .= "-map 0:a? ";
    }
  }

  //Subtexts
  $options['args']['subs'] = "-scodec copy";
  $options['args']['map'] .= "-map 0:s? ";

  //Clear Old Tags
  //lowercase metadata names
  $keep_vtags = array(
    "bps",
    "bit_rate",
    "duration",
    "duration-" . $options['args']['language'],
    "creation_date",
    "language",
    "codec_name",
    "rotate",
    "_statistics_writing_app",
    "_statistics_writing_date_utc"
  );
  //lowercase metatag names
  $keep_atags = array(
    "title",
    "duration",
    "duration-" . $options['args']['language'],
    "creation_date",
    "language",
    "codec_name",
    "channels",
    "sample_rate",
    "bit_rate",
    "bps",
    "_statistics_tags",
    "_statistics_writing_app",
    "_statistics_writing_date_utc",
    "number_of_frames",
    "number_of_bytes"
  );

  if (!empty($info['vtags'])) {
    foreach ($info['vtags'] as $vtag => $vval) {
      $lvtag = strtolower($vtag);
      if (in_array($lvtag, $keep_vtags)) {
        $options['args']['meta'] .= " -metadata:s:v:0 ${vtag}=${vval}";
      }
      else {
        // Set the existing value to nothing
        $options['args']['meta'] .= " -metadata:s:v:0 ${vtag}=";
      }
    }
  }
  if (!empty($info['atags'])) {
    foreach ($info['atags'] as $atag => $aval) {
      $latag = strtolower($atag);
      if ((in_array($latag, $keep_atags))) {
        $options['args']['meta'] .= " -metadata:s:a:0 ${atag}=${aval}";
      }
      else {
        // Set the existing value to nothing
        $options['args']['meta'] .= " -metadata:s:a:0 ${atag}=";
      }
    }
  }
  if (
       (preg_match("/copy/i", $options['args']['video'])) &&
       (preg_match("/copy/i", $options['args']['audio'])) &&
       ('.' . $file['extension'] == $options['extension'])
     ) {
      return(array());  //No re-encoding needed
  }
  return($options);
}

function ffprobe($file, $options) {
  $exec_args = "-v quiet -print_format xml -show_format -show_streams";
  $basename = $file['basename'];
  $filename = $file['filename'];
  $language = $options['args']['language'];
  $info = array();
  $xmlfile = $filename . '.xml';
  $xml = null;
  if (!file_exists("./.xml")) {
    mkdir("./.xml");
  }
  if (!file_exists("./.xml/${xmlfile}")) {
    $action = "PROBED";
    $cmdln = "ffprobe $exec_args '$basename' > './.xml/${xmlfile}'";
    $result = exec("$cmdln");
    if (!file_exists("./.xml/${xmlfile}")) {
      exit("error: Could not create ffprobe xml.  Check that ffprobe is exists, is executable, and in the system search path\n");
    }
  }
  else {
    $action = "INFO";
  }
  if (file_exists("./.xml/" . $filename . ".xml")) {
    $xml = simplexml_load_file("./.xml/${filename}.xml");
    $xml_filesize = getXmlAttribute($xml->format, "size");
  }
  if (!isset($xml) ||
    empty($xml) ||
    (!empty($xml_filesize) && (int) $xml_filesize != filesize($basename))
  ) {
    print "Stale xml detected.  Initiating new probe...\n";
    unlink("./.xml/" . $file['filename'] . ".xml");
    $info = ffprobe($file, $options);
    $xml = simplexml_load_file("./.xml/${filename}.xml");
  }
  $info = array();
  $info['format']['format_name'] = getXmlAttribute($xml->format, "format_name");
  $info['format']['duration'] = getXmlAttribute($xml->format, "duration");
  $info['format']['size'] = getXmlAttribute($xml->format, "size");
  $info['format']['bitrate'] = getXmlAttribute($xml->format, "bit_rate") ?: getXmlAttribute($xml->format, "BPS");
  $info['format']['nb_streams'] = getXmlAttribute($xml->format, "nb_streams");
  $info['format']['probe_score'] = getXmlAttribute($xml->format, "probe_score");
  $info['format']['exclude'] = getXmlAttribute($xml->format, "exclude") ?: false;
  $info['format']['mkvmerged'] = getXmlAttribute($xml->format, "mkvmerged") ?: false;
  $info['video'] = [];
  $info['audio'] = [];
  $info['subtitle'] = [];
  if (isset($xml->streams->stream)) {
    foreach ($xml->streams->stream as $stream) {
      $codec_type = getXmlAttribute($stream, "codec_type");
      switch ($codec_type) {
        case "video":
          if (empty($info['video'])) {
            $vtags = array();
            $info['video']['index'] = getXmlAttribute($stream, "index");
            $info['video']['codec_type'] = getXmlAttribute($stream, "codec_type");
            $info['video']['codec'] = getXmlAttribute($stream, "codec_name");
            $info['video']['pix_fmt'] = getXmlAttribute($stream, "pix_fmt");
            $info['video']['level'] = getXmlAttribute($stream, "level");
            $info['video']['width'] = getXmlAttribute($stream, "width");
            $info['video']['height'] = getXmlAttribute($stream, "height");
            $info['video']['ratio'] = getXmlAttribute($stream, "display_aspect_ratio");
            $info['video']['avg_frame_rate'] = getXmlAttribute($stream, "avg_frame_rate");
            $info['video']['fps'] = round(( explode("/", $info['video']['avg_frame_rate'])[0] / explode("/", $info['video']['avg_frame_rate'])[1]), 2);
            $info['video']['color_range'] = getXmlAttribute($stream, "color_range");
            $info['video']['color_space'] = getXmlAttribute($stream, "color_space");
            $info['video']['color_transfer'] = getXmlAttribute($stream, "color_transfer");
            $info['video']['color_primaries'] = getXmlAttribute($stream, "color_primaries");
            $info['video']['hdr'] = preg_match('/bt[27][0][29][0]?/', getXmlAttribute($stream, "color_primaries")) ? getXmlAttribute($stream, "color_primaries") : false;
            foreach ($stream->tags->tag as $tag) {
              $tag_key = strtolower(getXmlAttribute($tag, "key"));
              $tag_val = strtolower(getXmlAttribute($tag, "value"));
              $tag_val = str_replace('(', '', str_replace('\'', '', $tag_val));
              //print "\n" . $tag_key . " : " . $tag_val . "\n";
              if (preg_match('/^bps$/i', $tag_key) && !isset($info['video']['bitrate'])) {
                $info['video']['bitrate'] = (int) $tag_val;
              }
              if (preg_match('/^bit[\-\_]rate$/i', $tag_key) && !isset($info['video']['bitrate'])) {
                if (preg_match("/k/i", $tag_val)) {
                  $info['video']['bitrate'] = (int) (filter_var($tag_val, FILTER_SANITIZE_NUMBER_INT) * 1000);
                }
                else {
                  $info['video']['bitrate'] = (int) $tag_val;
                }
              }
              $vtags[$tag_key] = preg_match("/\s/", "$tag_val") ? "'$tag_val'" : $tag_val;
            }
            $info['vtags'] = $vtags;
          }
          break;
        case "audio":
          if (empty($info['audio'])) {
            $atags = array();
            $info['audio']['index'] = getXmlAttribute($stream, "index");
            $info['audio']['title'] = getXmlAttribute($stream, "title");
            $info['audio']['codec_type'] = getXmlAttribute($stream, "codec_type");
            $info['audio']['codec'] = getXmlAttribute($stream, "codec_name");
            $info['audio']['channels'] = getXmlAttribute($stream, "channels");
            $info['audio']['sample_rate'] = getXmlAttribute($stream, "sample_rate");
            $info['audio']['bitrate'] = getXmlAttribute($stream, "bit_rate");
            foreach ($stream->tag as $tag) {
              $tag_key = strtolower(getXmlAttribute($tag, "key"));
              $tag_val = strtolower(getXmlAttribute($tag, "value"));
              $tag_val = str_replace('(', '', str_replace('\'', '', $tag_val));
//              print "\n" . $tag_key . " : " . $tag_val . "\n";
              if ($tag_key == "language") {
                if ($tag_val !== $options['args']['language']) {
                  $info['filters']['audio']['language'][] = $tag_val;
                  $info['audio'] = array();
                  break;
                }
                else {
                  $info['audio']['language'] = $tag_val;
                }
              }
              if (preg_match('/^bps$/i', $tag_key)) {
                if (empty($info['audio']['bitrate'])) {
                  $info['audio']['bitrate'] = $tag_val;
                }
              }
              elseif (preg_match('/bit*rate$/i', $tag_key)) {
                if (empty($info['audio']['bitrate'])) {
                  if (preg_match("/k/i", $tag_val)) {
                    $info['audio']['bitrate'] = (int) (filter_var($tag_val, FILTER_SANITIZE_NUMBER_INT) * 1000);
                  }
                  else {
                    $info['audio']['bitrate'] = (int) $tag_val;
                  }
                }
              }
              $atags[$tag_key] = preg_match("/\s/", "$tag_val") ? "'$tag_val'" : $tag_val;
            }
            $info['atags'] = $atags;
          }
          break;
        case "subtitle":
          if (empty($info['subtitle'])) {
            $info['subtitle']['index'] = getXmlAttribute($stream, "index");
            $info['subtitle']['codec_type'] = getXmlAttribute($stream, "codec_type");
            $info['subtitle']['codec'] = getXmlAttribute($stream, "codec_name");
          }
          break;
      }
    }
  }

  if (
    $action != "INFO" &&
    !empty($info['video']) &&
    !empty($info['audio'])
  ) {
    print "\033[01;34m${action}: " . $file['filename'] . " (";
    if (!empty($info['video']) && !empty($info['video']['bitrate'])) {
      print $info['video']['codec_type'] . ":" . $info['video']['codec'] . ", " . $info['video']['width'] . "x" . $info['video']['height'] . ", " . formatBytes($info['video']['bitrate'], 2, false) . "PS | ";
    }
    if (!empty($info['audio']) && !empty($info['audio']['bitrate'])) {
      print $info['audio']['codec_type'] . ":" . $info['audio']['codec'] . ", CH." . $info['audio']['channels'] . ", " . formatBytes($info['audio']['bitrate'], 0, false) . "PS";
    }
    if (!empty($info['subtitle'])) {
      print "SUB: " . $info['subtitle']['codec_type'] . ":" . $info['subtitle']['codec'];
    }
    print "\033[01;34m)\033[0m\n";
  }
  elseif (
    empty($info['video']) ||
    empty($info['audio'])
  ) {
    $del = null;
    $missing = null;
    if (!$options['args']['yes'] && $file['extension'] == $options['extension']) {
      if (empty($info['video'])) {
        $missing = "video";
      }
      if (empty($info['audio'])) {
        $missing = 'audio';
      }
      print "\033[01;34m " . $file['filename'] . " $missing track is missing\033[0m\n";
      print "Delete " . $file['basename'] . "?  [Y/n] >";
      $del = rtrim(fgets(STDIN));
    }
    if (!preg_match('/n/i', $del) && !$options['args']['exclude']) {
      if (file_exists($file['basename'])) {
        unlink($file['basename']);
      }
      if (file_exists(".xml/" . $file['filename'] . ".xml")) {
        unlink("./.xml/" . $file['filename'] . ".xml");
      }
      print $file['basename'] . " NO Video or Audio\n";
      $info = array();
    }
    // Check if color_range is in options
    if (
      !empty($info['video']['color_range']) && 
      in_array($info['video']['color_range'],$options['video']['hdr']['color_range'])
    ) {
      print "Incompatible color_range detected: " . $file['filename'] . "\n";
      $info = array();
    }
  }
//  var_dump($info);exit;
  return($info);
}

function getLocationOptions($options, $args, $dir) {
  $brate = 0;
  if ($dir == ".") {
    $dir = getcwd();
  }

  foreach ($options['locations'] as $key => $location) {
    $esc_pattern = str_replace("/", "\/", explode("|", $location)[0]);
    if (preg_match("/$esc_pattern/", $dir)) {
      $options['args']['key'] = $args['key'] = $key;
      $location = str_replace(" ", "\ ", $options['locations'][$args['key']]);
      $key = $args['key'];
      $location_param_array = explode("|", $location);
      $dirs["$key"] = $location_param_array[0];
      $options['audio']['channels'] = $location_param_array[1];
      $options['audio']['bitrate'] = $location_param_array[2];
      $options['video']['vmin'] = $location_param_array[3];
      $options['video']['vmax'] = $location_param_array[4];
      $options['video']['quality_factor'] = $location_param_array[5];
      $options['video']['scale'] = $location_param_array[6];
      $options['video']['fps'] = $location_param_array[7];
      $options['video']['contrast'] = $location_param_array[8];
      $options['video']['brightness'] = $location_param_array[9];
      $options['video']['saturation'] = $location_param_array[10];
      $options['video']['gamma'] = $location_param_array[11];
      if (array_key_exists(12, $location_param_array)) {
        $options['args']['destination'] = $location_param_array[12];
      }
    }
  }
  $options = getAudioBitrate($options, $args);
  $options = getCommandLineOptions($options, $args);
  return($options);
}

function getAudioBitrate($options, $args) {
  if (!empty($args['key']) && array_key_exists($args['key'], $options['locations'])) {
    $options['audio']['bitrate'] = explode("|", $options['locations'][$args['key']])[2];
  }
  return($options);
}

function getCommandLineOptions($options, $args) {

//COMMAND LINE OPTIONS
  $help = "
  \033[01;31mcommand [options] [file|key]  // options and flags before file or defined key
  \033[01;33me.g.   $>" . $args['application'] . " --test MediaFileName.mkv
  \033[01;33me.g.   $>" . $args['application'] . " --keeporiginal tv
  \033[01;32mBOOLS (Default false)\033[01;34m
  --verbose    :flag:        display verbose output
  --test          :flag:        print out ffmpeg generated command line only -- and exit
  --yes           :flag:        answer yes to any prompts
  --keys          :flag:        print out the defined keys -- and exit
  --force         :flag:        force encoding and bypass verification checks and delays
  --override      :flag:        reencode and override existing files (redo all existing regardless)
  --exclude       :flag:        exclude from being processed (ignore this video), stored in .xml
  --nomkvmerge    :flag:        do not restructure MKV container with mkvmerge before encoding (if installed and in PATH)
  --keeporiginal  :flag:        keep the original file and save as filename.orig.ext
  --keepowner     :flag:        keep the original file owner for the newly created file
  --filterforiegn :flag:        strip foriegn languages NOT matching \$options['args']['language'] OR --language

  \033[01;32mPARMS (Default * denoted) i.e.  --language=eng \033[01;34m
  --language      :pass value:  manual set at command-line Use 3 letter lang codes. (*eng, fre, spa, etc.. etc..)
  --abitrate      :pass value:  set audio bitrate manually. Use rates compliant with configured audio codec 128k, 192k, 256k, *384k, 512k
  --acodec        :pass value:  set audio codec manually.  (*aac, ac3, libopus, mp3, etc... | none)
  --achannels     :pass value:  set audio channels manually.  (1, 2, *6, 8) 1=mono, 2=stereo, *6=5.1, 8=7.1
  --asamplerate   :pass value:  set audio sample rate manually. (8000, 12000, 16000, 24000, 32000, 44000, *48000)
  --pix_fmt       :pass value:  set video codec pix_fmt (*yuv420p, yuv420p10, yuv420p10le, yuv422p, yuv444p)
  --vmin          :pass value:  set variable quality min (*1-33)
  --vmax          :pass value:  set variable quality max (1-*33)
  --fps           :pass value:  set frames per second (23.97, 24, *29.97, 30, 60, 120, etc)
  --scale         :pass value:  set the the downsize resolution height. Aspect auto retained. (480, 720, 1080, *2160, 4320, etc) WILL NOT UPSCALE!
  --quality       :pass value:  set the quality factor (0.5 => low, 1.0 => normal, 2.0 => high) *default 1.29
  --permissions   :pass value:  set the file permission
  \033[0m";


  $cmd_ln_opts = getopt(null, $options['args']['cmdlnopts'], $args['index']);

  if (array_key_exists("help", $cmd_ln_opts)) {
    print $help;
    exit;
  }
  if (array_key_exists("keys", $cmd_ln_opts)) {
    print "\n\033[01;32mDefined Keys in \033[01;34mhevc_paths.ini\033[0m";
    print "\n\033[01;31mDefined Locations:\033[0m\n";
    print_r($options['locations']);
    exit;
  }
  if (array_key_exists("yes", $cmd_ln_opts)) {
    $options['args']['yes'] = true;
  }
  if (array_key_exists("verbose", $cmd_ln_opts)) {
    $options['args']['verbose'] = true;
  }
  if (array_key_exists("force", $cmd_ln_opts)) {
    $options['args']['force'] = true;
  }
  if (array_key_exists("nomkvmerge", $cmd_ln_opts)) {
    $options['args']['skip'] = true;
  }
  if (array_key_exists("override", $cmd_ln_opts)) {
    $options['args']['override'] = true;
  }
  if (array_key_exists("exclude", $cmd_ln_opts)) {
    $options['args']['exclude'] = true;
  }
  if (array_key_exists("keeporiginal", $cmd_ln_opts)) {
    $options['args']['keeporiginal'] = true;
  }
  if (array_key_exists("language", $cmd_ln_opts)) {
    $options['args']['language'] = substr($cmd_ln_opts['language'], 0, 3);
  }
  if (array_key_exists("test", $cmd_ln_opts)) {
    $options['args']['test'] = true;
  }
  if (array_key_exists("filterforeign", $cmd_ln_opts)) {
    $options['args']['filter_foreign'] = true;
  }
  if (array_key_exists("abitrate", $cmd_ln_opts)) {
    $options['audio']['bitrate'] = $cmd_ln_opts['abitrate'];
  }
  if (array_key_exists("acodec", $cmd_ln_opts)) {
    $options['audio']['codec'] = $cmd_ln_opts['acodec'];
  }
  if (array_key_exists("achannels", $cmd_ln_opts)) {
    $options['audio']['channels'] = $cmd_ln_opts['achannels'];
  }
  if (array_key_exists("asamplerate", $cmd_ln_opts)) {
    $options['audio']['sample_rate'] = $cmd_ln_opts['asamplerate'];
  }
  if (array_key_exists("quality", $cmd_ln_opts)) {
    $options['video']['quality_factor'] = $cmd_ln_opts['quality'];
  }
  if (array_key_exists("vmin", $cmd_ln_opts)) {
    $options['video']['vmin'] = $cmd_ln_opts['vmin'];
  }
  if (array_key_exists("vmax", $cmd_ln_opts)) {
    $options['video']['vmax'] = $cmd_ln_opts['vmax'];
  }
  if (array_key_exists("fps", $cmd_ln_opts)) {
    $options['video']['fps'] = $cmd_ln_opts['fps'];
  }
  if (array_key_exists("scale", $cmd_ln_opts)) {
    $options['video']['scale'] = $cmd_ln_opts['scale'];
    if (empty($cmd_ln_opts['scale'])) {
      echo "usage: option --scale=[value]";
      exit;
    }
  }
  if (array_key_exists("pix_fmt", $cmd_ln_opts)) {
    $options['video']['pix_fmt'] = $cmd_ln_opts['pix_fmt'];
  }
  return($options);
}

function getXmlAttribute($object, $attribute) {
  return((string) $object[$attribute]);
}

function setXmlFormatAttribute($file, $attribute, $value=true) {
  $xml_file = "./.xml/" . $file['filename'] . ".xml";
  if (file_exists($xml_file)) {
    $xml = new SimpleXMLElement($xml_file, null, true);
    $xml->format->addAttribute("$attribute", $value);
    $xml->saveXML($xml_file);
  }
}

function formatBytes($bytes, $precision, $kbyte) {
  $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
  $bytes = (int) max($bytes, 0);

  if ($kbyte == true) {
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
  }
  else {
    $pow = floor(($bytes ? log($bytes) : 0) / log(1000));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1000, $pow);
  }
  return round($bytes, $precision) . ' ' . $units[$pow];
}

function getExtensions() {
  $extensions = array("mkv");
  return($extensions);
}

function cleanXMLDir($dir, $options) {
// clean xml for nonexist media
  global $cleaned;
  if (!in_array($dir, $cleaned)) {
    chdir($dir);
    if (is_dir("./.xml")) {
      if ($dh = opendir("./.xml")) {
        while (($xmlfile = readdir($dh)) !== false) {
          if (is_dir($xmlfile)) {
            continue;
          }
          $xfile = pathinfo($xmlfile);
          $mediafile = str_replace("'", "\'", $xfile['filename']) . $options['extension'];
          if (!empty($xmlfile) && !file_exists("$mediafile") && file_exists("./.xml" . DIRECTORY_SEPARATOR . "${xmlfile}")) {
            print "\033[01;34mCleaned XML for NonExists: $dir" . DIRECTORY_SEPARATOR . $mediafile . "\033[0m\n";
            unlink("./.xml" . DIRECTORY_SEPARATOR . $xmlfile);
          }
        }
      }
      array_push($cleaned, $dir);
    }
  }
}

function setOption($option) {
  $options['profile'] = $option[0];
  $options['format'] = $option[1];
  $options['extension'] = $option[2];
  $options['video']['codec'] = $option[3];
  $options['video']['pix_fmt'] = $option[4];
  $options['video']['codec_name'] = $option[5];
  return($options);
}

function strip_illegal_chars($file) {
  if (preg_match('/\'/', $file['filename'])) {
    rename($file['dirname'] . "/" . $file['basename'], $file['dirname'] . "/" . str_replace("'", "", ($file['filename'])) . "." . $file['extension']);
    $file = pathinfo($file['dirname'] . "/" . str_replace("'", "", ucwords($file['filename'])) . "." . $file['extension']);
  }
  return($file);
}

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
    $titlename = implode(' ', $title);
    rename($file['dirname'] . "/" . $file['basename'], $file['dirname'] . "/" . $titlename . "." . $file['extension']);
    $file = pathinfo($file['dirname'] . DIRECTORY_SEPARATOR . $titlename . "." . $file['extension']);
  }
  if (file_exists($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'])){
    chgrp($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $options['group']);
    chmod($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $options['args']['permissions']);
  }
  return($file);
}

function set_fileattr($file, $options) {
  if (($options['args']['keepowner'] || isset($options['owner']) ) && !$options['args']['timelock']) {
    if (file_exists($file['basename'])) {
      chown($file['basename'], $options['owner']);
      chgrp($file['basename'], $options['group']);
      chmod($file['basename'], $options['args']['permissions']);
    }
  }
}
