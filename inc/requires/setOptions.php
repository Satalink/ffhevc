<?php
/**
 *   input: option
 *   output: options
 *   purpose: Set options using my_config setting in conf/default.php or configured profile defined
 * 
 */
 function setOption($option) {
  $options['profile'] = $option[0];
  $options['format'] = $option[1];
  $options['extension'] = $option[2];
  $options['video']['codec'] = $option[3];
  $options['video']['pix_fmt'] = $option[4];
  $options['video']['codec_name'] = $option[5];
  $options['video']['codec_long_name'] = $option[6];
  return($options);
}