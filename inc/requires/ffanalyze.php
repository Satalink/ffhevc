<?php
/**
 *  input: info, options, args, dir, file
 *  output: options
 *  purpose:  Analyze video info and configure options to encode according to configuration
 * 
 */


function ffanalyze($file, $info, $options, $quiet = false)
{
  if (empty($info))  return ($options);

  $options['args']['video'] = '';
  $options['args']['audio'] = '';
  $options['args']['meta']  = '';
  $options['args']['map']   = '';

  if ($info['format']['exclude'] && !$options['args']['override']) {
    if ($options['args']['verbose']) {
      print ansiColor("green") . " " . $file['basename'] . ansiColor("red") . " Excluded! " . ansiColor("yellow") . "  use --override option to override.\n" . ansiColor();
    }
  }

  // Container MetaData
  $meta_duration = "";
  if (!empty($info['format']['duration'])) {
    $t             = round($info['format']['duration']);
    $duration      = sprintf('%02d:%02d:%02d', ($t / 3600), ($t / 60 % 60), $t % 60);
    $meta_duration = " -metadata duration=" . $duration;
  }
  $options['args']['meta'] .= " -metadata title='" . trim(preg_split('/\-|\[|\./', $file['filename'])[0]) . "' -metadata creation_date='" . gmdate("Y-m-d H:i:s \G\M\T") . "' -metadata encoder= ";

  // Video
  if (!isset($info['video']['bitrate'])) {
    $info['video']['bitrate'] = 0;
  }

  // Dynamicly adjust video bitrate to size +
  if (!empty($info['video'])) {
    $codec_name              = $options['video']['codec_name'];
    $options['video']['vps'] = round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) . "k";
    $options['video']['bps'] = (round((($info['video']['height'] + $info['video']['width']) * $options['video']['quality_factor']), -2) * 1000);


    if (
      preg_match(strtolower("/$codec_name/"), $info['video']['codec']) &&
      in_array($info['video']['pix_fmt'], $options['args']['pix_fmts']) &&
      $info['video']['height'] <= $options['video']['scale'] &&
      $info['video']['bitrate'] !== 0 &&
      $info['video']['bitrate'] <= $options['video']['bps'] &&
      !$options['args']['override']
    ) {
      $options['args']['video'] = "-vcodec copy";
    }

    if (!preg_match("/copy/i", $options['args']['video']) && !$options['args']['exclude']) {
      $pf_key = array_search($info['video']['pix_fmt'], $options['args']['pix_fmts']);

      if (!$quiet) {
      print ansiColor("blue") . "Video Inspection ->" . ansiColor() .
        $info['video']['codec'] . ":" . $options['video']['codec_name'] . "," .
        $info['video']['pix_fmt'] . "~=" . $options['args']['pix_fmts'][$pf_key] . "," .
        $info['video']['height'] . "<=" . $options['video']['scale'] . "," .
        round(($info['video']['bitrate'] / 1000), -2) . "k" . "<=" . round(($options['video']['bps'] / 1000), -2) . "k," .
        $options['video']['quality_factor'] . "," . $options['args']['override'] . "\n";
      }
      if ($info['video']['height'] > $options['video']['scale']) {
        //hard set info to be used for bitrate calculation based on scaled resolution
        $info['video']['width']   = (($info['video']['width'] * $options['video']['scale']) / $info['video']['height']);
        $info['video']['height']  = $options['video']['scale'];
        $info['video']['bitrate'] = $options['video']['vps'];
        $scale_option             = "scale=-1:" . $options['video']['scale'];
        //Recalculate target video bitrate based on projected output scale
        $options['video']['vps'] = round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) . "k";
        $options['video']['bps'] = (round((round($info['video']['height'] + round($info['video']['width'])) * $options['video']['quality_factor']), -2) * 1000);
      } else {
        $scale_option = null;
        if ($info['video']['bitrate'] != 0 && $info['video']['bitrate'] < $options['video']['bps']) {
          // if info bps is lower than options, use info bitrate
          $options['video']['vps'] = round(($info['video']['bitrate'] / 1000), -2) . "k";
          $options['video']['bps'] = $info['video']['bitrate'];
        }
      }

      if ($info['video']['fps'] > $options['video']['fps']) {
        $fps_option = " -r " . $options['video']['fps'];
      } else {
        $options['video']['fps'] = $info['video']['fps'];
        $fps_option              = "";
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
    } else {
      $options['args']['meta'] .= " -metadata:s:v:0 language=" . $options['args']['language'] .
        " -metadata:s:v:0 codec_name=" . $options['video']['codec'] .
        " -metadata:s:v:0 bit_rate=" . $options['video']['vps'] .
        " -metadata:s:v:0 bps=" . $options['video']['bps'] .
        " -metadata:s:v:0 title= ";
        $options['info']['video'] = ansiColor("blue") . "Video Inspection ->" . ansiColor("green") . "copy\n" . ansiColor();
    }
    $options['args']['map'] .= "-map 0:v? ";
  }

  // Audio
  if (isset($info) && !empty($info['audio']) && isset($options) && !empty($options['audio']) && !$options['args']['exclude']) {
    $title = isset($info['audio']['title']) ? $info['audio']['title'] : "Default Track";
    if (!preg_match("/comment/i", $title)) {
      $info_br = preg_split('/\s/', formatBytes(filter_var($info['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT), 0, 0))[0];
      $opt_br  = preg_split('/\s/', formatBytes(filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT), 0, 0))[0];
      if (
        isset($info['audio']['codec_name']) && isset($options['audio']['codecs']) &&
        in_array($info['audio']['codec_name'], $options['audio']['codecs']) &&
        $info_br <= $opt_br &&  // Assumes option bitrate less than 1 MBPS
        $info['audio']['channels'] <= $options['audio']['channels'] &&
        $info['audio']['sample_rate'] <= $options['audio']['sample_rate'] &&
        !$options['args']['override']
      ) {
        $options['args']['audio'] = "-acodec copy";
      }

      if (preg_match("/copy/i", $options['args']['audio'])) {
        if ($info['audio']['bitrate'] == 0) {
          $info['audio']['bitrate'] = "";
          $info['audio']['bps']     = "";
        } elseif (
          isset($info['audio']['bitrate']) &&
          !empty($info['audio']['bitrate'])
        ) {
          if (is_numeric($info['audio']['bitrate'])) {
            $info['audio']['bps']     = $info['audio']['bitrate'];
            $info['audio']['bitrate'] = (int) round(($info['audio']['bitrate'] / 1000)) . "k";
          } else {
            $info['audio']['bps'] = (int) (filter_var($info['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000);
          }
        } else {
          $info['audio']['bps'] = "";
        }
        $options['args']['meta'] .=
          " -metadata:s:a:0 language=" . $options['args']['language'] .
          " -metadata:s:a:0 codec_name=" . $info['audio']['codec_name'] .
          " -metadata:s:a:0 channels=" . $info['audio']['channels'] .
          " -metadata:s:a:0 bit_rate=" . $info['audio']['bitrate'] .
          " -metadata:s:a:0 sample_rate=" . $info['audio']['sample_rate'] .
          " -metadata:s:a:0 bps=" . $info['audio']['bps'] .
          " -metadata:s:a:0 title= ";
        if (!empty($options['args']['audioboost']) && empty($info['audio']['audioboost'])) {
          $options['args']['meta'] .= " -metadata:s:a:0 audioboost=" . $options['args']['audioboost'] . '"';
        }
          $options['info']['audio'] = ansiColor("blue") . "Audio Inspection ->" . ansiColor("green") . "copy\n" . ansiColor();
      } else {
        if (is_numeric($info['audio']['bitrate'])) {
          $info['audio']['bps']     = (int) (filter_var($info['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000);
          $info['audio']['bitrate'] = (int) round(($info['audio']['bitrate'] / 1000)) . "k";
        } elseif (!empty($info['audio']['bitrate']) && is_integer($info['audio']['bitrate'])) {
          $info['audio']['bps'] = (int) (filter_var($info['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000);
        }
        if (!$quiet) {
          print ansiColor("blue") . "Audio Inspection ->" . ansiColor() .
            $info['audio']['codec_name'] . ":" . $options['audio']['codec'] . "," .
            $info['audio']['bitrate'] . "<=" . $options['audio']['bitrate'] . "," .
            $info['audio']['channels'] . "ch<=" . $options['audio']['channels'] . "ch," .
            ($info['audio']['sample_rate'] / 1000) . "KHz<=" . ($options['audio']['sample_rate'] / 1000) . "KHz";
          if (!empty($options['args']['audioboost']) && empty($info['audio']['audioboost'])) {
            print ansiColor("white") . "," . ansiColor("yellow") . "+" . $options['args']['audioboost'];
          }
          print "\n" . ansiColor();
        }
        if ($info['audio']['channels'] < $options['audio']['channels'] || !isset($options['audio']['channels'])) {
          $options['audio']['channels'] = $info['audio']['channels'];
        }
        if ($info['audio']['sample_rate'] < $options['audio']['sample_rate'] || !isset($options['audio']['sample_rate'])) {
          $options['audio']['sample_rate'] = $info['audio']['sample_rate'];
        }
        // Set audio args
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
          }

        } else {
          $options['args']['audio']        = "-acodec copy";
          $options['audio']['channels']    = isset($info['audio']['channels']) ? $info['audio']['channels'] : '';
          $options['audio']['bitrate']     = isset($info['audio']['bitrate']) ? $info['audio']['bitrate'] : 0;
          $options['audio']['sample_rate'] = isset($info['audio']['sample_rate']) ? $info['audio']['sample_rate'] : '';
        }
        $options['args']['meta'] .= " -metadata:s:a:0 language=" . $options['args']['language'] . " " .
          " -metadata:s:a:0 codec_name=" . $options['audio']['codec'] .
          " -metadata:s:a:0 channels=" . $options['audio']['channels'] .
          " -metadata:s:a:0 bit_rate=" . $options['audio']['bitrate'] .
          " -metadata:s:a:0 bps=" . (int) (filter_var($options['audio']['bitrate'], FILTER_SANITIZE_NUMBER_INT) * 1000) .
          " -metadata:s:a:0 sample_rate=" . $options['audio']['sample_rate'] .
          " -metadata:s:a:0 title=" . !empty(isset($info['audio']['title'])) ? $info['audio']['title'] : "Default Track";
      }
      $options['args']['map'] .= "-map 0:a? ";
    }
  }

  if (!$quiet) {
    if (isset($options['info']['video']) && preg_match('/copy/i', $options['info']['video']) &&
       !isset($options['info']['audio']) && !$options['args']['exclude']) {
      print $options['info']['video'];
    } elseif (isset($options['info']['audio']) && preg_match('/copy/i', $options['info']['audio']) &&
       !isset($options['info']['video']) && !$options['args']['exclude']) {
      print $options['info']['audio'];
    }
  }

  //Subtexts
  $options['args']['subs'] = "-scodec copy";
  $options['args']['map'] .= "-map 0:s? ";
 
  if (
    (preg_match("/copy/i", $options['args']['video']) &&
      preg_match("/copy/i", $options['args']['audio']) &&
      $file['extension'] == $options['args']['extension']) &&
    (!$options['args']['override'] &&
      !$options['args']['exclude']) # gets excluded later -- options needs to exist
  ) {
    return (array($options, []));  # no re-encoding needed
  }
  return (array($options, $info));
}