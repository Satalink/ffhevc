<?php
/**
 * input: file, options
 * output:  none, sets the permissions of a file
 */

 function set_fileattrs($file, $options) {
  if (file_exists($file['basename'])) {
    if ($options['args']['owner']) chown($file['basename'], $options['args']['owner']);
    if ($options['args']['group']) chgrp($file['basename'], $options['args']['group']);
    if ($options['args']['permissions']) chmod($file['basename'], $options['args']['permissions']);
  }
}
