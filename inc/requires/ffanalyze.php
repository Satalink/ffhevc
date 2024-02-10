<?php 
/**
 *  input: info, options, args, dir, file
 *  output: options
 *  purpose:  Analyze video info and configure options to encode according to configuration
 * 
 */


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
      print ansiColor("green") . " " . $file['basename'] . ansiColor("red") . " Excluded! ". ansiColor("yellow") . "  use --override option to override.\n" . ansiColor();
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
    $meta_duration . " -metadata creation_date=" . $options['info']['timestamp'] .
    " -metadata encoder= ";

//Video
//Dynamicly adjust video bitrate to size +
  if (!empty($info['video'])) {
    $codec_name = $options['video']['codec_name'];
    $options['video']['vps'] = round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) . "k";
    $options['video']['bps'] = (round((($info['video']['height'] + $info['video']['width']) * $options['video']['quality_factor']), -2) * 1000);

    if (!isset($info['video']['bitrate'])) {
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

    if (!preg_match("/copy/i", $options['args']['video'])) {
      $pf_key = array_search($info['video']['pix_fmt'], $options['args']['pix_fmts']);
      print ansiColor("blue") . "Video Inspection ->" . ansiColor() .
        $info['video']['codec'] . ":" . $options['video']['codec_name'] . "," .
        $info['video']['pix_fmt'] . "~=" . $options['args']['pix_fmts'][$pf_key] . "," .
        $info['video']['height'] . "<=" . $options['video']['scale'] . "," .
        $info['video']['bitrate'] . "<=" . $options['video']['bps'] . "," . 
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
  if ($options['audio']['bitrate'] == 0) {
    $info['audio'] = null;
  }
  
  if (isset($info) && !empty($info['audio']) && isset($options) && !empty($options['audio'])) {
    $title = isset($info['audio']['title']) ? $info['audio']['title'] : "Default Track";
    if (!preg_match("/comment/i", $title)) {
      if (
        isset($info['audio']['codec']) && isset($options['audio']['codecs']) &&
        in_array($info['audio']['codec'], $options['audio']['codecs']) &&
        (int) ((filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000)) &&
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
        $options['args']['meta'] .= 
          " -metadata:s:a:0 language=" . $options['args']['language'] . 
          " -metadata:s:a:0 codec_name=" . $info['audio']['codec'] .
          " -metadata:s:a:0 channels=" . $info['audio']['channels'] .
          " -metadata:s:a:0 bit_rate=" . $info['audio']['bitrate'] .
          " -metadata:s:a:0 sample_rate=" . $info['audio']['sample_rate'] .
          " -metadata:s:a:0 bps=" . $info['audio']['bps'] .
          " -metadata:s:a:0 title= ";
        if (!empty($options['args']['audioboost']) && empty($info['audio']['audioboost'])) {
          $options['args']['meta'] .= " -metadata:s:a:0 audioboost=" . $options['args']['audioboost'];
        }
        if (!preg_match("/copy/i", $options['args']['video'])) {
          print ansiColor("yellow") . "Audio Inspection ->" . ansiColor() . "copy\n";
        }
      }
      else {
        print ansiColor("green") . $file['basename'] . "\n" . ansiColor();
        print ansiColor("blue") . "Audio Inspection ->" . ansiColor() .
          $info['audio']['codec'] . ":" . $options['audio']['codec'] . "," .
          $info['audio']['bitrate'] . "<=" . (filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000) . "," .
          $info['audio']['channels'] . "ch<=" . $options['audio']['channels'] . "ch\n";
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
          $options['args']['audio'] = 
            "-acodec " . $options['audio']['codec'] .
            " -ac " . $options['audio']['channels'] .
            " -ab " . $options['audio']['bitrate'] .
            " -ar " . $options['audio']['sample_rate'] .
            " -async 1";
            if (!empty($options['args']['audioboost']) && empty($info['audio']['audioboost'])) {
            $options['args']['audio'] .= " -af volume=" . $options['args']['audioboost'];
            print ansiColor("blue") . "Applying Audio Boost " . $options['args']['audioboost'] . "\n" . ansiColor();
            }
    
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
  } else {
    print ansiColor("green") . "Audio Inspection ->" . ansiColor() .
    "info:missing\n";
  }

  //Subtexts
  $options['args']['subs'] = "-scodec copy";
  $options['args']['map'] .= "-map 0:s? ";

  //Clear Old Tags
  //lowercase metadata names
 $keep_ftags = array(
    "exclude",
    "mkvmerged",
    "audioboost"
  );
  $keep_vtags = array(
    "bps",
    "bit_rate",
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
    "channels",
    "sample_rate",
    "bit_rate",
    "bps",
    "_statistics_tags",
    "_statistics_writing_app",
    "_statistics_writing_date_utc",
    "number_of_frames",
    "number_of_bytes",
    );

  if (!empty($info['ftags'])) {
    foreach ($info['ftags'] as $ftag => $fval) {
      
    }
  }

  if (!empty($info['vtags'])) {
    foreach ($info['vtags'] as $vtag => $vval) {
      $lvtag = strtolower($vtag);
      if (in_array($lvtag, $keep_vtags)) {
        $options['args']['meta'] .= " -metadata:s:v:0 $vtag=$vval";
      }
      else {
        // Set the existing value to nothing
        $options['args']['meta'] .= " -metadata:s:v:0 $vtag=";
      }
    }
  }
  if (!empty($info['atags'])) {
    foreach ($info['atags'] as $atag => $aval) {
      $latag = strtolower($atag);
      if ((in_array($latag, $keep_atags))) {
        $options['args']['meta'] .= " -metadata:s:a:0 $atag=$aval";
      }
      else {
        // Set the existing value to nothing
        $options['args']['meta'] .= " -metadata:s:a:0 $atag=";
      }
    }
  }
  if (
       (preg_match("/copy/i", $options['args']['video'])) &&
       (preg_match("/copy/i", $options['args']['audio'])) &&
       ($file['extension'] == $options['args']['extension'])
     ) {
      return(array());  //No re-encoding needed
  }
  return($options);
}