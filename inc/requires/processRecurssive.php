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
    $stop = $args['stop'];
    if (file_exists("$stop")) {
      unlink("$stop");
      print ansiColor("red") . "STOP FILE DETECTED: $stop" . ansiColor();
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
      $item_exploded = preg_split('/\./', $item);
      $item_extension = end($item_exploded);
      if (isset($item_extension) && !empty($item_extension) && !in_array(strtolower($item_extension), $options['args']['extensions'])) {
        continue;  //skip non-accepted files by extension
      }
      $stats = processItem($dir, $item, $options, $args, $stats);
    }
  }
  return($stats);
}