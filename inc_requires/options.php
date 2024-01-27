<?php
/**
 *  input: args
 *  output: options
 *  purpose: construct the application options array
 * 
 */
function getDefaultOptions($args) {
  $options = array();
  $options['locations'] = array();
  if (isset($args['locations'])) {
    $options['locations'] = $args['locations'];
  }
  
  $option = $args['opt'][$args['my_config']];
  $options = array_merge($options, setOption($option));

  /* Edit below settings to your preferences
   *    NOTE: conf/media_paths_keys OVERRIDE THESE VALUES
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
  $options['video']['profile'] = 'webdl'; //Used for filenaming after encoding

  $options['audio']['codecs'] = array("ac3", "eac3");   //("aac", "ac3", "libopus", "mp3") allow these codecs : $options['video']['hdr']['codec'] = "libx265";zodesc (if bitrate is below location limits)
  $options['audio']['codec'] = "eac3";  // "aac", "ac3", "libfdk_aac", "libopus", "mp3", "..." : "none"
  $options['audio']['channels'] = 6;
  $options['audio']['bitrate'] = "720k"; // default fallback maximum bitrate (bitrate should never be higher than this setting)
  $options['audio']['quality_factor'] = 1.01; // give bit-rate some tollerance (384114 would pass okay for 384000)
  $options['audio']['sample_rate'] = 48000;
  $options['audio']['max_streams'] = 1;  //Maximum number of audio streams to keep

  $options['args']['output_format'] = "mkv";
  $options['args']['extensions'] = array("mkv", "mp4", "mpeg", "ts", "m2ts", "avi");
  $options['args']['verbose'] = false;
  $options['args']['test'] = false;
  $options['args']['stats_period'] = "1";  // ffmpeg stats reporting frequency in seconds
  $options['args']['keys'] = false;
  $options['args']['force'] = false;
  $options['args']['skip'] = false;
  $options['args']['override'] = false;
  $options['args']['followlinks'] = false;
  $options['args']['exclude'] = false;  // if true, the xml file will be flagged exclude for the processing the media.
  $options['args']['keeporiginal'] = false; //if true, the original file will be retained. (renamed as filename.orig.ext)
  $options['args']['keepowner'] = true;  // if true, the original file owner will be used in the new file.
  $options['args']['deletecorrupt'] = false; // if true, corrupt files will be automatically deleted. (can be annoying if you're not fully automated)
  $options['args']['permissions'] = 0664; //Set file permission to (int value).  Set to False to disable.
  $options['args']['language'] = "eng";  // Keep this language track
  $options['args']['filter_foreign'] = true; // filters out all other language tracks that do not match default language track (defined above) : requires mkvmerge in $PATH
  $options['args']['delay'] = 0; // File must be at least [delay] seconds old before being processes (set to zero to disable) Prevents process on file being assembled or moved.
  $options['args']['cooldown'] = 0; // used as a cool down period between processing - helps keep extreme systems for over heating when converting an enourmous library over night (on my liquid cooled system, continuous extreme load actually raises the water tempurature to a point where it compromises the systems ability to regulate tempurature.
  $options['args']['loglev'] = "quiet";  // [quiet, panic, fatal, error, warning, info, verbose, debug]
  $options['args']['threads'] = 0;
  $options['args']['maxmuxqueuesize'] = 8192;
  $options['args']['pix_fmts'] = array(//acceptable pix_fmts
    "yuv420p",
    "yuv420p10le",
    "p010le",
  );
  $options['args']['cmdlnopts'] = array(
    "help",
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



