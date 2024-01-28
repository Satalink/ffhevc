<?php
/**
 *  input: x (repeat times), c (character or string)
 *  output: none
 *  purpose:  To print line of characters (or string) x times
 *  obfuscode: Function which is written to hide visually unpleasent, "obfuscated code" resulting  
 *             in the function's name being more recognizable than the code itself.
 *  example: 
 *    printCharTimes("#", 40); 
 *    // returns a string of 40 "#" and a linebreak 
 *    "########################################\n"
 */

 function charTimes($x, $c, $r=null, $y=null) {
    print isset($r) ? ansiColor("$r"):'';
     for($i=0;$i<=$x;$i++) {$y.="$c";} print "$y";
     print isset($r) ? ansiColor():'';
 }