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
  $color = isset($color) ? $color : false;
  if ($color) {
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

function charTimes($times, $char, $color=null, $crlf=null) {
   print isset($color) ? ansiColor("$color"):'';
   for($i=0;$i<=$times;$i++) {$crlf.="$char";} print "$crlf";
   print isset($color) ? ansiColor():'';
}

function showStats($global) {
  print "\n";
  print "\n" . charTimes(80, "#", "blue");   
  if ($global['processed']) {
    print ansiColor("blue") . "#  Scanned Videos: " . ansiColor("green") . $global['processed'] . "\n" . ansiColor();
  }
  if ($global['reEncoded']) {
    print ansiColor("blue") . "#  Re-Encoded    : " . ansiColor("green") . $global['reEncoded'] . "\n" . ansiColor();
  }
  if ($global['byteSaved']) {
    print ansiColor("blue") . "#  Space Saved   : " . ansiColor("green") . formatBytes($global['byteSaved'], 0, true). "\n" . ansiColor();
  }
  $totaltime = (time() - $global['starttime']);
  print ansiColor("blue") . "#  Total Time    : " . ansiColor("green") . seconds_toTime("$totaltime") . "\n" . ansiColor();
  print charTimes(80, "#", "blue") . "\n";
}