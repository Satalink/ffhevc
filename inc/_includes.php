<?php

/**
 *  purpose: Load all includes. 
 */

# Include App Functions
// Init Stuff
$dirs    = array();
$cleaned = array();
$global  = array(
  'processed' => 0,
  'reEncoded' => 0,
  'byteSaved' => 0,
  'starttime' => time(),
  'percent' => 0,
);

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'settings.php';

$includeDirs = array(
  __DIR__ . DIRECTORY_SEPARATOR . 'requires' . DIRECTORY_SEPARATOR,
  __DIR__ . DIRECTORY_SEPARATOR,
);

foreach ($includeDirs as $includeDir) {
  foreach (glob($includeDir . "*.php") as $incFile) {
    require_once $incFile;
  }
}

$required_php_modules = array("SimpleXML");
foreach ($required_php_modules as $module) {
  if (!moduleCheck($module)) {
    print ansiColor("red") . "ERROR: php module '" . $module . " not installed\n" . ansiColor();
    exit;
  }
}

$options                     = getDefaultOptions($args, $location_config);  //Initialize options
$self                        = explode(DIRECTORY_SEPARATOR, $_SERVER['PHP_SELF']);
$lastkey                     = key(array_slice($self, -1, 1, true));
$args['application']         = $self[$lastkey];
list($options, $args, $dirs) = get_CommandLineArgs($options, $argv, $args, $global);
checkProcessCount($args, $options, $global);   //Don't melt my CPU!
$global['args'] = $args;
