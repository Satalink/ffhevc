<?php
/**
 * Default configuration
 * 
 * view inc_requires/options.php for more detailed option settings
 */
$args = array(
  // conf file that contains your media directroy ffenc encoding settings
  "media_paths_file" => __DIR__ . DIRECTORY_SEPARATOR . "media_path_keys.php",  

  // Configuration Settings
  "max_processes" => 1,         // maximum number of instances this application can run

  "priority" => "10",          // ffmpeg encoding process processor priority: -20 to 19 (19 is has lowest priorty, 10 is default sytem process priority)
                               // -20 is critical priority ( My mouse doesn't move.. oh there it goes.. oh no it's not moving again.)

  "stop" => "/tmp/hevc.stop",   // If this file exists the process will exist after finishing with the current file. Required to have it
                                // set to but you can change it to whatever works for you.  (including /dev/null if you don't want it at all).
                                // You might find it useful to create an alias stop='touch /tmp/hevc.stop' if you want to stop a huge cron job quickly.
  
  "remove_stale_stop" => true,  // If a "stop" file exists from a previous CTRL-C, remove it when you start ffhevc again if detected.
                                // This may be helpful if you have cronjobs setup AND run commandline as well.  Retaining the "stop" file 
                                // would prevent a cron from auto-resuming a job you CTRL-C quit.

  "show_progress" => true,      // Displays percentage of items scanned in media directory.

  "extension" => "mkv",         // The desired media container format of your media files.
  
  "rename" => true,             // Rename files to standard filenaming specification
                                // see [https://support.plex.tv/articles/naming-and-organizing-your-tv-show-files/]
                                //
                                //  from:  movie.title.1999.Bluray.h264.ac3.mkv
                                //    to:  Movie Title (1999) [WebDL x265 EAC3].mkv

  "remove_illegal_chars"  => true,
                                // Remove single quotes from filenames. (Not part of the renaming function.)
                                //   from: Jim's Movie (1997) [...].mkv
                                //     to: Jims Movie (1997).mkv

  "display_banner" => true,     // Display the ffHEVC banner on run

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

/*  DO NOT EDIT THIS */
if (file_exists($args['media_paths_file'])) {
  require_once $args['media_paths_file'];
} else {
  print ansiColor("red") . $args['media_paths_file'] . " not found.\n" . 
  ansiColor("blue") . "Please copy the example and configure it for your media directories.\n" . ansiColor();
}