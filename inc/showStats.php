<?php
/**
 * 
 */
function showStats($stats) {
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