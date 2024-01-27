<?php
/**
 *  input: options, args
 *  output: options
 *  purpose: updates the options audio bitrate for a configured directory location
 * 
 */

function getAudioBitrate($options, $args) {
  if (!empty($args['key']) && array_key_exists($args['key'], $options['locations'])) {
    $options['audio']['bitrate'] = explode("|", $options['locations'][$args['key']])[2];
  }
  return($options);
}