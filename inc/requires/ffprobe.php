<?php

/**
 * 
 *  input: file, options
 *  output: info 
 *  purpose: ffmpeg's ffprobe output to xml file and returns the results as $info
 * 
 */

function ffprobe($file, $options) {
  $exec_args = "-v quiet -print_format xml -show_format -show_streams";
  $xml_file = "./.xml" . DIRECTORY_SEPARATOR . $file['filename'] . ".xml";
  $info = array();
  $xml = null;
  if (!file_exists("./.xml")) {
    mkdir("./.xml");
  }
  if (!file_exists("$xml_file")) {
    $action = "PROBED";
    $cmdln = "ffprobe $exec_args '". $file['basename'] . "' > './.xml/" . $file['filename'] . ".xml'";
    exec("$cmdln");
    if (!file_exists("$xml_file")) {
      print ansiColor("red") . "error: Could not create ffprobe xml.  Check that ffprobe is exists, is executable, and in the system search path\n" . ansiColor();
      return(array($file, $options));
    }
  }
  else {
    $action = "INFO";
  }
  if (file_exists("$xml_file")) {
    $xml = simplexml_load_file("$xml_file");
    $xml_filesize = getXmlAttribute($xml->format, "size");
  }
  if (!isset($xml) ||
    empty($xml) ||
    (!empty($xml_filesize) && (int) $xml_filesize != filesize($file['basename']))
  ) {
    print "Stale xml detected.  Initiating new probe...\n";
    unlink("$xml_file");
    list($file, $info) = ffprobe($file, $options);
    $xml = simplexml_load_file("$xml_file");
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
            $info['video']['codec_name'] = getXmlAttribute($stream, "codec_name");
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

            if (!isset($stream->tags->tag)) {
              $stream->tags->tag = null;
            }
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
            $info['audio']['codec_name'] = getXmlAttribute($stream, "codec_name");
            $info['audio']['channels'] = getXmlAttribute($stream, "channels");
            $info['audio']['sample_rate'] = getXmlAttribute($stream, "sample_rate");
            $info['audio']['bitrate'] = getXmlAttribute($stream, "bit_rate");

            if (!isset($stream->tags->tag)) {
              $stream->tags->tag = null;
            }
            foreach ($stream->tags->tag as $tag) {
              $tag_key = strtolower(getXmlAttribute($tag, "key"));
              $tag_val = strtolower(getXmlAttribute($tag, "value"));
              $tag_val = str_replace('(', '', str_replace('\'', '', $tag_val));
              // print "\n" . $tag_key . " : " . $tag_val . "\n";
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
            $info['subtitle']['codec_name'] = getXmlAttribute($stream, "codec_name");
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
    $file = rename_byCodecs($file, $options, $info);
    $file = rename_PlexStandards($file, $options, $info);
    print ansiColor("blue") . "$action: " . ansiColor("green") . $file['basename'] . ansiColor("blue") . " (";
    if (!empty($info['video']) && !empty($info['video']['bitrate'])) {
      print $info['video']['codec_type'] . ":" . $info['video']['codec'] . ", " . $info['video']['width'] . "x" . $info['video']['height'] . ", " . formatBytes($info['video']['bitrate'], 2, false) . "PS | ";
    }
    if (!empty($info['audio']) && !empty($info['audio']['bitrate'])) {
      print $info['audio']['codec_type'] . ":" . $info['audio']['codec'] . ", CH." . $info['audio']['channels'] . ", " . formatBytes($info['audio']['bitrate'], 0, false) . "PS";
    }
    if (!empty($info['subtitle'])) {
      print "SUB: " . $info['subtitle']['codec_type'] . ":" . $info['subtitle']['codec'];
    }
    print ansiColor("blue") . ")\n" . ansiColor();
  }
  elseif (
    (empty($info['video']) || empty($info['audio'])) && !$options['args']['test']
  ) {
    $missing = null;
    if ($file['extension'] == $options['args']['extension']) {
      if (empty($info['video'])) {
        $missing = "video";
      }
      if (empty($info['audio'])) {
        $missing += ' audio';
      }
      print ansiColor("blue") . " " . $file['basename'] . " $missing track is missing\n" . ansiColor();
      print "Delete " . $file['basename'] . "?  [Y/n] >";
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
    }

    // Check if color_range is in options
    if (
      !empty($info['video']['color_range']) && 
      in_array($info['video']['color_range'],$options['video']['hdr']['color_range'])
    ) {
      print "Incompatible color_range detected: " . $file['basename'] . "\n";
      $info = array();
    }
  }
  
  return(array($file, $info));
}

function ffprobeXml($file) {
  $cwd = getcwd();
  chdir($file['dirname']);
  $exec_args = "-v quiet -print_format xml -show_format -show_streams";
  $cmdln = "ffprobe $exec_args '". $file['basename'] . ansiColor() . "'";
print  ansiColor("green") . "$cmdln" . "\n";
  exec("$cmdln", $xml);
  chdir($cwd);
  return($xml);
}