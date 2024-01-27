<?php
/**
 *  Purpose: process command line arguments 
 * 
 */

function get_CommandLineArgs($options, $argv, $args, $stats) {
  if (count($argv) > 1) {
    $args['index'] = $i = 0;
    foreach ($argv as $key => $arg) {
      if (is_file($arg)) {
        $file = pathinfo($arg);
        if ($file['basename'] == $args['application']) {
          unset($file);
        }
      }
      elseif (preg_match('/^--/', $arg)) {
        $args[] = $arg;
        if (!$args['index']) {
          $args['index'] = $i;
          if ($args['index'] > 1) {
            exit(ansiColor("red") . "\n  Invalid First Option: \"$arg $i\"\n" . ansiColor() . "  Type $>" . ansiColor("blue") . $args['application'] . " --help\n" . ansiColor());
          }
        }
      }
      elseif (preg_match('/^-/', $arg)) {
        exit("\n".ansiColor("red")."  Unknown option: \"$arg\"\n".ansiColor()."  Type $>".ansiColor("green") . $args['application'] . " --help\n" . ansiColor());
      }
      else {
        if (array_key_exists($arg, $options['locations'])) {
          $args['key'] = $arg;
        }
      }
      $i++;
    }
    //Single File Processing
    if (!empty($file)) {
      $options = getLocationOptions($options, $args, $file['dirname']);
      $stats = processItem($file['dirname'], $file['basename'], $options, $args, $stats);
      showStats($stats);
      exit;
    }

    //Defined Key Scan and Process
    if (isset($args['key'])) {
      $location = str_replace(" ", "\ ", $options['locations'][$args['key']]);
      $key = $args['key'];
      $dirs["$key"] = explode("|", $location)[0];
      $options = getLocationOptions($options, $args, explode("|", $location)[0]);
    }
    elseif ($args['index'] > 0) {
      $dir = getcwd();
      $options = getLocationOptions($options, $args, $dir);
      if (!empty($options['args']) && array_key_exists("key", $options['args'])) {
        $args['key'] = $options['args']['key'];
      }
      $dirs = array($args['key'] => $dir);
    }
    else {
      print "\n" . ansiColor("yellow") . "Defined Locations:" . ansiColor() . "\n";
      print_r($options['locations']);
      exit(ansiColor("red") . "  Unknown location: \"$argv[1]\"\n" . ansiColor("green") . "  Edit \$options['locations'] to add it OR create and define media_paths_file within the script." . ansiColor());
    }
  }
  else {
    //no arguments, use current dir to parse location settings
    $dir = getcwd();
    $options = getLocationOptions($options, $args, $dir);
    $args['key'] = null;
    if (!empty($options['args']) && array_key_exists("key", $options['args'])) {
      $args['key'] = $options['args']['key'];
    }
    $dirs = array($args['key'] => $dir);
  }
  return array($options, $args, $dirs, $stats);
}