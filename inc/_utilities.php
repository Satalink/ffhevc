<?php
/**
 * input:  seconds
 * output: hh:mm:ss time format
 */

function seconds_toTime($seconds)
{
  if (!preg_match('/^\d+:\d+:\d+$/', $seconds)) {
    $seconds = round($seconds);
    $seconds = sprintf('%02d:%02d:%02d', ($seconds / 3600), ($seconds / 60 % 60), $seconds % 60);
  }
  return ($seconds);
}

function checkProcessCount($args, $options, $global)
{
  if (!$options['args']['force'] && !$options['args']['test']) {
    exec("ps -efW|grep -v grep|grep ffmpeg|wc -l", $ffcount);
    exec("ps -efW|grep -v grep|grep mkvmerge|wc -l", $mergecount);
    if ($ffcount[0] >= $args['max_processes']) {
      exit(ansiColor("red") . "ERR: $ffcount[0] FFMPEG process(es) running, max processes (" . ansiColor("blue") . $args['max_processes'] . ansiColor("red") . ") reached\n" . ansiColor());
    } elseif ($mergecount[0] >= $args['max_processes']) {
      exit(ansiColor("red") . "ERR: $mergecount[0] MKVMERGE process(es) running, , max processes (" . ansiColor("blue") . $args['max_processes'] . ansiColor("red") . ") reached\n" . ansiColor());
    }
  }
}

function formatBytes($bytes, $precision, $kbyte)
{
  $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
  $bytes = (int) max($bytes, 0);

  if ($kbyte == true) {
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
  } else {
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1000));
    $pow   = min($pow, count($units) - 1);
    $bytes /= pow(1000, $pow);
  }
  return round($bytes, $precision) . ' ' . $units[$pow];
}


function stop($options, $time = null)
{
  // the `register_shutdown_function` won't pass time for normal shutdown of end of script
  if (isset($time)) {  //CTRL-C interupt
    file_put_contents($options['args']['stop'], $time, FILE_APPEND);
  }
  return;
}

function isStopped($options, $clear=false)
{
  $status = false;
  if (file_exists($options['args']['stop'])) {
    $status = true;
    if (filesize($options['args']['stop'])) {
      if (!$options['args']['remove_stale_stop']) {
        print charTimes(40, "#", "blue") . "\n";
        print ansiColor("blue") . "#  STOP FILE DETECTED: " . ansiColor("red") . $options['args']['stop'] . ansiColor() . "\n";
        print ansiColor("blue") . "#  Previous process was CTRL-C stopped on " . ansiColor() . "\n";
        print ansiColor("blue") . "#  " . date("Y-m-d h:i:s", filectime($options['args']['stop'])) . ansiColor() . "\n";
        print charTimes(40, "#", "blue") . "\n";
        exit;
      } else {
        if ($clear) {
          unlink($options['args']['stop']);  # remove previously CTRL-C stop
          exit;
        }
      }
    } else {
      if ($clear) {
        unlink($options['args']['stop']); # remove previously normal stop
        $status = false;
      }
    }
  }
  return ($status);
}

function moduleCheck($module)
{
  $loaded_php_modules = get_loaded_extensions();
  return (in_array($module, $loaded_php_modules));
}

function is_between(int $min, int $max, int $val)
{
  if ($val >= $min && $val <= $max) {
    return true;
  }
  return false;
}
