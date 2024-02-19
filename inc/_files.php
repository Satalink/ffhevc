<?php
/**
 * input: file, options
 * output:  none, sets the permissions of a file
 */

function set_fileattrs($file, $options)
{
  if (file_exists($file['basename'])) {
    if ($options['args']['owner'])
      chown($file['basename'], $options['args']['owner']);
    if ($options['args']['group'])
      chgrp($file['basename'], $options['args']['group']);
    if ($options['args']['permissions'])
      chmod($file['basename'], $options['args']['permissions']);
  }
}

function cleanup ($file) {
  $status = false;
  // Quietly clean leftover aborted process files
  if (preg_match('/.orig.mkv$/', $file['basename'])) { // file to be processed should never be orig
    $cmdln = "find . -type f";

    $episode = '';  
    preg_match_all('/s?\d+(e|x)\d+/i', $file['filename'], $data ,PREG_PATTERN_ORDER);
    if (!empty($data[0])) {
      $cmdln .= ' -name "*' . $data[0][0] . '*"';
    }    

    exec("$cmdln", $dir_scan);
    foreach ($dir_scan as $scan_file) {
      $sfile = pathinfo($scan_file);
      if ($sfile['basename'] != $file['basename'] && $sfile['extension'] == $file['extension']) {
        $sfile_xmlfile = "./.xml" . DIRECTORY_SEPARATOR . $sfile['filename'] . ".xml";
        if (file_exists("$sfile_xmlfile")) {
          $xml = simplexml_load_file("$sfile_xmlfile");
          $xml_filesize = getXmlAttribute($xml->format, "size") ? getXmlAttribute($xml->format, "size") : 0;
          if (isset($xml) && !empty($xml) && (file_exists($sfile['basename']) && (int) $xml_filesize == filesize($sfile['basename']))) {
            if (file_exists($file['basename'])) {
              unlink($file['basename']);  // leftover orig file detected
            }
            $lo_xml = "." . DIRECTORY_SEPARATOR . ".xml" . DIRECTORY_SEPARATOR . $file['basename'] . ".xml";
            if (file_exists($lo_xml)) {
              unlink($lo_xml);
            }
          } else {
            rename($file['basename'], str_replace('.orig.', '.', $file['basename']));
          }
        }
      } elseif ($sfile['extension'] == "merge") {
        unlink ($sfile['basename']); // leftover merge file detected
      }
    }
    return(true);
  }
  return ($status);
}