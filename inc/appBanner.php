<?php
/* 
*  input: banner array
*  output: prints banner
*  purpose: Displays App Banner 
*
*/

function appBanner($banner) {
  $banner = array('f','f','H','E','V','C');
  print "\n";
  print charTimes(80, "#", "blue") . "\n";
  print charTimes(25, ">", "blue");
  foreach ($banner as $bc) {
    print ansiColor("red") . "$bc" . charTimes(2, " ");
  }
  print charTimes(3, " ");
  print charTimes(26, "<", "blue");
  print "\n"; charTimes(80, "#", "blue"); print "\n\n";
}