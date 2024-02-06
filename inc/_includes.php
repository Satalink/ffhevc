<?php

/**
 *  purpose: Load all includes. 
 *           Helpful to test cases that test a function without running the full application.
 */

# Include App Functions
// Init Stuff
$dirs = array();
$cleaned = array();     
$stats = array(
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

// Check required PHP modules
$required_php_modules = array("SimpleXML");
$loaded_php_modules = get_loaded_extensions();
foreach ($required_php_modules as $rphpm) {
  if (!in_array($rphpm, $loaded_php_modules)) {
    print ansiColor("blue") . "\n\n" . charTimes("#", 40) . "\n";
    print ansiColor("red") . "ERROR: php module '".$rphpm." not installed\n" . ansiColor();
    print ansiColor("white") . "$> php -m" . ansiColor("blue") . "  to see loaded php modules";
  }
}

$options = getDefaultOptions($args, $location_config);  //Initialize options
$self = explode(DIRECTORY_SEPARATOR, $_SERVER['PHP_SELF']);
$lastkey = key(array_slice($self, -1, 1, true));
$args['application'] = $self[$lastkey];
list($options, $args, $dirs) = get_CommandLineArgs($options, $argv, $args, $stats);
checkProcessCount($args, $options, $stats);   //Don't melt my CPU!
