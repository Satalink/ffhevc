#!/usr/bin/php
<?php
/**
 * 
 */

$VERSION = 20240211.1049;
require __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR .'_includes.php';
declare(ticks = 1);
register_shutdown_function("stop", $options);

/*
* ---------- MAIN ----------- 
*/

stopcheck($options);
if ($args['display_banner']) appBanner(array('f','f','H','E','V','C'));

foreach ($dirs as $key => $dir) {
  $stats = processRecursive($dir, $options, $args, $stats);
  showStats($stats);
}