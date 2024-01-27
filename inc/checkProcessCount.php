<?php
/**
 *  input: args, options
 *  output: none
 *  purpose: Only run $args['max_processes'] instance(s) of this script.
 *  
 */

function checkProcessCount($args, $options) {
  exec("ps -efW|grep -v grep|grep ffmpeg|wc -l", $ffcount);
  exec("ps -efW|grep -v grep|grep mkvmerge|wc -l", $mkvmcount);

  if ($ffcount[0] > $args['max_processes'] && !$options['args']['force'] && !$options['args']['test']) {
    exit("ERR: $ffcount FFMPEG processes are running, max processes (" . ansiColor("red") . $args['max_processes'] .ansiColor() . ") reached\n");
  }
  elseif ($mkvmcount[0] > $args['max_processes'] && !$options['args']['force'] && !$options['args']['test']) {
    exit("ERR: $mkvmcount MKVMERGE processes are running, , max processes (" . ansiColor("red") . $args['max_processes'] .ansiColor() . ") reached\n");
  }
}