#!/usr/bin/php
<?php
$VERSION = 20240127.0907;

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
  __DIR__ . DIRECTORY_SEPARATOR . 'inc_requires' . DIRECTORY_SEPARATOR,
  __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR,
);
foreach ($includeDirs as $includeDir) {
  foreach (glob($includeDir . "/*.php") as $incFile) {
      require_once $incFile;
  }
}

$options = getDefaultOptions($args);  //Initialize options
list($options, $args, $dirs) = get_CommandLineArgs($options, $argv, $args, $stats);

/* ----------MAIN------------------ */

//Only run one instance of this script.
exec("ps -efW|grep -v grep|grep ffmpeg|wc -l", $ffcount);
exec("ps -efW|grep -v grep|grep mkvmerge|wc -l", $mkvmcount);

if ($ffcount[0] > $args['max_processes'] && !$options['args']['force'] && !$options['args']['test']) {
  exit("ERR: $ffcount FFMPEG processes are running, max processes (" . ansiColor("red") . $args['max_processes'] .ansiColor() . ") reached\n");
}
elseif ($mkvmcount[0] > $args['max_processes'] && !$options['args']['force'] && !$options['args']['test']) {
  exit("ERR: $mkvmcount MKVMERGE processes are running, , max processes (" . ansiColor("red") . $args['max_processes'] .ansiColor() . ") reached\n");
}

foreach ($dirs as $key => $dir) {
  $stats = processRecursive($dir, $options, $args, $stats);
  print "\n" . ansiColor("blue") . "######################################\n" . ansiColor();  
  if ($stats['processed']) {
    print ansiColor("blue") . "#  Scanned Videos: " . ansiColor("green") . $stats['processed'] . "\n" . ansiColor();
  }
  if ($stats['reEncoded']) {
    print ansiColor("blue") . "#  Re-Encoded    : " . ansiColor("green") . $stats['reEncoded'] . "\n" . ansiColor();
  }
  if ($stats['byteSaved']) {
    print ansiColor("blue") . "#  Space Saved   : " . ansiColor("green") . formatBytes($stats['byteSaved'], 0, true). "\n" . ansiColor();
  }
  $totaltime = (time() - $stats['starttime']);
  print ansiColor("blue") . "#  Total Time    : " . ansiColor("green") . seconds_toTime("$totaltime") . "\n" . ansiColor();
  print ansiColor("blue") . "######################################\n" . ansiColor();
}