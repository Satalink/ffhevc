# FFhevc

## Dangerous Known Issue ##
CTRL-C handling is not working correctly since the code has been migrated to an app structure. Since the induction of includes / requires, CTRL-C does not successfully terminate the script correctly.  Rather it terminates the include code and returns to the parent.   <b><ins>If you CTRL-C break from this script, it can and will result in your media file being truncated (unfinished)</b></ins>.   I'm looking into fixing this (it's complicated.)   If you wish to terminate the running process, please use the stop file method. `$> touch /tmp/hevc.stop` and it will terminate after the current running media process has completed.

## Table of Contents

- [About](#about)
- [Usage](#usage)
- [Getting Started](#getting_started)
- [Examples](#examples)
- [Contribute](../CONTRIBUTING.md)

## About <a name = "about"></a>

FFhevc uses FFmpeg and FFprobe to convert to, anaylize, and re-encode mkv videos.  It allows you to configure maximum qaulity and resolution per media directory.  If the rename flag is enabled, it will rename your media files automatically to Plex file naming Standards.  Optionally, It can process incoming files and move them to their destination directory once re-encoded.  If you have mkvmerge installed (recommended), FFhevc will use it to filter out unwanted tracks such as foreign language and director comment tracks to further reduce space.  

### Usage <a name = "usage"></a>

If you have a movies, movie archive, tv shows, tv show archive setup, you can set up ffhevc cronjobs to scan each media directory for videos that are above your quality/resolution set limits.  FFhevc will re-encode them according to your configuration settings per media directory.

## Getting Started <a name = "getting_started"></a>

### Prerequisites

<ul>
  <li>Windows (with <a href="" target=_blank >Cygwin</a>), Linux, or MacOS</li>
  <li>php 7.4+</li>
  <li><a href="https://ffmpeg.org/download.html" target=_blank>FFmpeg</a></li>
  <li>ffprobe (included in FFmpeg install)</li>
  <li><a href="https://www.matroska.org/downloads/mkvtoolnix.html" target=_blank>mkvmerge</a> (optional but recommeneded)</li>
</ul>

### Installing

  git clone ffmpeg onto your computer and create a symbolic link to the ffmpeg.php file somewhere that is in your $PATH: (~/bin/ffhevc) for example.  You can name the symbolic link whatever you like (I use `hevc`).. whatever works for you.

  Ensure ffmpeg, ffprobe, mkvmerge and php are in your shell's $PATH.  use `which ffmpeg` (etc.. etc..) to check.

  FFhevc was written on a Windows/Cygwin environment.  It should work on Apple and Linux but it hasn't been tested on those systems as of yet.

  Once installed, configure the conf/settings.php and conf/media_paths_keys.php to your needs.  To try it out, I recommend creating a "test" folder and copying some mkv files into it.  Then cd into that directory and run `ffhevc` (or whatever you named your symbolic link). 

  ffhevc --help to show command line options. Using command line options overrides media_path_key settings. This allows you to specifically encode an mkv file inside of a media directory with custom quality.  If your custom encoding is higher than your directories media_path_key configuration allows, you can use --exclude to tell ffhevc to ignore the custom encoding when it scans the media directory during a manual scan or cron run.

### Usage <a name = "examples"></a>

  > $> ffhevc.php
  
  Run in a media directory without any options or paths to scan and process the current working directory.  If there is a key defined in the conf/media_paths_keys.php file that matches your current working directory, those settings will be used.  Otherwise, global defaults defined in the inc/options.php file will be used.

  > $> ffhevc.php "My Favorite Video.mkv"

  If you supply a filename, that file will be scanned and processed (no recurssive directory scanning or processing).  If a key is defined that matches your current working directory, those settings will be used.  Otherwise, global defaults defined inc/requires/options.php file will be used.

  > $> ffhevc.php mov

  If you supply a "key" (defined in conf/media_paths_keys.php), the path defined in that key will be scanned and processed using the settings defined for that key.