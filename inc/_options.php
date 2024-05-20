<?php
/**
 *   input: option
 *   output: options
 *   purpose: Set options using engine_config setting in conf/default.php or configured profile defined
 * 
 */

/** ---------------------------------------------------------------------------------------------------- */
function get_CommandLineArgs($options, $argv, $args, $global)
{

  if (count($argv) > 1) {
    $args['index'] = $i = 0;
    foreach ($argv as $key => $arg) {      
      if (is_file($arg)) {
        $file = pathinfo("." . DIRECTORY_SEPARATOR . "$arg");
        if ($file['filename'] == $args['application']) {
          unset($file);
        } 
      } elseif (preg_match('/^--/', $arg)) {
        $args[] = $arg;
        if (!$args['index']) {
          $args['index'] = $i;
          if ($args['index'] > 1) {
            exit(ansiColor("red") . "\n  Invalid first option: \"$arg $i\"\n" . ansiColor() . "  e.g. $>" . ansiColor("blue") . $args['application'] . " --help\n" . ansiColor());
          }
        }
      } elseif (preg_match('/^-/', $arg)) {
        exit("\n" . ansiColor("red") . "  Unknown option: \"$arg\"\n" . ansiColor() . "  Type $>" . ansiColor("green") . $args['application'] . " --help\n" . ansiColor());
      } else {
        if (array_key_exists($arg, $options['locations'])) {
          $args['key'] = $arg;
        }
      }
      $i++;
    }
    //Single File Processing
    if (isset($file)) {
      $options = getLocationOptions($options, $args, $file['dirname']);
      isStopped($options, true);
      $global  = processItem($file['dirname'], $file['basename'], $options, $args, $global);
      cleanXMLDir($file['dirname'], $options, true);
      showStats($global);
      exit;
    }

    //Defined Key, Scan, and Process
    if (isset($args['key'])) {
      $location     = str_replace(" ", "\ ", $options['locations'][$args['key']]);
      $key          = $args['key'];
      $dirs["$key"] = explode("|", $location)[0];
      $options      = getLocationOptions($options, $args, explode("|", $location)[0]);
    } elseif ($args['index'] > 0) {
      $dir     = getcwd();
      $options = getLocationOptions($options, $args, $dir);
      if (!empty($options['args']) && array_key_exists("key", $options['args'])) {
        $args['key'] = $options['args']['key'];
      } else {
        $dirs = array("key" => $dir);
      }
    } else {
      print "\n" . ansiColor("yellow") . "Defined Locations:" . ansiColor() . "\n";
      print_r($options['locations']);
      exit(ansiColor("red") . "  Unknown location: \"$argv[1]\"\n" . ansiColor("green") . "  Edit \$options['locations'] to add it OR create and define media_paths_file within the script." . ansiColor());
    }
  } else {
    //no arguments, use current dir to parse location settings
    $dir         = getcwd();
    $options     = getLocationOptions($options, $args, $dir);
    $args['key'] = null;
    if (!empty($options['args']) && array_key_exists("key", $options['args'])) {
      $args['key'] = $options['args']['key'];
    }
    $dirs = array($args['key'] => $dir);
  }
  if (!isset($dirs)) {
    $dirs = array(".");
  }
  return array($options, $args, $dirs, $global);
}

