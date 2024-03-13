#!/usr/bin/php
<?php
/**
 * 
 */

$VERSION = "24.03.13.091147";
require __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . '_includes.php';
declare(ticks=1);
register_shutdown_function("stop", $options);

/*
 * ---------- MAIN ----------- 
 */

isStopped($options, true);
if ($args['display_banner'])
  appBanner(array('f', 'f', 'H', 'E', 'V', 'C'));

foreach ($dirs as $key => $dir) {
  $global = processRecursive($dir, $options, $args, $global);
  showStats($global);
}