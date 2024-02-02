<?php
/**
 *  input: dir, options, args, stats
 *  output: none
 *  purpose:  Process all mkv items in current directory recursively 
 */

function processRecursive($dir, $options, $args, $stats) {
  $result = array();
  $list = array_slice(scandir("$dir"), 2);
  foreach ($list as $index => $item) {
    if (file_exists($args['stop'])) {
      print charTimes(40, "#", "blue") . "\n";
      print ansiColor("blue") . "#  STOP FILE DETECTED: " . ansiColor("red") . $args['stop'] . ansiColor() . "\n";
      print charTimes(40, "#", "blue") . "\n";
      return($stats);
    }

    if (!$options['args']['followlinks'] && is_link($dir . DIRECTORY_SEPARATOR . $item )) {
      continue;  //skip symbolic links
    }

    if (is_dir($dir . DIRECTORY_SEPARATOR . $item)) {
      if (!preg_match("/_UNPACK_/", $item)) {
        cleanXMLDir($dir, $options);
        $result["$dir" . DIRECTORY_SEPARATOR . "$item"] = processRecursive($dir . DIRECTORY_SEPARATOR . $item, $options, $args, $stats);
      }
    }
    else {
      $stats = processItem($dir, $item, $options, $args, $stats);
    }
  }
  return($stats);
}