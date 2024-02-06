<?php
/**
 * input:  seconds
 * output: hh:mm:ss time format
 */

function seconds_toTime($seconds) {
  if(!preg_match('/^\d+:\d+:\d+$/', $seconds)) {
    $seconds = round($seconds);
    $seconds = sprintf('%02d:%02d:%02d', ($seconds/3600),($seconds/60%60), $seconds%60);
  }
  return($seconds);
}

function checkProcessCount($args, $options, $stats) {
  exec("ps -efW|grep -v grep|grep ffmpeg|wc -l", $ffcount);
  exec("ps -efW|grep -v grep|grep mkvmerge|wc -l", $mkvmcount);

  if ($ffcount[0] > $args['max_processes'] && !$options['args']['force'] && !$options['args']['test']) {
  print_r($ffcount);
    exit("ERR: $ffcount FFMPEG processes are running, max processes (" . ansiColor("red") . $args['max_processes'] .ansiColor() . ") reached\n");
  }
  elseif ($mkvmcount[0] > $args['max_processes'] && !$options['args']['force'] && !$options['args']['test']) {
    exit("ERR: $mkvmcount MKVMERGE processes are running, , max processes (" . ansiColor("red") . $args['max_processes'] .ansiColor() . ") reached\n");
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

function stop($args, $time=null) {
  // the `register_shutdown_function` won't pass time for normal shutdown of end of script
  if (!file_exists($args['stop']) || !filesize($args['stop'])) {
    touch($args['stop']);
  }
  if (isset($time)) {
    file_put_contents($args['stop'], $time, FILE_APPEND);
  }
  return;
}


function is_between(int $min, int $max, int $val){
    if ($val >= $min && $val <= $max) {
    return true;
  }
  return false;
}

function step() {
  echo "continue? [Y/n]: ";
  $handle = fopen ("php://stdin","r");
  $line = fgets($handle);
  if(trim(preg_match('/[y]/i', $line))) {
      echo "ABORTING!\n";
      exit;
  }
  fclose($handle);
}