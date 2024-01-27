#!/usr/bin/php
<?php
$VERSION = 20240127.1349;

// Init Stuff

$HOME = getenv("HOME");
$self = explode(DIRECTORY_SEPARATOR, $_SERVER['PHP_SELF']);
$dirs = array();
$cleaned = array();
$stats = array(
  'processed' => 0,
  'reEncoded' => 0,
  'byteSaved' => 0,
  'starttime' => time()
 );
 

# Include App Functions
include __DIR__ . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'settings.php';
$includeDirs = array(
  __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'requires' . DIRECTORY_SEPARATOR,
  __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR,
);
foreach ($includeDirs as $includeDir) {
  foreach (glob($includeDir . "/*.php") as $incFile) {
      require_once $incFile;
  }
}

$options = getDefaultOptions($args);  //Initialize options
list($options, $args, $dirs) = get_CommandLineArgs($options, $argv, $args, $stats);
checkProcessCount($args, $options);   //Don't melt my CPU!

/* ----------MAIN------------------ */

foreach ($dirs as $key => $dir) {
  $stats = processRecursive($dir, $options, $args, $stats);
  showStats($stats);
}