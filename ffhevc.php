#!/usr/bin/php
<?php
$VERSION = 20180812.1047;

//Initialization and Command Line interface stuff
$dirs = array();
$cleaned = array();
$args = array();

function getDefaultOptions($args) {
  $options = array();
  $HOME = getenv("HOME");
  $external_paths_file = "${HOME}/bin/hevc_paths.ini";
//Format: (don't end line with comma in external file)
  /* example hevc_paths.ini key locations configurations
   *
   *
    #"key" => "/path/to/video/library|audioK|vmin|vmax|quality_factor|scale|fps|contrast|brightness|saturation|gamma|/optional/destination/path"
    "gopro" => "/cygdrive/e/Videos/GoPro|128k|10|32|2|720|30|1.07|0.02|1.02|0.95"
    "goarchive" => "/cygdrive/e/Videos/GoPro/archived|64k|10|38|0.75|640|18|1|0|1|1"
    "tv" => "/cygdrive/e/TV_Shows|384k|1|38|0.75|720|25|1|0|1|1"
    "mov" => "/cygdrive/e/Movies|512k|1|36|0.75|2160|30|1|0|1|1"
    "newtv" => "/cygdrive/c/Temp/TV_Shows|384k|1|38|0.75|720|25|1|0|1|1|/cygdrive/e/TV_Shows"
    "new" => "/cygdrive/c/Temp/Movies|512k|1|36|0.75|2160|30|1|0|1|1|/cygdrive/e/Movies"
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
#"key" => "/path/to/video/library|audioK|vmin|vmax|quality_factor|scale|fps|contrast|brightness|saturation|gamma/optional/destination/path"
    $options['locations'] = array(
      "tv" => "/path/to/TV_Shows|384k|1|38|0.75|720|25|1|0|1|1|/optional/desitnation/path",
      "mov" => "/path/to/Movies|640k|1|33|0.9|2160|30|1|0|1|1|/optional/desitnation/path"
    );
  }

//Default configuration for video/audio encoding
#Presets for Plex Direct Play  (use nvenc if you have GTX-1060+ or GTX-960+ Card)
  $opt['H264'] = array("matroska", ".mkv", "libx264", "yuv420p", "avc");
  $opt['H264-nvenc'] = array("matroska", ".mkv", "h264_nvenc", "yuv420p", "avc");
  $opt['H265'] = array("matroska", ".mkv", "libx265", "yuv420p", "hevc");
  $opt['hevc-nvenc-mkv'] = array("matroska", ".mkv", "hevc_nvenc", "yuv420p", "hevc");
  $opt['hevc-nvenc-mp4'] = array("mp4", ".mp4", "hevc_nvenc", "yuv420p", "hevc");

//---EASY CONFIG SELECT---//
  $my_config = $opt['hevc-nvenc-mkv'];
//---EASY CONFIG SELECT---//
  $options = array_merge($options, setOption($my_config));

  /* Edit below settings to your preferences
   *
   *    NOTE: External Paths Config OVERRIDES THESE VALUES
   */
  $options['video']['quality_factor'] = 1.12;  //Range 0.02 to 3.00 (QualityBased: vmin-vmax overrides VBR Quality. Bitrate will not drop below VBR regardless of vmin-vmax settings)
  $options['video']['vmin'] = "1";
  $options['video']['vmax'] = "33";  // The lower the value, the higher the quality/bitrate
  $options['video']['fps'] = 29.97;
  $options['video']['scale'] = 2160;  // max video resolution setting
  $options['video']['contrast'] = 1;
  $options['video']['brightness'] = 0;
  $options['video']['saturation'] = 1;
  $options['video']['gamma'] = 1;

  $options['audio']['codecs'] = array("ac3");   //("aac", "ac3", "libopus", "mp3") allow these codesc (if bitrate is below location limits)
  $options['audio']['codec'] = "ac3";  // ("aac", "ac3", "libopus", "mp3")
  $options['audio']['channels'] = 6;
  $options['audio']['bitrate'] = "640k"; // default fallback maximum bitrate (bitrate should never be higher than this setting)
  $options['audio']['sample_rate'] = 48000;

  $options['args']['force'] = false;
  $options['args']['skip'] = false;
  $options['args']['override'] = false;
  $options['args']['keeporiginal'] = false; //if true, the original file will be retained. (renamed as filename.orig)
  $options['args']['keepowner'] = false;  // if true, the original file owner will be used in the new file.
  $options['args']['permissions'] = 0644; //Set file permission to (int value).  Set to False to disable.
  $options['args']['language'] = "eng";  // If language stream is specified, pull this one
  $options['args']['delay'] = 300; // File must be at least [delay] seconds old before being processes (set to zero to disable) Prevents process on file being assembled or moved.
  $options['args']['cooldown'] = 0; // used as a cool down period between processing - helps keep extreme systems for over heating when converting an enourmous library over night (on my liquid cooled system, continuous extreme load actually raises the water tempurature to a point where it compromises the systems ability to regulate tempurature.
  $options['args']['loglev'] = "info";  // [quiet, panic, fatal, error, warning, info, verbose, debug]
  $options['args']['threads'] = 0;
  $options['args']['subtitle_codecs'] = array(
    "ass" => "ass",
    "dks" => "dks",
    "jacosub" => "jss",
    "microdvd" => "sub",
    "mpl2" => "mpl",
    "mov_text" => "",
    "pjs" => "pjs",
    "realtext" => "rt",
    "sami" => "smi",
    "ssa" => "ssa",
    "srt" => "srt",
    "stl" => "stl",
    "stuctured" => "ssf",
//    "subrip" => "srt",
    "subviewer" => "sub",
    "subviewer1" => "sub",
    "svcd" => "svcd",
    "text" => "txt",
    "unviversal" => "usf",
    "vplayer" => "txt",
    "vobsub" => "idx",
  );

  //COMMAND LINE OPTIONS
  $shortopts = "";
  $options['args']['cmdlnopts'] = array(
    "help",
    "force",
    "nomkvmerge",
    "override",
    "keeporiginal",
    "keepowner",
    "permissions",
    "language::",
    "abitrate::",
    "acodec::",
    "achannels::",
    "asamplerate::",
    "vbr::",
    "vmin::",
    "vmax::",
    "fps::",
    "scale::",
    "quality::"
  );
  $help = "
  \033[01;32mBOOLS (Default false)\033[01;34m
  --force         :flag:        force encoding and bypass verification checks and delays
  --override      :flag:        reencode and override existing files (redo all existing regardless)
  --nomkvmerge    :flag:        do not restructure MKV container with mkvmerge before encoding (if installed and in PATH)
  --keeporiginal  :flag:        keep the original file and save as filename.ext.orig
  --keepowner     :flag:        keep the original file owner for the newly created file

  \033[01;32mPARMS (Default * denoted)\033[01;34m
  --language      :pass value:  manual set at command-line Use 3 letter lang codes. (*eng, fre, spa, etc.. etc..)
  --abitrate      :pass value:  set audio bitrate manually. Use rates compliant with configured audio codec 128k, 192k, 256k, *384k 512k
  --acodec        :pass value:  set audio codec manually.  (aac, *ac3, libopus, mp3, etc...)
  --achannels     :pass value:  set audio channels manually.  (1, 2, 6, 8) 1=mono, 2=stereo, *6=5.1, 8=7.1
  --asamplerate   :pass value:  set audio sample rate manually. (8000, 12000, 16000, 24000, 32000, 44000, *48000)
  --vmin          :pass value:  set variable quality min (*1-33)
  --vmax          :pass value:  set variable quality max (1-*33)
  --fps           :pass value:  set frames per second (23.97, 24, *29.97, 30, 60, 120, etc)
  --scale         :pass value:  set the the downsize resolution height. Aspect auto retained. (480, 720, 1080, *2160, 4320, etc) WILL NOT UPSCALE!
  --quality       :pass value:  set the quality factor (0.5 => low, 1.0 => normal, 2.0 => high) *default 1.12
  --permissions   :pass value:  set the file permission
  \033[0m";

  $cmd_ln_opts = getopt($shortopts, $options['args']['cmdlnopts']);
  if (array_key_exists("help", $cmd_ln_opts)) {
    print $help;
    exit;
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
  if (array_key_exists("keeporiginal", $cmd_ln_opts)) {
    $options['args']['keeporiginal'] = true;
  }
  if (array_key_exists("language", $cmd_ln_opts)) {
    $options['args']['language'] = substr($cmd_ln_opts['language'], 0, 3);
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
  }
  return($options);
}

//Only run one instance of this script!
$proc = "ffmpeg-hevc_nvenc";
$lock = proclock($proc);
$options = getDefaultOptions($args);

//Some pre-processing...
if (isset($argv[1]) && in_array(str_replace("--", "", $argv[1]), $options['args']['cmdlnopts'])) {
  unset($argv[1]);
}
if (isset($argv[1])) {
  $args['key'] = $argv[1];
  if (is_file($args['key'])) {
    $file = pathinfo($args['key']);
    $argv[2] = "force";
    $options = getLocationOptions($options, $args, $file['dirname']);
    if (isset($argv[2])) {
      if ($argv[2] == true || $argv[2] === "force") {
        $options['args']['force'] = true;
      }
    }
    processItem($file['dirname'], $file['basename'], $options, $args);
    exit;
  }

  if (isset($args['key']) && isset($options['locations'][$args['key']])) {
    $location = str_replace(" ", "\ ", $options['locations'][$args['key']]);
    $key = $args['key'];
    $dirs["$key"] = explode("|", $location)[0];
    $options = getLocationOptions($options, $args, explode("|", $location)[0]);
  }
  else {
    print "\n\033[01;31mDefined Locations:\033[0m\n";
    print_r($options['locations']);
    exit("\033[01;31m Unknown location: \"$argv[1]\"\n Edit \$options['locations'] to add it OR create and define external_paths_file.ini within the script.\033[0m");
  }
}
else {
  $dir = getcwd();
  $options = getDefaultOptions($args);
  $options = getLocationOptions($options, $args, $dir);
  $args['key'] = null;
  if (array_key_exists("key", $options['args'])) {
    $args['key'] = $options['args']['key'];
  }

  $dirs = array($args['key'] => $dir);
}


/* ----------MAIN------------------ */
foreach ($dirs as $key => $dir) {
  processRecursive($dir, $options, $args);
}

//procunlock($lock, $proc);


/* ## ## ## STATIC FUNCTIONS ## ## ## */

function processRecursive($dir, $options, $args) {
  $result = array();
  $list = array_slice(scandir("$dir"), 2);

  foreach ($list as $index => $item) {
    global $proc;
    proccheck($proc);
    if (is_dir($dir . DIRECTORY_SEPARATOR . $item)) {
      if (!preg_match("/_UNPACK_/", $item)) {
        cleanXMLDir($dir, $options);
        $result["$dir" . DIRECTORY_SEPARATOR . "$item"] = processRecursive($dir . DIRECTORY_SEPARATOR . $item, $options, $args);
      }
    }
    else {
      processItem($dir, $item, $options, $args);
    }
  }
}

function processItem($dir, $item, $options, $args) {
  $extensions = getExtensions();
  $file = strip_illegal_chars(pathinfo("$dir" . DIRECTORY_SEPARATOR . "$item"));
  if (!isset($file['extension'])) {
    return;
  }
  if (!in_array(strtolower($file['extension']), $extensions)) {
    return;
  }
  $curdir = getcwd();
  chdir($file['dirname']);

# Process Item
  $options['info']['title'] = "'" . str_replace("'", "\'", $file['filename']) . "'";
  $options['info']['timestamp'] = date("Ymd.His");

  $info = ffprobe($file, $options);

  $options = ffanalyze($info, $options, $args, $dir, $file);
  if (empty($options)) {
    return;
  }

// check the file's timestamp (Do not process if file time is too new. Still unpacking?)
  if (filemtime($file['basename']) > time()) {
    touch($file['basename'], time()); //file time is in the future (created overseas?), set it to current time.
    return;
  }
  elseif ((filemtime($file['basename']) + $options['args']['delay']) > time() && filemtime($file['basename']) <= time() && !$options['args']['force']) {
    print $file['basename'] . " modified < " . $options['args']['delay'] . " seconds ago.";
    return;
  }

  //Preprocess with mkvmerge (if in path)
  if (`which mkvmerge` && !$options['args']['skip']) {
    $cmdln = "mkvmerge -v -s '" . $options['args']['language'] . "'" .
            " --language 0:" . $options['args']['language'] .
            " --language 1:" . $options['args']['language'] .
            " --track-order 0:0,0:1,0:2" .
            " -o '" . $file['filename'] . ".mkvm' '" . $file['basename'] . "'";
    print "\n\n\033[01;32m${cmdln}\033[0m\n";
    system("${cmdln} 2>&1");
    if (file_exists($file['filename'] . ".mkvm")) {
      $mtime = filemtime($file['basename']);
      rename($file['filename'] . ".mkvm", $file['basename']);
      touch($file['basename'], $mtime); //retain original timestamp
    }
  }

  if (!isset($options['args']['video'])) {
    $options['args']['video'] = "-vcodec copy";
  }

# CONVERT MEDIA
  $cmdln = "nice -n1 ffmpeg -v " .
          $options['args']['loglev'] . " " .
          "-i \"" . $file['basename'] . "\" " .
          "-threads " . $options['args']['threads'] . " " .
          "-f " . $options['format'] . " " .
          $options['args']['video'] . " " .
          $options['args']['audio'] . " " .
          $options['args']['subs'] . " " .
          $options['args']['map'] . " " .
          $options['args']['meta'] . " " .
          "-y \"" . $file['filename'] . ".hevc\"";

  print "\n\n\033[01;32m${cmdln}\033[0m\n\n";
  exec("${cmdln}");

#Swap Validate
  if ($options['args']['keepowner']) {
    $options['owner'] = fileowner($file['basename']);
  }
  rename($file['basename'], $file['filename'] . "." . $file['extension'] . ".orig");
  $fileorig = $file;
  if (file_exists("./.xml/" . $file['filename'] . ".xml")) {
    unlink("./.xml/" . $file['filename'] . ".xml");
  }
  rename($file['filename'] . ".hevc", $file['filename'] . $options['extension']);
  $file = pathinfo("$dir" . DIRECTORY_SEPARATOR . $file['filename'] . $options['extension']);
  set_permissions($file, $options);
  restore_owner($file, $options);
  $info = ffprobe($file, $options);


#Validate
  if (!$options['args']['keeporiginal']) {
    $exclude = false;
    $mtime = filemtime($fileorig['filename'] . "." . $fileorig['extension'] . ".orig");
    if (
            (
            ($info['format']['probe_score'] == 100) &&
            ($info['format']['size'] < (filesize($fileorig['filename'] . "." . $fileorig['extension'] . ".orig")))
            ) || ($options['args']['force'])
    ) {
      echo "\033[01;34mSTAT: " . $file['filename'] . $options['extension'] . " ( " . formatBytes(filesize($fileorig['filename'] . "." . $fileorig['extension'] . ".orig"), 2, true) . " [orig] - " . formatBytes($info['format']['size'], 2, true) . " [new] = \033[01;32m" . formatBytes((filesize($fileorig['filename'] . "." . $fileorig['extension'] . ".orig") - $info['format']['size']), 2, true) . "\033[01;34m [diff] )\033[0m\n";
      unlink($fileorig['filename'] . "." . $fileorig['extension'] . ".orig");
      if (file_exists($file['filename'] . $options['extension'])) {
        touch($file['filename'] . $options['extension'], $mtime); //retain original timestamp
        if (isset($options['args']['destination'])) {
          //move file to destination path defined in (external_ini_file)
          print "\033[01;32mMOVING: " . $file['filename'] . $options['extension'] . " to " . $options['args']['destination'] . "\033[0m\n";
          rename($file['filename'] . $options['extension'], $options['args']['destination'] . DIRECTORY_SEPARATOR . $file['filename'] . $options['extension']);
        }
      }
    }
    else {
      print "Rollback: " . $file['basename'] . "\n";
      if ($info['format']['probe_score'] != 100) {
        print "error:  probe_score " . $info['format']['probe_score'] . "\n";
      }
      if ($info['format']['size'] < filesize($fileorig['filename'] . "." . $fileorig['extension'] . ".orig")) {
        print "error:: original filesize is smaller\n";
      }
      $exclude = true;
      if (file_exists($file['filename'] . ".hevc")) {
        unlink($file['filename'] . ".hevc");
      }
      if (file_exists($file['filename'] . "." . $options['extension'])) {
        unlink($file['filename'] . "." . $options['extension']);
      }
      rename($file['filename'] . "." . $file['extension'] . ".orig", $file['basename']);
      if (file_exists("./.xml/" . $file['filename'] . ".xml")) {
        unlink("./.xml/" . $file['filename'] . ".xml");
        $info = ffprobe($file, $options);
        setXmlAttributeExclude($file);
      }
    }
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
//bad info? skip it.
    $options = array();
    return($options);
  }

  if (isset($info['format']['exclude']) && $info['format']['exclude'] == "1" && !$options['args']['force']) {
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
    $options['video']['bps'] = (round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) * 1000);

    if (
            preg_match(strtolower("/$codec_name/"), $info['video']['codec']) &&
            ($info['video']['pix_fmt'] == $options['video']['pix_fmt']) &&
            ($info['video']['height'] <= $options['video']['scale']) &&
            ($info['video']['bitrate'] <= $options['video']['bps']) &&
            (!$options['args']['override'])
    ) {
      $options['args']['video'] = "-vcodec copy";
    }
    if (!preg_match("/copy/i", $options['args']['video'])) {
      print "\033[01;32mVideo Inspection ->\033[0m" .
              $info['video']['codec'] . ":" . $options['video']['codec_name'] . "," .
              $info['video']['pix_fmt'] . "=" . $options['video']['pix_fmt'] . "," .
              $info['video']['height'] . "=" . $options['video']['scale'] . "," .
              $info['video']['bitrate'] . "<=" . ( $options['video']['bps'] ) . "," .
              $options['video']['quality_factor'] . "," . $options['args']['override'] . "\n";

      list($ratio_w, $ratio_h) = explode(":", $info['video']['ratio']);
      if ($info['video']['height'] > $options['video']['scale']) {
        //hard set info to be used for bitrate calculation based on scaled resolution
        $info['video']['width'] = (($info['video']['width'] * $options['video']['scale']) / $info['video']['height']);
        $info['video']['height'] = $options['video']['scale'];
        $scale_option = ", scale=-1:" . $options['video']['scale'];
        //Recalculate target video bitrate based on projected output scale
        $options['video']['vps'] = round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) . "k";
        $options['video']['bps'] = (round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) * 1000);
      }
      else {
        $scale_option = "";
      }

      if ($info['video']['fps'] > $options['video']['fps']) {
        $fps_option = " -r " . $options['video']['fps'];
      }
      else {
        $fps_option = "";
        $options['video']['fps'] = $info['video']['fps'];
      }
      $options['args']['video'] = "-vcodec " . $options['video']['codec'] .
              " -vb " . $options['video']['vps'] .
              " -qmin " . $options['video']['vmin'] .
              " -qmax " . $options['video']['vmax'] .
              " -pix_fmt " . $options['video']['pix_fmt'] .
              $fps_option .
              " -vsync 1 " .
              " -vf \"eq=" .
              "contrast=" . $options['video']['contrast'] .
              ":brightness=" . $options['video']['brightness'] .
              ":saturation=" . $options['video']['saturation'] .
              ":gamma=" . $options['video']['gamma'] .
              "$scale_option\"";


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
    $options['args']['map'] .= "-map 0:" . $info['video']['index'];
  }

//Audio
  if ($options['audio']['bitrate'] == 0) {
    $info['audio'] = null;
  }
  if (!empty($info['audio'])) {
    if (
            in_array($info['audio']['codec'], $options['audio']['codecs']) &&
            (int) $info['audio']['bitrate'] <= ((filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000)) &&
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
      $options['args']['meta'] .= " -metadata:s:a:0 language=eng " .
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
              $info['audio']['bitrate'] . "<=" . (filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000) . "," .
              $options['args']['override'];

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
      if ($info['audio']['channels'] < $options['audio']['channels']) {
        $options['audio']['channels'] = $info['audio']['channels'];
      }
      if ($info['audio']['sample_rate'] < $options['audio']['sample_rate']) {
        $options['audio']['sample_rate'] = $info['audio']['sample_rate'];
      }
      //Set audio args
      $options['args']['audio'] = "-acodec " . $options['audio']['codec'] .
              " -ac " . $options['audio']['channels'] .
              " -ab " . $options['audio']['bitrate'] .
              " -ar " . $options['audio']['sample_rate'] .
              " -async 1";
      $options['args']['meta'] .= " -metadata:s:a:0 language=eng " .
              " -metadata:s:a:0 codec_name=" . $options['audio']['codec'] .
              " -metadata:s:a:0 channels=" . $options['audio']['channels'] .
              " -metadata:s:a:0 bit_rate=" . $options['audio']['bitrate'] .
              " -metadata:s:a:0 sample_rate=" . $options['audio']['sample_rate'] .
              " -metadata:s:a:0 bps=" . $options['audio']['bps'] .
              " -metadata:s:a:0 title= ";
    }
    $options['args']['map'] .= " -map 0:" . $info['audio']['index'];
  }
  else {
    $options['args']['audio'] = "-acodec copy";
  }

//Subtexts
  $options['args']['subs'] = "-scodec copy";
  $options['args']['map'] .= " -map 0:s? ";

//Clear Old Tags
  $keep_vtags = array(
    "bps",
    "bit_rate",
    "duration",
    "creation_date",
    "language",
    "codec_name",
  );
  $keep_atags = array(
    "title",
    "duration",
    "creation_date",
    "language",
    "codec_name",
    "channels",
    "sample_rate",
    "bit_rate",
    "bps",
  );

  if (!empty($info['vtags'])) {
    foreach ($info['vtags'] as $vtag) {
      if (!empty($vtag) && !in_array(strtolower($vtag), $keep_vtags)) {
        $options['args']['meta'] .= " -metadata:s:v:0 ${vtag}=";
      }
    }
  }
  if (!empty($info['atags'])) {
    foreach ($info['atags'] as $atag) {
      if (!empty($atag) && !in_array(strtolower($atag), $keep_atags)) {
        $options['args']['meta'] .= " -metadata:s:a:0 ${atag}=";
      }
    }
  }

  if (
          (preg_match("/copy/i", $options['args']['video'])) &&
          (preg_match("/copy/i", $options['args']['audio'])) &&
          ('.' . $file['extension'] == $options['extension'])
  ) {
    $options = array();
  }

//safetynet
  if (empty($options['video'])) {
    $options = array();
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
  if (!file_exists("./.xml/" . $xmlfile)) {
    $action = "PROBED";
    $cmdln = "ffprobe $exec_args '$basename' > './.xml/${xmlfile}'";
    $result = exec("$cmdln");
    if (!file_exists("./.xml/${xmlfile}")) {
      print "error: Could not create ffprobe xml.  Check that ffprobe is exists, is executable, and in the system search path\n";
      exit(1);
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
  $info['format']['bitrate'] = getXmlAttribute($xml->format, "bit_rate");
  $info['format']['nb_streams'] = getXmlAttribute($xml->format, "nb_streams");
  $info['format']['probe_score'] = getXmlAttribute($xml->format, "probe_score");
  $info['format']['exclude'] = getXmlAttribute($xml->format, "exclude");
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
            foreach ($stream->tag as $tag) {
              $tag_key = strtolower(getXmlAttribute($tag, "key"));
              $tag_val = strtolower(getXmlAttribute($tag, "value"));
              $vtags[] = $tag_key;
              switch ($tag_key) {
                case (preg_match('/^bps*/', $tag_key)):
                  $info['video']['bitrate'] = (int) $tag_val;
                  break;
                case (preg_match('/^bit*rate$/', $tag_key)):
                  if (preg_match("/k/i", $tag_val)) {
                    $info['video']['bitrate'] = (int) (filter_var($tag_val, FILTER_SANITIZE_NUMBER_INT) * 1000);
                  }
                  else {
                    $info['video']['bitrate'] = (int) $tag_val;
                  }
                  break;
              }
            }
            $info['vtags'] = $vtags;
          }
          break;
        case "audio":
          if (empty($info['audio'])) {
            $atags = array();
            $info['audio']['index'] = getXmlAttribute($stream, "index");
            $info['audio']['codec_type'] = getXmlAttribute($stream, "codec_type");
            $info['audio']['codec'] = getXmlAttribute($stream, "codec_name");
            $info['audio']['channels'] = getXmlAttribute($stream, "channels");
            $info['audio']['sample_rate'] = getXmlAttribute($stream, "sample_rate");
            $info['audio']['bitrate'] = getXmlAttribute($stream, "bit_rate");

            foreach ($stream->tag as $tag) {
              $tag_key = strtolower(getXmlAttribute($tag, "key"));
              $tag_val = strtolower(getXmlAttribute($tag, "value"));
              $atags[] = $tag_key;
//              print $tag_key . " : " . $tag_val . "\n";
              switch ($tag_key) {
                case (preg_match('/^bps*/', $tag_key)):
                  if (empty($info['audio']['bitrate'])) {
                    $info['audio']['bitrate'] = $tag_val;
                  }
                  break;
                case (preg_match('/^bit*rate$/', $tag_key)):
                  if (empty($info['audio']['bitrate'])) {
                    if (preg_match("/k/i", $tag_val)) {
                      $info['audio']['bitrate'] = (int) (filter_var($tag_val, FILTER_SANITIZE_NUMBER_INT) * 1000);
                    }
                    else {
                      $info['audio']['bitrate'] = (int) $tag_val;
                    }
                  }
                  break;
                case "language":
                  if ($tag_val !== $options['args']['language']) {
                    $info['audio'] = array();
                    break 2;
                  }
                  else {
                    $info['audio']['language'] = $tag_val;
                  }
                  break 2;
              }
            }
            //rebuild atags (.. break 2)
            foreach ($stream->tag as $tag) {
              $atags[] = strtolower(getXmlAttribute($tag, "key"));
            }
            $info['atags'] = $atags;
          }
          break;
        case "subtitle":
          $info['subtitle']['index'] = getXmlAttribute($stream, "index");
          $info['subtitle']['codec_type'] = getXmlAttribute($stream, "codec_type");
          $info['subtitle']['codec'] = getXmlAttribute($stream, "codec_name");
          break;
      }
    }
  }
  if (
          $action != "INFO" &&
          isset($info['video']) &&
          isset($info['audio'])
  ) {
    print "\033[01;34m${action}: " . $file['filename'] . " (";
    if (!empty($info['video'])) {
      print $info['video']['codec_type'] . ":" . $info['video']['codec'] . ", " . $info['video']['width'] . "x" . $info['video']['height'] . ", " . formatBytes($info['video']['bitrate'], 2, false) . "PS | ";
    }
    if (!empty($info['audio'])) {
      print $info['audio']['codec_type'] . ":" . $info['audio']['codec'] . ", CH." . $info['audio']['channels'] . ", " . formatBytes($info['audio']['bitrate'], 2, false) . "PS";
    }
    if (!empty($info['subtitle'])) {
      print "SUB: " . $info['subtitle']['codec_type'] . ":" . $info['subtitle']['codec'];
    }
    print "\033[01;34m)\033[0m\n";
  }
  return($info);
}

function getAudioBitrate($options, $args) {
  if (!empty($args['key']) && array_key_exists($args['key'], $options['locations'])) {
    $options['audio']['bitrate'] = explode("|", $options['locations'][$args['key']])[1];
  }
  return($options);
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
      $options['audio']['bitrate'] = $location_param_array[1];
      $options['video']['vmin'] = $location_param_array[2];
      $options['video']['vmax'] = $location_param_array[3];
      $options['video']['quality_factor'] = $location_param_array[4];
      $options['video']['scale'] = $location_param_array[5];
      $options['video']['fps'] = $location_param_array[6];
      $options['video']['contrast'] = $location_param_array[7];
      $options['video']['brightness'] = $location_param_array[8];
      $options['video']['saturation'] = $location_param_array[9];
      $options['video']['gamma'] = $location_param_array[10];
      if (array_key_exists(11, $location_param_array)) {
        $options['args']['destination'] = $location_param_array[11];
      }
    }
  }
  $options = getAudioBitrate($options, $args);
  return($options);
}

function getXmlAttribute($object, $attribute) {
  return((string) $object[$attribute]);
}

function setXmlAttributeExclude($file) {
  $xml_file = "./.xml/" . $file['filename'] . ".xml";
  if (file_exists($xml_file)) {
    $xml = new SimpleXMLElement($xml_file, null, true);
    $xml->format->addAttribute("exclude", true);
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
  $extensions = array("mkv", "mp4", "mv4", "m4v", "avi", "mov", "ts", "wmv");
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
          if (!file_exists("$mediafile")) {
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
  $options['format'] = $option[0];
  $options['extension'] = $option[1];
  $options['video']['codec'] = $option[2];
  $options['video']['pix_fmt'] = $option[3];
  $options['video']['codec_name'] = $option[4];
  return($options);
}

function proclock($procname) {
//This prevents multiple instances of this script from running.
  $lockfile = "/tmp/${procname}.lock";
  if (file_exists($lockfile)) {
    if (filesize($lockfile) === 0) {
      unlink($lockfile);
    }
  }
  $lock = fopen("$lockfile", 'wr+');
  if (!flock($lock, LOCK_EX | LOCK_NB)) {
    $lockdate = file_get_contents($lockfile);
    if (($lockdate + 28800) > time()) {  // if process has been running for 8 hours let it finish and die.
      fclose($lock);
      unlink($lockfile);
      exit("\nStale lock file detected ${lockfile}");
    }
    else {
      exit("\nAlready running: file lock exists ${lockfile}");
    }
  }
  else {
    fwrite($lock, time());
  }
  return($lock);
}

function strip_illegal_chars($file) {
  if (preg_match('/\'/', $file['filename'])) {
    rename($file['dirname'] . "/" . $file['basename'], $file['dirname'] . "/" . str_replace("'", "", $file['filename']) . "." . $file['extension']);
    $file = pathinfo($file['dirname'] . "/" . str_replace("'", "", $file['filename']) . "." . $file['extension']);
  }
  return($file);
}

function proccheck($procname) {
//This is a safety mechanism that I use to halt the process after the current conversion is complete.
//To use it, delete the lock file in /tmp and the process will halt after the current operation is done.
  $lockfile = "/tmp/${procname}.lock";
  if (!file_exists($lockfile)) {
    exit("Process Halted: $lockfile missing");
  }
}

function procunlock($lock, $procname) {
  fclose($lock);
  $lockfile = "/tmp/${procname}.lock";
  unlink($lockfile);
}

function restore_owner($file, $options) {
  if ($options['args']['keepowner'] && isset($options['owner'])) {
    chown($file['basename'], $options['owner']);
  }
}

function set_permissions($file, $options) {
  if ($options['args']['permissions']) {
    chmod($file['basename'], $options['args']['permissions']);
  }
}
