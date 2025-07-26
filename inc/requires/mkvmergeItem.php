<?php
/**
 *   input: $file, $fileorig, $options, $info
 *   output: $file, $fileorig
 */

function mkvmergeItem($file, $fileorig, $options, $info)
{
  //Preprocess with mkvmerge (if in path)
  $mkvm_ext = ".mkv.merge";

  // //Perms test and fix (sometimes USB drives get perm wacked)
  // if (`which setfacl 2> /dev/null`) {
  //   $cmdln = 'setfacl --set "user::rw-,group::r--,other::r--"' . ' "' . $file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'] . '"';
  //    system("$cmdln 2>&1 > /dev/null", $status);
  // }


  if (empty($file) || empty($options) || empty($info)) return (array([], [], []));
  if (file_exists($file['filename'] . $mkvm_ext)) unlink ($file['filename'] . $mkvm_ext); // leftover detected
  if (!isset($fileorig)) $fileorig =[];
  if (`which mkvmerge 2> /dev/null` && 
       !$options['args']['nomkvmerge'] && 
       !isStopped($options)) {
    if (
      (
        !$info['format']['mkvmerged'] && 
        !$info['format']['exclude'] && 
        !$options['args']['exclude']
      ) || $options['args']['override']
    ) { 
      print ansiColor("blue") . "Preprocessing: " . 
            ansiColor("red") . $file['basename'] . 
            ansiColor() . " (" . formatBytes(filesize($file['basename']), 2, true) . ")\n" .
            ansiColor();
      $cmdln    = "mkvmerge";
      if (!empty($options['args']['language'])) {
        $cmdln .=
          " --language 0:" . $options['args']['language'] .
          " --language 1:" . $options['args']['language'];
      }
      if (!empty($info['video']['index'])) {
        $cmdln .=
          " --video-tracks " . $info['video']['index'];
      }
      if (!empty($info['audio']['index'])) {
        $cmdln .=
          " --audio-tracks " . $info['audio']['index'] .
          " --track-order " . "0:" . $info['video']['index'] . "," . "1:" . $info['audio']['index'];
      } else {
        $cmdln .=
        " --audio-tracks " . $options['args']['language'];
        " --default-language " . $options['args']['language'];
      }
      $cmdln .=
        " --no-attachments";
      if ($options['args']['filter_foreign'] && !empty($options['args']['language'])) {
        $cmdln .=
          " --subtitle-tracks '" . $options['args']['language'] . "'" .
          " --track-tags '" . $options['args']['language'] . "'";
      }
      $cmdln .= " --output '" . $file['filename'] . $mkvm_ext . "' '" . $file['basename'] . "'";

      if ($options['args']['verbose']) {
        print "\n\n" . ansiColor("green") . "$cmdln\n" . ansiColor();
      }
      if (!$options['args']['test']) {
        system("$cmdln 2>&1", $status);
        if ($status == 255) {
          // status(255) => CTRL-C
          // Restore and Cleanup
          if(!empty($fileorig) && !empty($file)) {
            rename($fileorig['basename'], $file['basename']); 
            if(file_exists($file['filename'] . $mkvm_ext)) {
              unlink($file['filename'] . $mkvm_ext);
            }
          }        
          stop($options, time());
          return (array([], []));
        }
        if (file_exists($file['filename'] . $mkvm_ext) && file_exists($file['basename'])) {
          if (empty($fileorig)) {
            rename($file['basename'], $file['filename'] . ".orig." . $file['extension']);
            $fileorig = pathinfo($file['filename'] . ".orig." . $file['extension']);
          }
          rename($file['filename'] . $mkvm_ext, $file['basename']);
          $file     = pathinfo($file['basename']);
          setXmlFormatAttribute($file, 'mkvmerged', true);
          $options['args']['mkvmerged'] = true;
        }
      }
    }
  }
  return (array($file, $fileorig, $options));
}