<?php
/** 
 * 
 * Purpose: Preconfigure media directories with max quality settings
 *  
 * Definition of data:
 * path           = path where media files reside
 * audioCh        = number of audio channels
 * audioK         = audio bitrate Kbps
 * vmin           = variable video quality minimum (ffmpeg param)
 * vmax           = variable video quality maxium (ffmpeg param)
 * quality_factor = quality multiplier based on 1Kbps per pixel ie(1920x1080=3000 or 3Mbps)
 *                  quality factor of 1.5 adds 1500 quality multiplier (3Mbps * 1.5)=> 4.5Mbps
 * scale          = max width resolution. (720, 1080, 1440, 2160, etc)
 * fps            = frames per second (24, 29.97, 30, 60, etc)
 * contrast       = contrast adjust video output (1 is no change, 0.9 lowers the contrast by 0.1, 1.2 increase contrast by 0.2) (ffmpeg param)
 * brightness     = adjust the video brightness (similar to contrast setting.  base 1 = no change)
 * saturation     = adjust the video saturation (similar to contrast setting ...)
 * gamma          = adjust the video gamma (similar to contrast setting ...)
 * destination    = optional path to move the re-encoded media to. 
 *                  If you have a recievable directory, this would move the re-encoded file to your desired 
 *                  destination directory.
 */

// CONFIGURE TO YOUR MEDIA DIRECTORY NEEDS - Use above array for reference of value lables
$location_config = array(
  "gopro" => "/cygdrive/i/Videos/GoPro|2|128k|10|32|1.29|720|30|1.07|0.02|1.02|0.95|/cygdrive/i/Videos/Archive",
  "dashcam" => "/cygdrive/i/Videos/Dashcam|2|192k|7|37|1.0|1080|25|1|0|1|1",
  "archive" => "/cygdrive/i/Videos/Archive|6|96k|10|38|0.9|720|25|1|0|1|1",
  "tv" => "/cygdrive/e/TV_Shows|2|256k|1|36|1.29|1080|25|1|0|1|1",
  "atv" => "/cygdrive/i/TV_Shows|2|192k|1|36|1.11|1080|25|1|0|1|1",
  "temp" => "/cygdrive/f/Temp/30day|6|640k|1|33|1.33|2160|30|1|0|1|1",
  "mov" =>  "/cygdrive/f/Movies|6|640k|1|33|1.33|2160|30|1|0|1|1",
  "amov" => "/cygdrive/g/Movies|2|256k|1|33|1.29|1080|30|1|0|1|1",
  "xmas" => "/cygdrive/g/Christmas_Movies|2|256k|1|33|1.33|2160|30|1|0|1|1",
  );


/****  DO NOT EDIT BELOW HERE ****/
$locLabels = array(
  // This is static -- DO NOT CHANGE
  "path","audioCh","audioK","vmin","vmax","quality_factor","scale","fps","contrast","brightness","saturation","gamma","destination"
);
$locations = array();
foreach ($location_config as $key => $locConf) {
  $i = 0;
  $locVals = explode("|", $locConf);
  $locations["$key"] = array();
  foreach ($locVals as $locVal) {
    $locations["$key"]["$locLabels[$i]"] = "$locVal";
    $i++;
  }
}