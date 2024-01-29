<?php
/**
 * input: scale (integer value of the video width)
 * output: resolution group
 */

function set_resString($scale) {
  switch ($scale) {
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
  }
  return($res);
}