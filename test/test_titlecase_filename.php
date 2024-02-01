#!/usr/bin/phpdbg -qrr
<?php
// prod shebang --> #!/usr/bin/env php
$mms = microtime(true);
require __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR .'_includes.php';

$t = rand(0,1);
$w = rand(1,3);

$title = getRandTitle($t, $w);

/**  TEST CASE */
$filename = __DIR__ . DIRECTORY_SEPARATOR . "$title";
$file = pathinfo("$filename");

//print_r($file);
// File must exist for function to rename
exec("touch '$file[basename]'");

$file = rename_PlexStandards($file, $options, $info);
$filename = $file['dirname'] . DIRECTORY_SEPARATOR . $file['basename'];
if (file_exists("$filename")) {
  unlink ("$filename");
}
//print_r($file);

print (microtime(1) - $mms) . "ms\n";

function getRandTitle($t=1, $w=2) {
  $title = '';
  $spec = '';
  $ext = ".mkv";
  $types = array(
    0 => "tv",
    1 => "mov",
  );
  $dems = [" ",".","_","-"];
  $dem = $dems[rand(0, count($dems) -1)];
  $dem2 = $dems[rand(0, count($dems) -1)];
  $words = ["1923"];
            //, "the", "amazing", "usa", "tree", "tesla", "machine", "fbi", "exact", "maximum",
            //"wife", "dog", "down", "guns" ];  
  $year = "(" . rand(1960,date("Y")) . ")";
  $spec_q = array("720p", "1080p", "2160p");
  $spec_p = array("bluray", "redux", "webrip", "hdtv", "webdl");
  $spec_v = array("avc", "h264", "x264", "h265", "x265", "hevc");
  $spec_a = array("acc", "ac3", "eac3", "TrueHD");
  $p = rand(0,count($spec_p)-1);
  $q = rand(0,count($spec_q)-1);
  $v = rand(0,count($spec_v)-1);
  $a = rand(0,count($spec_a)-1);

  //Title Words
  for($i=0;$i<$w;$i++) {
    $title .= $words[rand(0,count($words)-1)];
    if($i < $w-1) {
      $title .= "$dem";
    }
  } 
  switch ($types[$t]) {
    case "tv":
      $s = sprintf("S%'.02d", rand(1,10));
      $e = sprintf("E%'.02d", rand(1, 24));
      $spec = "$dem" . "$dem2" . "$dem";
      $spec .= "$s" . "$e";
      break;
    case "mov":
      $sa = array(
        $spec_q[$q],
        $spec_p[$p],
        $spec_v[$v],
        $spec_a[$a],
      );
      $spec = "$dem2" . "$year" . "$dem2";
      $spec .= implode("$dem", $sa);
      break;
  }

  $filename = "$title" . "$spec" . "$ext";
  return($filename);
}