#!/usr/bin/env php
<?php
$VERSION = 20240128.1658;

// Init Stuff
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

$self = explode(DIRECTORY_SEPARATOR, $_SERVER['PHP_SELF']);
$args['application'] = end($self);
$options = getDefaultOptions($args, $location_config);  //Initialize options

list($options, $args, $dirs) = get_CommandLineArgs($options, $argv, $args, $stats);
checkProcessCount($args, $options);   //Don't melt my CPU!

/* ----------MAIN------------------ */

foreach ($dirs as $key => $dir) {
  $stats = processRecursive($dir, $options, $args, $stats);
  showStats($stats);
}