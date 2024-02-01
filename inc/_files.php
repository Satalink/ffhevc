<?php
/**
 * input: file, options
 * output:  none, sets the permissions of a file
 */

 function set_fileattr($file, $options) {
  if (($options['args']['keepowner'] || isset($options['owner']) )) {
    if (file_exists($file['basename'])) {
      chown($file['basename'], $options['owner']);
      chgrp($file['basename'], $options['group']);
      chmod($file['basename'], $options['args']['permissions']);
    }
  }
}