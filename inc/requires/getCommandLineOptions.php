<?php
/**
 *  input: options, args
 *  output: options
 *  purpose:  Get Command Line Options (and flags) and configure the options array accordingly
 * 
 */

 function getCommandLineOptions($options, $args) {
  //COMMAND LINE OPTIONS
    $help = 
    ansiColor("blue") . "command [options] [file|key]  // options and flags before file or defined key\n" .
    ansiColor("yellow") . "e.g.   $> " . $args['application'] . " --test MediaFileName.mkv\n" .
    ansiColor("yellow") . "e.g.   $> " . $args['application'] . " --keeporiginal tv\n\n" . 
    ansiColor("green") . "BOOLS (Default false)" .
    ansiColor("blue") . "
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
    --filterforiegn :flag:        strip foriegn languages NOT matching \$options['args']['language'] OR --language\n\n" .
  
    ansiColor("green") . "PARMS (Default * denoted) i.e.  --language=eng" .
    ansiColor("blue") . "
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
    --permissions   :pass value:  set the file permission" .
    ansiColor();
  
  
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
