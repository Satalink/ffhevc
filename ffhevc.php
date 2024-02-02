#!/usr/bin/phpdbg -qrr
<?php
/**
 * 
 */

$VERSION = 20240131.2211;

require __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR .'_includes.php';
 declare(ticks = 1);
 register_shutdown_function("stop", $args);

/*
* ---------- MAIN ----------- 
*/

if (file_exists($args['stop'])) {
  if (filesize($args['stop'])) {
    print charTimes(40, "#", "blue") . "\n";
    print ansiColor("blue") . "#  STOP FILE DETECTED: " . ansiColor("red") . $args['stop'] . ansiColor() . "\n";
    print charTimes(40, "#", "blue") . "\n";
    exit(1);
  } else {
    unlink($args['stop']);  //Remove for previous normal shutdown
  }
}

$banner = array('f','f','H','E','V','C');
appBanner($banner);

foreach ($dirs as $key => $dir) {
  $stats = processRecursive($dir, $options, $args, $stats);
  showStats($stats);
}