<?php
/**
 *  input:  options, args, dir 
 *  output: options
 *  purpose:  If this application is executed in a media directory that has a 
 *            configuration key, then use those options to encode with.
 *            Media Directory keys are configured in the conf/media_path_keys file
 */
function getLocationOptions($options, $args, $dir) {
  if ($dir == ".") {
    $dir = getcwd();
  }

  foreach ($options['locations'] as $key => $location) {

    $esc_pattern = str_replace("/", "\/", explode("|", $location)[0]);
    if (preg_match("/$esc_pattern/", $dir)) {
      $options['args']['key'] = $args['key'] = $key;
      $location = str_replace(" ", "\ ", $options['locations'][$args['key']]);
      $key = $args['key'];
      $location_param_array = explode("|", $location);
      $dirs["$key"] = $location_param_array[0];
      $options['audio']['channels'] = $location_param_array[1];
      $options['audio']['bitrate'] = $location_param_array[2];
      $options['video']['vmin'] = $location_param_array[3];
      $options['video']['vmax'] = $location_param_array[4];
      $options['video']['quality_factor'] = $location_param_array[5];
      $options['video']['scale'] = $location_param_array[6];
      $options['video']['fps'] = $location_param_array[7];
      $options['video']['contrast'] = $location_param_array[8];
      $options['video']['brightness'] = $location_param_array[9];
      $options['video']['saturation'] = $location_param_array[10];
      $options['video']['gamma'] = $location_param_array[11];
      if (array_key_exists(12, $location_param_array)) {
        $options['args']['destination'] = $location_param_array[12];
      }
    }
  }
  $options = getAudioBitrate($options, $args);
  $options = getCommandLineOptions($options, $args);
  return($options);
}