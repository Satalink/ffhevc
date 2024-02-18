<?php
/**
 *  
 */

function convertItem($file, $options, $info)
{
  // Convert Media  i.e.( mp4 -> mkv )
  $fileorig = []; // assume no convertion needed
  if (!empty($info) && $file['extension'] !== $options['args']['extension']) {
    $fileorig = $file;
    print ansiColor("red") . $file['filename'] . "." . $file['extension'] . "\n" . ansiColor();
    print ansiColor("blue") . "Format Convert: " . ansiColor("red") . $file['extension'] . ansiColor("blue") . " => " . ansiColor("green") . $options['args']['extension'] . "\n" . ansiColor();
    $cmdln = "ffmpeg " .
      "-hide_banner " .
      "-v " . $options['args']['loglev'] . " " .
      "-i '" . $file['filename'] . "." . $file['extension'] . "' " .
      "-c copy " .
      "-sn " .
      "-stats " .
      "-stats_period " . $options['args']['stats_period'] . " " .
      "-y '" . $file['filename'] . "." . $options['args']['extension'] . "'";
    if ($options['args']['verbose']) {
      print ansiColor("green") . "$cmdln\n" . ansiColor();
    }
    if (!$options['args']['test']) {
      exec("$cmdln", $output, $status);
      if ($status == 255) {
        //status(255) => CTRL-C
        // Restore and Cleanup
        if(!empty($fileorig) && !empty($file)) {
          if (file_exists($fileorig['basename']) && file_exists($file['filename'] . "." .  $options['args']['extension'])) {
            unlink ($file['filename'] . "." . $options['args']['extension']);
          }
        }        
        stop($options, time());
        return (array([], [] , $options));
      }
      if (file_exists($file['filename'] . "." . $options['args']['extension'])) {
        $fileorig = $file;
        $file     = pathinfo($file['filename'] . "." . $options['args']['extension']);
      }
    }
  }
  return (array($file, $fileorig, $options));
}