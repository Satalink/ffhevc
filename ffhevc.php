#!/usr/bin/env php
<?php
$VERSION = 20240128.1900;

require __DIR__ . DIRECTORY_SEPARATOR . 'includes.php';

/*
* ---------- MAIN ----------- 
*/

foreach ($dirs as $key => $dir) {
  $stats = processRecursive($dir, $options, $args, $stats);
  showStats($stats);
}