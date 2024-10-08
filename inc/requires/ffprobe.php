<?php

/**
 * 
 *  input: file, options
 *  output: array(file, info) 
 *  purpose: ffmpeg's ffprobe output to xml file and returns the results as $info
 * 
 */

function ffprobe($file, $options, $quiet = false)
{
  if (
      empty($file) || 
      empty($options) 
  ) {
    return (array([], []));
  }

  $exec_args = "-v quiet -print_format xml -show_format -show_streams";
  $xml_file  = "./.xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml";
  $info      = array();
  $language  = null;
  $xml       = null;
  if (!file_exists("." . DIRECTORY_SEPARATOR . ".xml")) {
    mkdir("./.xml");
  }
  if (!file_exists("$xml_file")) {
    $action = "PROBED";
    $cmdln  = "ffprobe $exec_args '" . $file['basename'] . "' > '." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml'";
    exec("$cmdln");
    if (!file_exists("$xml_file")) {
      print ansiColor("red") . "error: Could not create ffprobe xml.  Check that ffprobe is exists, is executable, and in the system search path\n" . ansiColor();
      return (array($file, $options));
    }
  } else {
    $action = "INFO";
  }
  if (file_exists("$xml_file")) {
    $xml          = simplexml_load_file("$xml_file");
    $xml_filesize = getXmlAttribute($xml->format, "size") ? getXmlAttribute($xml->format, "size") : 0;
  }
  if (!isset($xml) || empty($xml) || (file_exists($file['basename']) && (int) $xml_filesize != filesize($file['basename'])) && !isset($options['stale-detected'])) {
    if (!$quiet) print "Stale xml detected for " . $file['basename'] . " Initiating new probe...\n";
    $options['stale-detected'] = true;
    unlink("$xml_file");
    list($file, $info) = ffprobe($file, $options, $quiet);
    list($options, $info) = ffanalyze($file, $info, $options, false);
    $xml_file          = "." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml";
    $xml               = simplexml_load_file("$xml_file");
  }
  $info                          = array();
  if (!$options['args']['toolopt']) {
    $info['format']['format_name'] = getXmlAttribute($xml->format, "format_name");
    $info['format']['duration']    = getXmlAttribute($xml->format, "duration");
    $info['format']['size']        = getXmlAttribute($xml->format, "size");
    $info['format']['bitrate']     = getXmlAttribute($xml->format, "bit_rate") ? getXmlAttribute($xml->format, "bit_rate") : getXmlAttribute($xml->format, "BPS");
    $info['format']['nb_streams']  = getXmlAttribute($xml->format, "nb_streams");
    $info['format']['probe_score'] = getXmlAttribute($xml->format, "probe_score");
    $info['format']['exclude']     = getXmlAttribute($xml->format, "exclude") ? getXmlAttribute($xml->format, "exclude") : false;
    $info['format']['mkvmerged']   = getXmlAttribute($xml->format, "mkvmerged") ? getXmlAttribute($xml->format, "mkvmerged") : false;
    $info['format']['audioboost']  = false;
    $info['video']                 = [];
    $info['audio']                 = [];
    $info['subtitle']              = [];
  }

  if(isset($xml->format->tags)) {
    foreach ($xml->format->tags->tag as $tag) {
      $tag_key = strtolower(getXmlAttribute($tag, "key"));
      $tag_val = strtolower(preg_replace('/\(|\)|\'/', '', getXmlAttribute($tag, "value")));
      if (preg_match('/^exclude$/', $tag_key)) {
        $options['tags']['ftags'][] = $info['format']['exclude'] = $tag_val;
      } elseif (preg_match('/^mkvmerged$/', $tag_key)) {
        $options['tags']['ftags'][] = $info['format']['mkvmerged'] = $tag_val;
      } elseif (preg_match('/audioboost/', $tag_key)) {
        $options['tags']['ftags'][] = $info['format']['audioboost'] = "$tag_val";
      }
      $ftags[$tag_key] = preg_match("/\s/", "$tag_val") ? "'$tag_val'" : $tag_val;
    }
  }

  if (isset($xml->streams->stream)) {
    foreach ($xml->streams->stream as $stream) {
      $codec_type = getXmlAttribute($stream, "codec_type");
      switch ($codec_type) {
        case "video":
          if (empty($info['video'])) {
            $vtags                            = array();
            $info['video']['index']           = getXmlAttribute($stream, "index");
            $info['video']['codec_type']      = getXmlAttribute($stream, "codec_type");
            $info['video']['codec']           = getXmlAttribute($stream, "codec_name");
            $info['video']['codec_name']      = getXmlAttribute($stream, "codec_name");
            $info['video']['pix_fmt']         = getXmlAttribute($stream, "pix_fmt");
            $info['video']['level']           = getXmlAttribute($stream, "level");
            $info['video']['width']           = getXmlAttribute($stream, "width");
            $info['video']['height']          = getXmlAttribute($stream, "height");
            $info['video']['ratio']           = getXmlAttribute($stream, "display_aspect_ratio");
            $info['video']['avg_frame_rate']  = getXmlAttribute($stream, "avg_frame_rate");
            $info['video']['fps']             = round((explode("/", $info['video']['avg_frame_rate'])[0] / explode("/", $info['video']['avg_frame_rate'])[1]), 2);
            $info['video']['color_range']     = getXmlAttribute($stream, "color_range");
            $info['video']['color_space']     = getXmlAttribute($stream, "color_space");
            $info['video']['color_transfer']  = getXmlAttribute($stream, "color_transfer");
            $info['video']['color_primaries'] = getXmlAttribute($stream, "color_primaries");
            $info['video']['hdr']             = preg_match('/bt[27][0][29][0]?/', getXmlAttribute($stream, "color_primaries")) ? getXmlAttribute($stream, "color_primaries") : false;

            if (!isset($stream->tags->tag)) {
              $stream->tags->tag = null;
            }
            foreach ($stream->tags->tag as $tag) {
              $tag_key = strtolower(getXmlAttribute($tag, "key"));
              $tag_val = strtolower(preg_replace('/\(|\)|\'/', '', getXmlAttribute($tag, "value")));
              //print "\n" . $tag_key . " : " . $tag_val . "\n";
              if (preg_match('/^bps$/i', $tag_key) && !isset($info['video']['bitrate'])) {
                $info['video']['bitrate'] = (int) $tag_val;
              }
              if (preg_match('/^bit[\-\_]rate$/i', $tag_key) && !isset($info['video']['bitrate'])) {
                if (preg_match("/k/i", $tag_val)) {
                  $info['video']['bitrate'] = (int) (filter_var($tag_val, FILTER_SANITIZE_NUMBER_INT) * 1000);
                } else {
                  $info['video']['bitrate'] = (int) $tag_val;
                }
              }
              $vtags[$tag_key] = preg_match("/\s/", "$tag_val") ? "'$tag_val'" : $tag_val;
            }
            $options['tags']['vtags'] = $vtags;
          }
          break;
        case "audio":
          if (empty($info['audio']) && !isset($info['def_audio'])) {
            if (empty($language)) {
              $info['def_audio']['index']       = getXmlAttribute($stream, "index") ? getXmlAttribute($stream, "index") : "";
              $info['def_audio']['title']       = getXmlAttribute($stream, "title") ? getXmlAttribute($stream, "title") : "";
              $info['def_audio']['codec_type']  = getXmlAttribute($stream, "codec_type") ? getXmlAttribute($stream, "codec_type") : "";
              $info['def_audio']['codec_name']  = getXmlAttribute($stream, "codec_name") ? getXmlAttribute($stream, "codec_name") : "";
              $info['def_audio']['channels']    = getXmlAttribute($stream, "channels") ? getXmlAttribute($stream, "channels") : "";
              $info['def_audio']['sample_rate'] = getXmlAttribute($stream, "sample_rate") ? getXmlAttribute($stream, "sample_rate") : "";
              $info['def_audio']['bitrate']     = getXmlAttribute($stream, "bit_rate") ? getXmlAttribute($stream, "bit_rate") : "";
              $info['def_audio']['audioboost']  = !empty($info['format']['audioboost']) ? $info['format']['audioboost'] : "";            
            }
            if (!isset($stream->tags->tag)) {
              $stream->tags->tag = null;
            }
            foreach ($stream->tags->tag as $tag) {
              $tag_key = strtolower(getXmlAttribute($tag, "key"));
              $tag_val = strtolower(preg_replace('/\(|\)|\'/', '', getXmlAttribute($tag, "value")));
              if ($tag_key == "language") {
                $language = $options['args']['language'];
                if (preg_match("/$language/i", $tag_val)) {                  
                  $info['audio']['index']       = getXmlAttribute($stream, "index") ? getXmlAttribute($stream, "index") : "";
                  $info['audio']['title']       = getXmlAttribute($stream, "title") ? getXmlAttribute($stream, "title") : "";
                  $info['audio']['codec_type']  = getXmlAttribute($stream, "codec_type") ? getXmlAttribute($stream, "codec_type") : "";
                  $info['audio']['codec_name']  = getXmlAttribute($stream, "codec_name") ? getXmlAttribute($stream, "codec_name") : "";
                  $info['audio']['channels']    = getXmlAttribute($stream, "channels") ? getXmlAttribute($stream, "channels") : "";
                  $info['audio']['sample_rate'] = getXmlAttribute($stream, "sample_rate") ? getXmlAttribute($stream, "sample_rate") : "";
                  $info['audio']['bitrate']     = getXmlAttribute($stream, "bit_rate") ? getXmlAttribute($stream, "bit_rate") : "";
                  $info['audio']['audioboost']  = !empty($info['format']['audioboost']) ? $info['format']['audioboost'] : "";
                  $info['audio']['language'] = $tag_val;
                  unset($info['def_audio']);
                }
                else {
                  $info['audio'] = array();
                  continue;
                }
              }
              if (preg_match('/^bps$/i', $tag_key)) {
                if (!empty($info['audio']) && empty($info['audio']['bitrate'])) {
                  $info['audio']['bitrate'] = $tag_val;
                } elseif (isset($info['def_audio'])) {
                  $info['def_audio']['bitrate'] = $tag_val;
                }
              } elseif (preg_match('/bit*rate$/i', $tag_key)) {
                if (!empty($info['audio']) && empty($info['audio']['bitrate'])) {
                  if (preg_match('/k/i', $tag_val)) {
                    $info['audio']['bitrate'] = (int) (filter_var($tag_val, FILTER_SANITIZE_NUMBER_INT) * 1000);
                  } elseif (preg_match('/m/i', $tag_val)) {
                    $info['audio']['bitrate'] = (int) (filter_var($tag_val, FILTER_SANITIZE_NUMBER_INT) * 1000000);
                  } else {
                    $info['audio']['bitrate'] = (int) "$tag_val";
                  }
                } elseif (isset($info['def_audio'])) {
                  $info['def_audio']['bitrate'] = $tag_val;
                }
              }
              $atags[$tag_key] = preg_match("/\s/", "$tag_val") ? "'" . $tag_val . "'" : $tag_val;
            }
            $options['tags']['atags'] = $atags;
          }
          break;
        case "subtitle":
          if (empty($info['subtitle'])) {
            $info['subtitle']['index']      = getXmlAttribute($stream, "index");
            $info['subtitle']['codec_type'] = getXmlAttribute($stream, "codec_type");
            $info['subtitle']['codec']      = getXmlAttribute($stream, "codec_name");
            $info['subtitle']['codec_name'] = getXmlAttribute($stream, "codec_name");
          }
          break;
      }
    }
  }

  if (
    $action != "INFO" &&
    !empty($info['video']) &&
    !empty($info['audio']) &&
    !$quiet
  ) {
    ffanalyze($file, $info, $options, true);
    print ansiColor("blue") . "$action: " . ansiColor("green") . $file['basename'] . ansiColor() . " (" . formatBytes(filesize($file['basename']), 2, true) . ")" . ansiColor("blue") . "\n";
    if (!empty($info['video']) && !empty($info['video']['bitrate'])) {
      charTimes(7, " ");
      print $info['video']['codec_type'] . ":" . $info['video']['codec'] . ", " . $info['video']['width'] . "x" . $info['video']['height'] . ", " . formatBytes($info['video']['bitrate'], 2, false) . "PS\n";
    }
    if (!empty($info['audio']) && !empty($info['audio']['bitrate'])) {
      charTimes(7, " ");
      print $info['audio']['codec_type'] . ":" . $info['audio']['codec_name'] . ", CH." . $info['audio']['channels'] . ", " . formatBytes($info['audio']['bitrate'], 0, false) . "PS\n";
    }
    if (!empty($info['subtitle'])) {
      charTimes(7, " ");
      print "sub: " . $info['subtitle']['codec_type'] . ":" . $info['subtitle']['codec'];
    }
    print ansiColor() . "\n";
  } elseif (
    (!empty($info) && (empty($info['video']) || empty($info['audio']))) && !$options['args']['test']
  ) {
    $missing = null;
    if ($file['extension'] == $options['args']['extension']) {
      if (empty($info['video'])) $missing = "video";
      if (empty($info['audio'])) $missing .= ' audio';
      if (!$options['args']['ignore_delete_prompt']) {
        print ansiColor("red") . "NO EXISTING: " . ansiColor("green") . strtoupper($options['args']['language']) . ansiColor("red") . " TRACK(s) detected\n" . ansiColor();        
        print "Delete " . ansiColor("green") . $file['basename'] . ansiColor() . "?  [y/N] >";
        $del_response = trim(fgets(STDIN));
      } 
      elseif ($options['args']['ignore_delete_prompt'] > 0) {
        $del_response = "y";
      } else {
        $del_response = "n";
        if (isset($info['def_audio'])) {
          $info['audio'] = $info['def_audio'];
          unset($info['def_audio']);
        }
        mkvmergeItem($file, [] , $options , $info ); // mkvmerge with --default-language
      }
      if (preg_match('/y/i', $del_response)) {
        unlink($file['basename']);
      }  else {
      //  $tag_data = [array("name" => "exclude", "value" => "1")];
      //  $status = setMediaFormatTag($file, $tag_data, $options);
      //  if (!$status) {
      //    ffprobe($file, $options, true);
      //  }
      //  $options['args']['exclude'] = true;
        $info = array();
        return (array($file, $info));
      }
    }
    if (!$options['args']['exclude'] && !$options['args']['test']) {
      if (file_exists($file['basename'])) {
        unlink($file['basename']);
      }
      if (file_exists(".xml/" . $file['filename'] . ".xml")) {
        unlink("./.xml/" . $file['filename'] . ".xml");
      }
      print $file['basename'] . " NO Video or Audio\n";
      $info = array();
    } else {
      ffprobe($file, $options, true);
    }

    // Check if color_range is in options
    if (
      !empty($info['video']['color_range']) &&
      in_array($info['video']['color_range'], $options['video']['hdr']['color_range'])
    ) {
      print "Incompatible color_range detected: " . $file['basename'] . "\n";
      $info = array();
    }
  }
  return (array($file, $info));
}