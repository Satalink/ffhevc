#!/usr/bin/phpdbg -qrr
<?php
/**
 * 
 */

$mms = microtime(1);
$VERSION = 20240131.2145;

require __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR .'_includes.php';

/*
* ---------- MAIN ----------- 
*/
$banner = array('f','f','H','E','V','C');
appBanner($banner);

foreach ($dirs as $key => $dir) {
  $stats = processRecursive($dir, $options, $args, $stats);
  showStats($stats);
}