#!/usr/bin/php
<?php
/**
 * 
 */

$VERSION = 20240211.1036;
require __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR .'_includes.php';
declare(ticks = 1);
register_shutdown_function("stop", $options);

/*
* ---------- MAIN ----------- 
*/

if (file_exists($options['args']['stop'])) {
  if (filesize($options['args']['stop']) && !$args['remove_stale_stop']) {
    print charTimes(40, "#", "blue") . "\n";
    print ansiColor("blue") . "#  STOP FILE DETECTED: " . ansiColor("red") . $options['args']['stop'] . ansiColor() . "\n";
    print ansiColor("blue") . "#  Previous process was CTRL-C stopped on " . ansiColor() . "\n";
    print ansiColor("blue") . "#  " . date("Y-m-d h:i:s", filectime($options['args']['stop'])) . ansiColor() . "\n";
    print charTimes(40, "#", "blue") . "\n";
    exit(1);
  } else {
    unlink($options['args']['stop']);  //Remove for previous normal shutdown
  }
}

if ($args['display_banner']) appBanner(array('f','f','H','E','V','C'));

foreach ($dirs as $key => $dir) {
  $stats = processRecursive($dir, $options, $args, $stats);
  showStats($stats);
}