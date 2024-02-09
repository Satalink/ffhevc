<?php
/**
 *  input: dir, options, args, stats
 *  output: none
 *  purpose:  Process all mkv items in current directory recursively 
 */

function processRecursive($dir, $options, $args, $stats) {
  // Get Total Files to Process (once)
  if (!isset($stats['total_files'])) {
    $cmdln = "find '" . $dir . "' -not -path '*_UNPACK_*'"; $i=0;
    foreach ($options['args']['extensions'] as $ext) {
      if ($i) $cmdln .= " -or";
      $cmdln .= " -name '*." . $ext . "'"; $i++;
    }
    $cmdln .= "|wc -l";
    exec("$cmdln", $tf);    
    $stats['total_files'] = (int) $tf[0];
  }

  $list = array_slice(scandir("$dir"), 2);
  foreach ($list as $index => $item) {
    if (file_exists($options['args']['stop'])) {
      return($stats);
    }

    if (!$options['args']['followlinks'] && is_link($dir . DIRECTORY_SEPARATOR . $item )) {
      continue;  //skip symbolic links
    }

    if (is_dir($dir . DIRECTORY_SEPARATOR . $item)) {
      if (!preg_match("/_UNPACK_/", $item)) {
        cleanXMLDir($dir, $options);
        $stats = processRecursive($dir . DIRECTORY_SEPARATOR . $item, $options, $args, $stats);
      }
    }
    else {
      $stats = processItem($dir, $item, $options, $args, $stats);
    }
  }
  return($stats);
}