<?php
/**
 * input: scale (integer value of the video width)
 * output: resolution group
 */

function get_resolution($scale) {
  switch ($scale) {
    case $scale > 180 && $scale <= 240:
      $res = "480p";
      break;
    case $scale > 240 && $scale <= 360:
      $res = "360p";
      break;
    case $scale > 360 && $scale <= 480:
      $res = "480p";
      break;
    case $scale > 480 && $scale <= 720:
      $res = "720p";
      break;
    case $scale > 720 && $scale <= 1080:
      $res = "1080p";
      break;
    case $scale > 1080 && $scale <= 1440:
      $res = "1440p";
      break;
    case $scale > 1440 && $scale <= 2160:
      $res = "2160p";
      break;
    case $scale > 2160 && $scale <= 2880:
      $res = "2880p";
      break;
    case $scale > 2160 && $scale <= 4320:
      $res = "4320p";
      break; 
  }
  return($res);
}