/** ---------------------------------------------------------------------------------------------------- */
function getCommandLineOptions($options, $args)
{
  //COMMAND LINE OPTIONS
  $help =
    ansiColor("blue") . "command [options] [file|key]  // options and flags before file or defined key\n" .
    ansiColor("yellow") . "e.g.   $> " . $args['application'] . " --test \"\{MediaFileName\}\"\n" .
    ansiColor("yellow") . "e.g.   $> " . $args['application'] . " --keeporiginal tv\n\n" .
    ansiColor("green") . "BOOLS (Default false)" .
    ansiColor("blue") . "
    --verbose    :flag:        display verbose output
    --test          :flag:        print out ffmpeg generated command line only -- and exit
    --keys          :flag:        print out the defined keys -- and exit
    --force         :flag:        force encoding and bypass verification checks and delays
    --override      :flag:        reencode and override existing files (redo existing regardless)
    --exclude       :flag:        exclude from being processed (ignore this video), stored in .xml
    --nomkvmerge    :flag:        do not restructure MKV container with mkvmerge before encoding (if installed and in PATH)
    --norename      :falg:        do not rename files
    --keeporiginal  :flag:        keep the original file and save as filename.orig.ext
    --filterforiegn :flag:        strip foriegn languages NOT matching \$options['args']['language'] OR --language\n\n" .

    ansiColor("green") . "PARMS (Default * denoted) i.e.  --language=eng" .
    ansiColor("blue") . "
    --language      :pass value:  manual set at command-line Use 3 letter lang codes. (*eng, fre, spa, etc.. etc..)
    --abitrate      :pass value:  set audio bitrate manually. Use rates compliant with configured audio codec 128k, 192k, 256k, *384k, 512k
    --acodec        :pass value:  set audio codec manually.  (*aac, ac3, libopus, mp3, etc... | none)
    --achannels     :pass value:  set audio channels manually.  (1, 2, *6, 8) 1=mono, 2=stereo, *6=5.1, 8=7.1
    --asamplerate   :pass value:  set audio sample rate manually. (8000, 12000, 16000, 24000, 32000, 44000, *48000)
    --audioboost    :pass value:  set the dB boost level (1dB, 5dB, 10dB, 15dB.. etc) // must end with 'dB' || 0 to disable
    --pix_fmt       :pass value:  set video codec pix_fmt (*yuv420p, yuv420p10, yuv420p10le, yuv422p, yuv444p)
    --vmin          :pass value:  set variable quality min (*1-33)
    --vmax          :pass value:  set variable quality max (1-*33)
    --fps           :pass value:  set frames per second (23.97, 24, *29.97, 30, 60, 120, etc)
    --scale         :pass value:  set the the downsize resolution height. Aspect auto retained. (480, 720, 1080, *2160, 4320, etc) WILL NOT UPSCALE!
    --quality       :pass value:  set the quality factor (0.5 => low, 1.0 => normal, 2.0 => high) *default 1.29
    --permissions   :pass value:  set the file permission\n\n" .

    ansiColor("green") . "TOOLS " . 
    ansiColor("blue") . "
    --rename        :flag:        rename existing files to Plex Naming Standards (bypasses encoding, rename in settings renames encoded files)
    --search        :pass value:  search for \"keyword(s)\" in your media_path_keys (e.g.  --search=\"Marvels\")
    " . ansiColor();


  $cmd_ln_opts = getopt(null, $options['args']['cmdlnopts'], $args['index']);

  if (array_key_exists("help", $cmd_ln_opts)) {
    print $help;
    exit;
  }
  if (array_key_exists("keys", $cmd_ln_opts)) {
    print ansiColor("green") . "\nDefined Keys in " . ansiColor("blue") . "conf/media_paths_keys.php\n" . ansiColor();
    print ansiColor("red") . "Defined Locations:\n" . ansiColor();
    print_r($options['locations']);
    exit;
  }
  if (array_key_exists("verbose", $cmd_ln_opts)) {
    $options['args']['verbose'] = true;
  }
  if (array_key_exists("force", $cmd_ln_opts)) {
    $options['args']['force'] = true;
  }
  if (array_key_exists("nomkvmerge", $cmd_ln_opts)) {
    $options['args']['nomkvmerge'] = true;
  }
  if (array_key_exists("norename", $cmd_ln_opts)) {
    $options['args']['rename'] = false;
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
  if (array_key_exists("rename", $cmd_ln_opts) && !array_key_exists("norename", $cmd_ln_opts)) {
    $options['args']['rename'] = 2;
    $options['args']['toolopt'] = true;
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
  if (array_key_exists("audioboost", $cmd_ln_opts)) {
    $options['args']['audioboost'] = !empty($cmd_ln_opts['audioboost']) ? $cmd_ln_opts['audioboost'] : false;
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
      print "usage: --scale=[value]\n";
      exit;
    }
  }
  if (array_key_exists("search", $cmd_ln_opts)) {
    $options['args']['search'] = $cmd_ln_opts['search'];
    $options['args']['toolopt'] = true;    
    if (empty($cmd_ln_opts['search'])) {
      print "usage: --search=[keyword(s)]\n";
      exit;
    }
  }
  if (array_key_exists("pix_fmt", $cmd_ln_opts)) {
    $options['video']['pix_fmt'] = $cmd_ln_opts['pix_fmt'];
  }
  return ($options);
}

/** ---------------------------------------------------------------------------------------------------- */
function getDefaultOptions($args, $location_config)
{
  $options              = array();
  $options['locations'] = $location_config;
  $option               = $args['opt'][$args['engine_config']];
  $options              = array_merge($options, setOption($option));

  /* Edit below settings to your preferences (BEWARE! Very Advanced)
   *    NOTE: conf/media_paths_keys OVERRIDE THESE VALUES
   */
  // arg=value i.e: `--quality_factor=1.5`     (higher quaility = higher bit rate)
  $options['video']['quality_factor']        = !empty($args['quality_factor']) ? $args['quality_factor'] : 1.33;
  $options['video']['vmin']                  = !empty($args['video_qmin']) ? $args['video_qmin'] : 0;
  $options['video']['vmax']                  = !empty($args['video_qmax']) ? $args['video_qmax'] : 29;
  $options['video']['fps']                   = !empty($args['video_fps']) ? $args['video_fps'] : 29.97;
  $options['video']['scale']                 = !empty($args['video_max_scale']) ? $args['video_max_scale'] : 2160;  // max video resolution setting
  $options['video']['contrast']              = 1;
  $options['video']['brightness']            = 0;
  $options['video']['saturation']            = 1;
  $options['video']['gamma']                 = 1;
  $options['video']['hdr']['color_range']    = array("tv"); // "tv", "pc"
  $options['video']['hdr']['codec']          = "hevc_nvenc";  // = "libx265" If you're video card does not support HDR;
  $options['video']['hdr']['pix_fmt']        = array("p010le", "p010le", "p010le");
  $options['video']['hdr']['color_primary']  = array("bt601|", "bt709", "bt2020");
  $options['video']['hdr']['color_transfer'] = array("bt601", "bt709", "smpte2084");
  $options['video']['hdr']['color_space']    = array("bt601", "bt709", "bt2020nc");
  $options['video']['profile']               = !empty($args['video_profile']) ? $args['video_profile'] : "WEBDL";
  $options['audio']['codec']       = !empty($args['audio_codec']) ? $args['audio_codec'] : "eac3";
  $options['audio']['codecs']      = !empty($args['audio_codecs']) ? $args['audio_codecs'] : array("ac3", "eac3");
  $options['audio']['channels']    = !empty($args['audio_channels']) ? $args['audio_channels'] : 6;
  $options['audio']['bitrate']     = !empty($args['audio_bitrate']) ? $args['audio_bitrate'] : "720k";
  $options['audio']['sample_rate'] = !empty($args['audio_sample_rate']) ? $args['audio_sample_rate'] : 48000;
  $options['args']['audioboost']           = !empty($args['audio_boost']) ? $args['audio_boost'] : false;
  $options['args']['extension']            = !empty($args['extension']) ? $args['extension'] : "mkv";
  $options['args']['extensions']           = array("mkv", "mp4", "mpeg", "m4v", "ts", "m2ts", "avi", "av1", "3gp", "flv", "fv4", "ogv", "webm", "wmv", "mov", "vob");  // acceptable formats to convert/encode
  $options['args']['maxmuxqueuesize']      = !empty($args['maxmuxqueuesize']) ?$args['maxmuxqueuesize'] : 8192;
  $options['args']['owner']                = !empty($args['owner']) ? $args['owner'] : false;
  $options['args']['group']                = !empty($args['group']) ? $args['group'] : false;
  $options['args']['rename']               = !empty($args['rename']) ?: 0;
  $options['args']['language']             = !empty($args['language']) ? $args['language'] : null;
  $options['args']['stats_period']         = !empty($args['ffmpeg_stats_period']) ? $args['ffmpeg_stats_period'] : "0.5";  // ffmpeg stats reporting frequency in seconds
  $options['args']['cooldown']             = !empty($args['cooldown']) ? $args['cooldown'] : 0;

  //Operational args (if detected is_enabled :: no value to pass)
  $options['args']['test']              = false;
  $options['args']['verbose']           = !empty($options['args']['test']) ? $options['args']['test'] : false;  // if test, assume verbose
  $options['args']['keys']              = false;
  $options['args']['force']             = false;
  $options['args']['nomkvmerge']        = false;
  $options['args']['override']          = false;
  $options['args']['followlinks']       = false;
  $options['args']['remove_illegal_chars'] = !empty($args['remove_illegal_chars']) ?: false;
  $options['args']['ignore_delete_prompt'] = !empty($args['ignore_delete_prompt']) ? $args['ignore_delete_prompt'] : false;
  $options['args']['exclude']           = false;  // if true, the xml file will be flagged exclude for the processed the media.
  $options['args']['keeporiginal']      = false; //if true, the original file will be retained. (renamed as filename.orig.ext)
  $options['args']['permissions']       = 0664; //Set file permission to (int value).  Set to False to disable.
  $options['args']['filter_foreign']    = !empty($args['filter_foreign']) ? $args['filter_foreign'] : false;
  $options['args']['delay']             = 0; // File must be at least [delay] seconds old before being processes (set to zero to disable) Prevents process on file being assembled or moved.
  $options['args']['loglev']            = "quiet";  // [quiet, panic, fatal, error, warning, info, verbose, debug]
  $options['args']['threads']           = 0;
  $options['args']['stop']              = isset($args['stop']) ? $args['stop'] : "/tmp/hevc.stop";
  $options['args']['toolopt']           = false;
  $options['args']['remove_stale_stop'] = isset($args['remove_stale_stop']) ? $args['remove_stale_stop'] : false;
  $options['args']['pix_fmts']          = array(//acceptable pix_fmts
    "yuv420p",
    "yuv420p10le",
    "p010le",
  );

  $options['args']['cmdlnopts'] = array(
    "exclude",
    "followlinks",
    "force",
    "help",
    "ignore_delete_prompt",
    "keeporiginal",
    "keys",
    "nomkvmerge",
    "override",
    "rename",
    "permissions",
    "test",
    "verbose",
    "abitrate::",
    "achannels::",
    "acodec::",
    "asamplerate::",
    "audioboost::",
    "filterforeign",
    "fps::",
    "language::",
    "pix_fmt::",
    "quality::",
    "scale::",
    "search::",
    "vbr::",
    "vmax::",
    "vmin::",
  );
  $options = keeper_tags($options);
  return ($options);
}

/** ---------------------------------------------------------------------------------------------------- */
function getLocationOptions($options, $args, $dir)
{
  if ($dir == ".") {
    $dir = getcwd();
  }

  foreach ($options['locations'] as $key => $location) {
    $esc_pattern = str_replace("/", "\/", explode("|", $location)[0]);
    if (preg_match("/$esc_pattern/", $dir)) {
      $options['args']['key']             = $args['key'] = $key;
      $location                           = str_replace(" ", "\ ", $options['locations'][$args['key']]);
      $key                                = $args['key'];
      $location_param_array               = explode("|", $location);
      $dirs["$key"]                       = $location_param_array[0];
      $options['audio']['channels']       = $location_param_array[1];
      $options['audio']['bitrate']        = $location_param_array[2];
      $options['video']['vmin']           = $location_param_array[3];
      $options['video']['vmax']           = $location_param_array[4];
      $options['video']['quality_factor'] = $location_param_array[5];
      $options['video']['scale']          = $location_param_array[6];
      $options['video']['fps']            = $location_param_array[7];
      $options['video']['contrast']       = $location_param_array[8];
      $options['video']['brightness']     = $location_param_array[9];
      $options['video']['saturation']     = $location_param_array[10];
      $options['video']['gamma']          = $location_param_array[11];
      if (array_key_exists(12, $location_param_array)) {
        $options['args']['destination'] = $location_param_array[12];
      }
    }
  }
  $options = getAudioBitrate($options, $args);
  $options = getCommandLineOptions($options, $args);
  return ($options);
}

/** ---------------------------------------------------------------------------------------------------- */
function setOption($option)
{
  $options['profile']                  = $option[0];
  $options['format']                   = $option[1];
  $options['args']['extension']        = str_replace('.', '', $option[2]);
  $options['video']['codec']           = $option[3];
  $options['video']['pix_fmt']         = $option[4];
  $options['video']['codec_name']      = $option[5];
  $options['video']['codec_long_name'] = $option[6];
  return ($options);
}

/** ---------------------------------------------------------------------------------------------------- */
function getAudioBitrate($options, $args)
{
  if (!empty($args['key']) && array_key_exists($args['key'], $options['locations'])) {
    $options['audio']['bitrate'] = explode("|", $options['locations'][$args['key']])[2];
  }
  return ($options);
}

/**
 *  input: options
 *  output: options
 *  purpose: build array of tags to keep from process item 
 */
function keeper_tags($options)
{
  $options['tags'] = [];
  // Clear Old Tags --  lowercase metadata names
  $keep_ftags = array(
    "major_brand",
    "compatible_brands",
    "date",
  );
  if (!$options['args']['override']) {
    array_push(
      $keep_ftags,
      array(
        "exclude",
        "mkvmerged",
        "audioboost"
      )
    );
  }
  $keep_vtags = array(
    "duration",
    "duration-" . $options['args']['language'],
    "creation_date",
    "language",
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
    "_statistics_tags",
    "_statistics_writing_app",
    "_statistics_writing_date_utc",
  );

  if (!empty($options['tags']['ftags'])) {
    foreach ($options['tags']['ftags'] as $ftag => $fval) {

    }
  }

  if (!empty($options['tags']['vtags'])) {
    foreach ($options['tags']['vtags'] as $vtag => $vval) {
      $lvtag = strtolower($vtag);
      if (in_array($lvtag, $keep_vtags)) {
        $options['args']['meta'] .= " -metadata:s:v:0 $vtag=$vval";
      } else {
        // Set the existing value to nothing
        $options['args']['meta'] .= " -metadata:s:v:0 $vtag=";
      }
    }
  }
  if (!empty($options['tags']['atags'])) {
    foreach ($options['tags']['atags'] as $atag => $aval) {
      $latag = strtolower($atag);
      if (in_array($latag, $keep_atags)) {
        $options['args']['meta'] .= " -metadata:s:a:0 $atag=$aval";
      } else {
        // Set the existing value to nothing
        $options['args']['meta'] .= " -metadata:s:a:0 $atag=";
      }
    }
  }
  return ($options);
}