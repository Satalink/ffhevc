#!/usr/bin/phpdbg -qrr
<?php
/**
 * 
 */

$VERSION = 20240131.2211;

require __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR .'_includes.php';
declare(ticks = 1);
register_shutdown_function("stop", $args, $stats);

/*
* ---------- MAIN ----------- 
*/

if (file_exists($args['stop'])) {
  print "STOP FILE " . $args['stop'] . "DETECTED.";
  exit(1);
}

$banner = array('f','f','H','E','V','C');
appBanner($banner);

foreach ($dirs as $key => $dir) {
  $stats = processRecursive($dir, $options, $args, $stats);
  showStats($stats);
}