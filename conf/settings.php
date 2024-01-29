<?php
/**
 * Default configuration
 * 
 * view inc_requires/options.php for more detailed option settings
 */

$args = array(
  // Configuration Settings
  "max_processes" => 1,  // maximum number of instances this application can run
  "stop" => "/tmp/hevc.stop", //if this file exists the process will exist after finished with the current file
  "media_paths_file" => __DIR__ . DIRECTORY_SEPARATOR . "media_path_keys.php",
  "dot2space" => false,  // Replace dots with spaces in the title portion of the filename.
                         //  eg.  movie.title.1999.Bluray.h264.ac3.mkv
                         //  to:  Movie Title (1999).webdl.x265.eac3.mkv

  "my_config" => 'hevc-nvenc-mkv', // Map to the "opt" configuration below
  /* 
  * Use nvenc if you have nVidia GFX card that support CUDA
  */
  "opt" => array(    // Advanced user configs (Beware!)
    // CPU Processing Only
    "H264" => array("main", "mp4", ".mp4", "libx264", "yuv420p", "avc", "h264"),
    "H265" => array("main10", "matroska", ".mkv", "libx265", "yuv420p10le", "hevc", "x265"), 
    // GFX Processing video
    "H264-nvenc-mp4" => array("main", "mp4", ".mp4", "h264_nvenc", "yuv420p", "avc", "h264"),
    "H264-nvenc-mkv"  => array("main10", "matroska", ".mp4", "h264_nvenc", "p010le", "hevc", "x264"),
    "hevc-nvenc-mkv"  => array("main10", "matroska", ".mkv", "hevc_nvenc", "yuv420p10le", "hevc", "x265"),
  ),
);

require_once $args['media_paths_file'];