<?php
/**
 *  input file, options -- optional input: resolution, acodec, vcodec, profile
 *  output: file
 *  purpose: Renames media file with new codecs after encoding
 */

function rename_byCodecs($file, $options, $resolution=null, $acodec=null, $vcodec=null, $profile=null) {
  $filename    = $file['filename'];

  $resolutions = array('480p', '720p', '1080p', '2160p', 'SD', 'HD', 'UHD');
  $vcodecs     = array("h264", "h.264", "h-264", "x-264", "x.264", "x264", "264", "h265", "h.265", "h-265", "x-265", "x.265", "x265", "265", "vc1", "hevc");
  $acodecs     = array('AAC', 'EAC3', 'AC3', 'AC4', 'MP3', 'OGG', 'FLAC', 'WMA', 'ddp5.1', 'ddp7.1', 'DTS-HD', 'DTS', 'TrueHD', 'PPCM', 'DST', 'OSQ', 'DCT', );
  $profiles    = array('Raw-HD', 'BR-Disk', 'Remux', 'Bluray', 'WebDL', 'WebRip', 'HDTV');  // Radarr Quality Profiles

  $resolution  = isset($resolution) ?: set_resString($options['video']['scale']);
  $vcodec      = isset($vcodec) ?: $options['video']['codec_long_name'];
  $acodec      = isset($acodec) ?: $options['audio']['codec'];
  $profile     = isset($profile) ?: $options['video']['profile'];

  $filename_set = false;
  if (preg_match("/\([1-2]\d{3}\)$/", $filename)) {
      $filename = $file['filename'] . ".$resolution.$vcodec.$acodec";
      $filename_set = true;
  }
  if (!$filename_set) {
    foreach ($profiles as $pf) {
      if (preg_match("/$pf/i", $filename)) {
        $filename = str_ireplace($pf, "$profile", "$filename");
        break;
      }
    }
    foreach ($resolutions as $res) {
      if (preg_match("/$res/i", $filename)) {
        $filename = str_ireplace($res, "$resolution", $filename);
        break;
      }
    } 
    foreach ($vcodecs as $vc) {
      if (preg_match("/$vc/i", $filename)) {
        $filename = str_ireplace($vc, "$vcodec", "$filename");
        break;
      }    
    }
    foreach ($acodecs as $ac) {
      if (preg_match("/$ac/i", $filename)) {
        $filename = str_ireplace($ac, "$acodec", "$filename");
        break;
      }        
    }
  }
  rename($file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'], $file['dirname'] . DIRECTORY_SEPARATOR . $filename . "." . $file['extension']);
  $file = pathinfo($file['dirname'] . DIRECTORY_SEPARATOR . $filename . "." . $file['extension']);
  return($file);
}