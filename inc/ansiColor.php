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