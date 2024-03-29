# FFhevc

## First Time User ##
Copy some media to a test directory and run ffhevc against a copy.  Adjust the settings and configure your media_path_keys to suit your needs.  USE AT YOUR OWN RISK!

## Table of Contents

- [About](#about)
- [Usage](#usage)
- [Getting Started](#getting_started)
- [Examples](#examples)
- [Plex Naming Standard](#naming)
- [Contribute](../CONTRIBUTING.md)

## About <a name = "about"></a>

FFhevc uses FFmpeg and FFprobe to convert to, anaylize, and re-encode mkv videos.  It allows you to configure maximum qaulity and resolution per media directory.  If the rename flag is enabled, it will rename your media files automatically to Plex file naming Standards.  Optionally, It can process incoming files and move them to their destination directory once re-encoded.  If you have mkvmerge installed (recommended), FFhevc will use it to filter out unwanted tracks such as foreign language and director comment tracks to further reduce space.  

## Usage <a name = "usage"></a>

If you have a movies, movie archive, tv shows, tv show archive setup, you can set up ffhevc cronjobs to scan each media directory for videos that are above your quality/resolution set limits.  FFhevc will re-encode them according to your configuration settings per media directory.

## Getting Started <a name = "getting_started"></a>

### Prerequisites

<ul>
  <li>Windows (with <a href="" target=_blank >Cygwin</a>), Linux, or MacOS</li>
  <li>php 7.3.7+</li>
    <ul> <i>modules</i>:
      <li>SimpleXML</li>
    </ul>
  <li><a href="https://ffmpeg.org/download.html" target=_blank>FFmpeg</a></li>
  <li>ffprobe (included in FFmpeg install)</li>
  <li><a href="https://www.matroska.org/downloads/mkvtoolnix.html" target=_blank>mkvmerge</a> (optional but recommeneded)</li>
</ul>


### Installing

  PHP<br>
    `$> php -v`  should meet the minimum version mentioned above.<br>
    `$> php -m`  should show the required php modules mentioned above.<br>

  GIT CLONE ffmpeg onto your computer. <br>
  `$> git clone https://github.com/Satalink/ffhevc.git ffhevc`
  
  Create a symbolic link to the ffmpeg.php file somewhere that is in your $PATH: (~/bin/ffhevc) for example.  You can name the symbolic link whatever you like (I use `hevc`).. whatever works for you.  Just make sure you create the link somewhere in your $PATH.

  Ensure ffmpeg, ffprobe, mkvmerge and php are in your shell's $PATH. <br>
  `which ffmpeg` (etc.. etc..) to check.<br>
  `$> which ffmpeg`

  FFhevc was written on a Windows/Cygwin environment.  It should work on Apple and Linux but it hasn't been tested on those systems as of yet.

  Once installed, configure the conf/settings.php and conf/media_paths_keys.php to your needs.  To try it out, I recommend creating a "test" folder and copying some mkv files into it.  Then cd into that directory and run `ffhevc` (or whatever you named your symbolic link). I suggest you try single file mode, then try the whole test directory, and lastly use the key method.<br>
  `$> cd /path/to/test/media`<br>
  `$> ffhevc "my_first_test_media.mkv"`<br>
  `$> ffhevc`  << this will process the entire current directory<br>

  lastly... if you've properly configured your media_path_keys, you can use the key method.<br>
  `$> ffhevc {key}`  << where key would be the array key name in your media_path_keys i.e. tv or mov

  ffhevc --help to show command line options. Using command line options overrides media_path_key settings. This allows you to specifically encode an mkv file inside of a media directory with custom quality.  If your custom encoding is higher than your directories media_path_key configuration allows, you can use --exclude to tell ffhevc to ignore the custom encoding when it scans the media directory during a manual scan or cron run.


### Usage <a name = "examples"></a>

  > $> ffhevc.php
  
  Run in a media directory without any options or paths to scan and process the current working directory.  If there is a key defined in the conf/media_paths_keys.php file that matches your current working directory, those settings will be used.  Otherwise, global defaults defined in the inc/options.php file will be used.

  > $> ffhevc.php "My Favorite Video.mkv"

  If you supply a filename, that file will be scanned and processed (no recurssive directory scanning or processing).  If a key is defined that matches your current working directory, those settings will be used.  Otherwise, global defaults defined inc/requires/options.php file will be used.

  > $> ffhevc.php mov

  If you supply a "key" (defined in conf/media_paths_keys.php), the path defined in that key will be scanned and processed using the settings defined for that key.


### Plex Naming Standard <a name = "naming"></a>

ffhevc uses the Plex Naming Standard if the `rename` flag is set to true.
  
#### Recommend `darr Settings ()
  Radarr -> Settings -> Media Management: Standard Movie Format<br>
  `{Movie CleanTitle} ({Release Year}) - [ {Quality Title} {MediaInfo VideoCodec} {MediaInfo AudioCodec} ]`

  Sonarr -> Settings -> Media Management: Standard Episode Format<br>
  `{Series CleanTitle} - s{season:00}e{episode:00} ({Series Year}) - [ {Quality Title} {MediaInfo VideoCodec} {MediaInfo AudioCodec} ]`

  -  ref [Plex Movie Naming Standard](https://support.plex.tv/articles/naming-and-organizing-your-movie-media-files/)<br>
  -  ref [Plex TV Show Naming Standard](https://support.plex.tv/articles/naming-and-organizing-your-tv-show-files/)<br>

