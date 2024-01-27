# Project Title

## Table of Contents

- [About](#about)
- [Example Usage](#example)
- [Getting Started](#getting_started)
- [Contributing](../CONTRIBUTING.md)

## About <a name = "about"></a>

FFhevc uses FFmpeg and FFprobe to convert to, anaylize, and re-encode mkv videos.  It allows you to configure maximum qaulity and resolution per media directory.  Optionally, It can process incoming files and move them to their destination directory once re-encoded.  If you have mkvmerge installed, FFhevc will use it to filter out unwanted tracks such as foreign language and director comment tracks.  

### Example Usage <a name = "example"></a>

If you have a movies, movie archive, tv shows, tv show archive setup, you can set up ffhevc cronjobs to scan each media directory for videos that are above your quality/resolution set limits.  FFhevc will re-encode them according to your configuration settings per media directory.

## Getting Started <a name = "getting_started"></a>

### Prerequisites

  Windows (with <a href="" target=_blank >Cygwin</a>), Linux, or MacOS
  php 7.4+
  <a href="https://ffmpeg.org/download.html" target=_blank>FFmpeg</a>
  ffprobe (included in FFmpeg install)
  <a href="https://www.matroska.org/downloads/mkvtoolnix.html" target=_blank>mkvmerg</a> (optional but recommeneded)


### Installing

  git clone ffmpeg onto your computer and create a symbolic link to the ffmpeg.php file somewhere that is in your $PATH: (~/bin/ffhevc) for example.  You can name the symbolic link whatever you like (I use `hevc`).. whatever works for you.

  Ensure ffmpeg, ffprobe, mkvmerge and php are in your shell $PATH.  use `which ffmpeg` (etc.. etc..) to check.

  FFhevc was written on a Windows/Cygwin environment.  It should work on Apple and Linux but it hasn't been tested on those systems as of yet.

  Once installed, configure the conf/settings.php and conf/media_paths_keys.php to your needs.  To try it out, I recommend creating a "test" folder and copying some mkv files into it.  Then cd into that directory and run `ffhevc` (or whatever you named your symbolic link). 

  ffhevc --help to show command line options. Using command line options overrides media_path_key settings. This allows you to specifically encode an mkv file inside of a media directory with custom quality.  If your custom encoding is higher than your directories media_path_key configuration allows, you can use --exclude to tell ffhevc to ignore the custom encoding when it scans the media directory during a manual scan or cron run.

