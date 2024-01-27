<?php
/**
 * input:  seconds
 * output: hh:mm:ss time format
 */

function seconds_toTime($seconds) {
  if(!preg_match('/^\d+:\d+:\d+$/', $seconds)) {
    $seconds = round($seconds);
    $seconds = sprintf('%02d:%02d:%02d', ($seconds/3600),($seconds/60%60), $seconds%60);
  }
  return($seconds);
}