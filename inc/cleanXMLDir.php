<?php
/**
 *  input: dir, options
 *  output: none
 *  purpose: Clean abandoned xml files for media that no longer exists
 * 
 */

function cleanXMLDir($dir, $options) {
  // clean xml for nonexist media
    global $cleaned;
    if (!in_array($dir, $cleaned)) {
      chdir($dir);
      if (is_dir("./.xml")) {
        if ($dh = opendir("./.xml")) {
          while (($xmlfile = readdir($dh)) !== false) {
            if (is_dir($xmlfile)) {
              continue;
            }
            $xfile = pathinfo($xmlfile);
            $mediafile = str_replace("'", "\'", $xfile['filename']) . $options['extension'];
            if (!empty($xmlfile) && !file_exists("$mediafile") && file_exists("./.xml" . DIRECTORY_SEPARATOR . "$xmlfile")) {
              print ansiColor("blue") . "Cleaned XML for NonExists: $dir" . DIRECTORY_SEPARATOR . $mediafile . "\n" . ansiColor();
              unlink("./.xml" . DIRECTORY_SEPARATOR . $xmlfile);
            }
          }
        }
        array_push($cleaned, $dir);
      }
    }
  }