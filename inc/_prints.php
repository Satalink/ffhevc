<?php
/**
 * input: color 
 * output: ansi color code
 */

function ansiColor($color=null) {
  $colors = array(
    "black"   => 0,
    "red"     => 1,
    "green"   => 2,
    "yellow"  => 3,
    "blue"    => 4,
    "magenta" => 5,
    "cyan"    => 6,
    "white"   => 7
  );

  $ansi = "\033[0m";
  if (isset($color)) {
    $ansi = "\033[01;3" . $colors[$color] . "m";
  }

  return("$ansi");
}

function appBanner($banner) {
  print "\n";
  print charTimes(80, "#", "blue") . "\n";
  print charTimes(25, "#", "blue");
  foreach ($banner as $bc) {
    print ansiColor("green") . "$bc" . charTimes(2, " ");
  }
  print charTimes(3, " ");
  print charTimes(26, "#", "blue");
  print "\n"; charTimes(80, "#", "blue"); print "\n\n";
}

function charTimes($x, $c, $r=null, $y=null) {
  print isset($r) ? ansiColor("$r"):'';
   for($i=0;$i<=$x;$i++) {$y.="$c";} print "$y";
   print isset($r) ? ansiColor():'';
}

function showStats($stats) {
  print "\n";
  print "\n" . charTimes(80, "#", "blue");   
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
  print charTimes(80, "#", "blue") . "\n";
}