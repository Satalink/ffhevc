<?php
/**
 *  input: bytes, precision, kbyte
 *  output: bytes[Unit]  
 *  purpose: Returns bytes in Bytes, KiloBytes, MegaBytes, etc. to precision decimals points
 * 
 *  e.g.  formatBytes(1024, 1, 1) =>  1.0 KB
 */

function formatBytes($bytes, $precision, $kbyte) {
  $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
  $bytes = (int) max($bytes, 0);

  if ($kbyte == true) {
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
  }
  else {
    $pow = floor(($bytes ? log($bytes) : 0) / log(1000));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1000, $pow);
  }
  return round($bytes, $precision) . ' ' . $units[$pow];
}