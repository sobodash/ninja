#!/usr/bin/php -q
<?
/*

NINJA

Patching system that creates format independent patches for ROM images of
various video game consoles.

Version:   1.2
Author:    Derrick Sobodash <derrick@sobodash.com>
Copyright: (c) 2004, 2012 Derrick Sobodash
Web site:  https://github.com/sobodash/ninja/
License:   BSD License <http://opensource.org/licenses/bsd-license.php>

*/

error_reporting (E_WARNING | E_PARSE);
$version = "1.2";
echo ("\nNINJA v$version (cli)\nCopyright (c) 2004, 2012 Derrick Sobodash\n");
set_time_limit(6000000);

// Check the PHP version of the user
if(phpversion() < "4.3.2")
  die(print "ERROR: PHP 4.3.2 or newer is required\n");

if ($argc < 2) { DisplayOptions(); exit; }
else
  $mode = $argv[1];

// Systems supporting extra patch info
                  //  0       1       2       3       4       5       6
$extra_info = array("raw",  "nes",  "snes", "n64",  "gb",   "gbc",  "gba",  
                  //  7       8       9      10      11      12      13
                    "ngp",  "ngpc", "sms",  "gg",   "mega", "pce",  "ws",
                  // 14      15      16      17
                    "wsc",  "lynx", "jag",  "gp32");

// Textual patch creation mode
if ($mode=="-t") {
  if ($argc < 6) { DisplayOptions(); die; }
  else {
    $format = $argv[2];
    $source = $argv[3];
    $modified = $argv[4];
    $out_file = $argv[5];
  }
  if (in_array(strtolower($format), $extra_info)===FALSE) {
    print "Mode not supported\nDefaulting to RAW\n";
    $format = "raw";
  }
  textualpatch($format, $source, $modified, $out_file, 0);
}

// Compressed textual patch creation mode
if ($mode=="-tz") {
  if ($argc < 6) { DisplayOptions(); die; }
  else {
    $format = $argv[2];
    $source = $argv[3];
    $modified = $argv[4];
    $out_file = $argv[5];
  }
  if (in_array(strtolower($format), $extra_info)===FALSE) {
    print "Mode not supported\nDefaulting to RAW\n";
    $format = "raw";
  }
  textualpatch($format, $source, $modified, $out_file, 1);
}

// Binary patch creation mode
if ($mode=="-b") {
  if ($argc < 6) { DisplayOptions(); die; }
  else {
    $format = $argv[2];
    $source = $argv[3];
    $modified = $argv[4];
    $out_file = $argv[5];
  }
  if (in_array(strtolower($format), $extra_info)===FALSE) {
    print "Mode not supported\nDefaulting to RAW\n";
    $format = "raw";
  }
  binarypatch($format, $source, $modified, $out_file, 0);
}

// Compressed binary patch creation mode
if ($mode=="-bz") {
  if ($argc < 6) { DisplayOptions(); die; }
  else {
    $format = $argv[2];
    $source = $argv[3];
    $modified = $argv[4];
    $out_file = $argv[5];
  }
  if (in_array(strtolower($format), $extra_info)===FALSE) {
    print "Mode not supported\nDefaulting to RAW\n";
    $format = "raw";
  }
  binarypatch($format, $source, $modified, $out_file, 1);
}

// Patching mode
elseif ($mode=="-p") {
  if ($argc < 4) { DisplayOptions(); die; }
  else {
    $patch = $argv[2];
    $target = $argv[3];
  }
  $fd = fopen($patch, "rb");
  $format = fread($fd, 5);
  if($format == "PATCH") {
    print "Patch identified as IPS...\n";
    ips_patch($patch, $target);
  }
  elseif($format == "NINJA") {
    $version = fread($fd, 1);
    if($version != "1")
      die(print "ERROR: This patch is for a newer version of NINJA\n");
    $format = fread($fd, 2);
    if($format == "T" . chr(0xa)) {
      print "Patch identified as textual RUP...\n";
      rup_text_patch($patch, $target, 0);
    }
    elseif($format == "TZ") {
      print "Patch identified as compressed, textual RUP...\n";
      rup_text_patch($patch, $target, 1);
    }
    elseif($format == "B ") {
      print "Patch identified as binary RUP...\n";
      rup_bin_patch($patch, $target, 0);
    }
    elseif($format == "BZ") {
      print "Patch identified as compressed, binary RUP...\n";
      rup_bin_patch($patch, $target, 1);
    }
  }
  else
    print "ERROR: Patch format unknown\n";
}

else die(print "ERROR: The program just ate a frisbee\n");
exit;


function textualpatch($format, $source, $modified, $outfile, $compressed) {
  global $extra_info, $version;
  print "Creating validation data (this could be slow)...\n";
  $readfile = fopen($source, "rb");
  $filesize = filesize($source);
  if($filesize>0x1e00000) {
    print "File too large to test\nTaking a 30MB sample...\n";
    $srcfile = fread($readfile, 0x1400000);
    fseek($readfile, ($filesize - 0xa00000), SEEK_SET);
    $srcfile.= fread($readfile, 0xa00000) . $filesize;
  }
  else {
    if(strtolower($format) == "raw") {
      $fd = fopen($source, "rb");
      $srcfile = fread($fd, filesize($source));
      $fileoff = 0;
      fclose($fd);
    }
    elseif(strtolower($format) == "snes")
      list($srcfile, $fileoff) = snes_read($source);
    elseif(strtolower($format) == "mega")
      list($srcfile, $fileoff) = mega_read($source);
    elseif(strtolower($format) == "gb")
      list($srcfile, $fileoff) = gb_read($source);
    else {
      print "No matching format found\nDefaulting to RAW...\n";
      $format = "raw";
      $fd = fopen($source, "rb");
      $srcfile = fread($fd, filesize($source));
      $fileoff = 0;
      fclose($fd);
    }
  }
  fclose($readfile);
  sscanf(crc32($srcfile), "%u", $crc);
  $crc32 = str_pad(dechex($crc), 8, "0", STR_PAD_LEFT);
  unset($crc);
  $md5 = md5($srcfile);
  $sha1 = sha1($srcfile);
  if($filesize>0x1e00000)
    unset($srcfile);
  if(isset($srcfile)) {
    print "Reading in the modified file...\n";
    if(strtolower($format) == "raw") {
      $fd = fopen($modified, "rb");
      $modfile = fread($fd, filesize($modified));
      $modoff = 0;
      fclose($fd);
    }
    elseif(strtolower($format) == "snes") {
      print "Converting to SNES common...\n";
      list($modfile, $modoff) = snes_read($modified);
    }
    elseif(strtolower($format) == "mega") {
      print "Converting to Mega Drive common...\n";
      list($modfile, $modoff) = mega_read($modified);
    }
    elseif(strtolower($format) == "gb") {
      print "Converting to Game Boy common...\n";
      list($modfile, $modoff) = gb_read($modified);
    }
    else {
      print "No matching format found\nDefaulting to RAW...\n";
      $fd = fopen($modified, "rb");
      $modfile = fread($fd, filesize($modified));
      $modoff = 0;
      fclose($fd);
    }
    $pointer = 0;
    $running_patch = "";
    print "Creating the patch...";
    if(strlen($srcfile)<=strlen($modfile)) {
      while($pointer<strlen($srcfile)) {
        $src_chunk = substr($srcfile, $pointer, 0x200);
        $mod_chunk = substr($modfile, $pointer, 0x200);
        if($src_chunk != $mod_chunk) {
          if($srcfile[$pointer] != $modfile[$pointer]) {
            $offset = dechex($pointer);
            $patch = "";
            while($srcfile[$pointer] != $modfile[$pointer]) {
              $patch.= $modfile[$pointer];
              $pointer++;
            }
            $running_patch.= $offset . " " . bin2hex($patch) . "\n";
          }
          else
            $pointer++;
        }
        else
          $pointer = $pointer + 0x200;
      }
      if(strlen($modfile)>strlen($srcfile)) {
        $offset = dechex($pointer);
        $running_patch.= $offset . " " . bin2hex(substr($modfile, $pointer, (strlen($modfile)-$pointer))) . "\n";
      }
    }
    else {
      while($pointer<strlen($modfile)) {
        $src_chunk = substr($srcfile, $pointer, 0x200);
        $mod_chunk = substr($modfile, $pointer, 0x200);
        if($src_chunk != $mod_chunk) {
          if($srcfile[$pointer] != $modfile[$pointer]) {
            $offset = dechex($pointer);
            $patch = "";
            while($srcfile[$pointer] != $modfile[$pointer]) {
              $patch.= $modfile[$pointer];
              $pointer++;
            }
            $running_patch.= $offset . " " . bin2hex($patch) . "\n";
          }
          else
            $pointer++;
        }
        else
          $pointer = $pointer + 0x200;
      }
      if(strlen($modfile)<strlen($srcfile)) {
        $running_patch.= "cut " . dechex($pointer) . "\n";
      }
    }
    $running_patch = trim($running_patch);
    print " done!\nWriting patch to $outfile...\n";
    $fo = fopen($outfile, "wb");
    if($compressed == 1)
      fputs($fo, "NINJA1TZ" . gzcompress("#Generated by NINJA v$version\n#Textual patch format\n$format $crc32 $md5 $sha1\n$running_patch", 9));
    else
      fputs($fo, "NINJA1T\n#Generated by NINJA v$version\n#Textual patch format\n$format $crc32 $md5 $sha1\n$running_patch");
  }
  else {
    print "Opening $source and $modified for reading...\n";
    $source_stream = fopen($source, "rb");
    $modified_stream = fopen($modified, "rb");
    $pointer = 0;
    $running_patch = "";
    print "Creating the patch...";
    if(filesize($source)<=filesize($modified)) {
      while($pointer<filesize($source)) {
        $src_chunk = fread($source_stream, 0x200);
        $mod_chunk = fread($modified_stream, 0x200);
        if($src_chunk != $mod_chunk) {
          $chunkpoint = 0;
          while($chunkpoint < strlen($src_chunk)) {
            if($src_chunk[$chunkpoint] != $mod_chunk[$chunkpoint]) {
              $offset = dechex($pointer+$chunkpoint);
              $patch = "";
              while($src_chunk[$chunkpoint] != $mod_chunk[$chunkpoint]) {
                $patch.= $mod_chunk[$chunkpoint];
                $chunkpoint++;
              }
              $running_patch.= $offset . " " . bin2hex($patch) . "\n";
            }
            else
              $chunkpoint++;
          }
        }
        else
          $pointer = $pointer + 0x200;
      }
      if(filesize($modified)>filesize($source)) {
        $offset = dechex($pointer);
        $running_patch.= $offset . " " . bin2hex(fread($modified_stream, filesize($modified)-$pointer)) . "\n";
      }
    }
    else {
      while($pointer<filesize($modified)) {
        $src_chunk = fread($source_stream, 0x200);
        $mod_chunk = fread($modified_stream, 0x200);
        if($src_chunk != $mod_chunk) {
          $chunkpoint = 0;
          while($chunkpoint < strlen($src_chunk)) {
            if($src_chunk[$chunkpoint] != $mod_chunk[$chunkpoint]) {
              $offset = dechex($pointer+$chunkpoint);
              $patch = "";
              while($src_chunk[$chunkpoint] != $mod_chunk[$chunkpoint]) {
                $patch.= $mod_chunk[$chunkpoint];
                $chunkpoint++;
              }
              $running_patch.= $offset . " " . bin2hex($patch) . "\n";
            }
            else
              $chunkpoint++;
          }
        }
        else
          $pointer = $pointer + 0x200;
      }
      if(filesize($modified)<filesize($source)) {
        $running_patch.= "cut " . dechex($pointer) . "\n";
      }
    }
    fclose($source_stream);
    fclose($modified_stream);
    print " done!\nWriting patch to $outfile...\n";
    $fo = fopen($outfile, "wb");
    if($compressed == 1)
      fputs($fo, "NINJA1TZ" . gzcompress("#Generated by NINJA v$version\n#Textual patch format\n$format $crc32 $md5 $sha1\n$running_patch", 9));
    else
      fputs($fo, "NINJA1T\n#Generated by NINJA v$version\n#Textual patch format\n$format $crc32 $md5 $sha1\n$running_patch");
  }
  print "All done!\n";
  exit;
}


function binarypatch($format, $source, $modified, $outfile, $compressed) {
  global $extra_info;
  print "Creating validation data (this could be slow)...\n";
  $readfile = fopen($source, "rb");
  $filesize = filesize($source);
  if($filesize>0x1e00000) {
    print "File too large to test\nTaking a 30MB sample...\n";
    $srcfile = fread($readfile, 0x1400000);
    fseek($readfile, ($filesize - 0xa00000), SEEK_SET);
    $srcfile.= fread($readfile, 0xa00000) . $filesize;
  }
  else {
    if(strtolower($format) == "raw") {
      $fd = fopen($source, "rb");
      $fileoff = 0;
      $srcfile = fread($fd, filesize($source));
      fclose($fd);
    }
    elseif(strtolower($format) == "snes")
      list($srcfile, $fileoff) = snes_read($source);
    elseif(strtolower($format) == "mega")
      list($srcfile, $fileoff) = mega_read($source);
    elseif(strtolower($format) == "gb")
      list($srcfile, $fileoff) = gb_read($source);
    else {
      print "No matching format found\nDefaulting to RAW...\n";
      $format = "raw";
      $fd = fopen($source, "rb");
      $fileoff = 0;
      $srcfile = fread($fd, filesize($source));
      fclose($fd);
    }
  }
  fclose($readfile);
  sscanf(crc32($srcfile), "%u", $crc);
  $crc32 = pack("H*", str_pad(dechex($crc), 8, "0", STR_PAD_LEFT));
  unset($crc);
  $md5 = pack("H*", md5($srcfile));
  $sha1 = pack("H*", sha1($srcfile));
  $formbyte = chr(array_search($format, $extra_info));
  if($filesize>0x1e00000)
    unset($srcfile);
  if(isset($srcfile)) {
    if(strtolower($format) == "raw") {
      $fd = fopen($modified, "rb");
      $modoff = 0;
      $modfile = fread($fd, filesize($modified));
      fclose($fd);
    }
    elseif(strtolower($format) == "snes") {
      print "Converting to SNES common...\n";
      list($modfile, $modoff) = snes_read($modified);
    }
    elseif(strtolower($format) == "mega") {
      print "Converting to Mega Drive common...\n";
      list($modfile, $modoff) = mega_read($modified);
    }
    elseif(strtolower($format) == "gb") {
      print "Converting to Game Boy common...\n";
      list($modfile, $modoff) = gb_read($modified);
    }
    else {
      print "No matching format found\nDefaulting to RAW...\n";
      $fd = fopen($modified, "rb");
      $modoff = 0;
      $modfile = fread($fd, filesize($modified));
      fclose($fd);
    }
    $pointer = 0;
    $running_patch = "";
    print "Creating the patch...";
    if(strlen($srcfile)<=strlen($modfile)) {
      while($pointer<strlen($srcfile)) {
        $src_chunk = substr($srcfile, $pointer, 0x200);
        $mod_chunk = substr($modfile, $pointer, 0x200);
        if($src_chunk != $mod_chunk) {
          if($srcfile[$pointer] != $modfile[$pointer]) {
            $offset = dechex($pointer);
            if(strlen($offset) % 2 != 0)
              $offset = pack("H*", str_pad($offset, strlen($offset)+1, "0", STR_PAD_LEFT));
            else
             $offset = pack("H*", $offset);
            $offlen = chr(strlen($offset));
            $patch = "";
            while($srcfile[$pointer] != $modfile[$pointer]) {
              $patch.= $modfile[$pointer];
              $pointer++;
            }
            $length = dechex(strlen($patch));
            if(strlen($length) % 2 != 0)
              $length = pack("H*", str_pad($length, strlen($length)+1, "0", STR_PAD_LEFT));
            else
              $length = pack("H*", $length);
            $lenlen = chr(strlen($length));
            $running_patch.= $offlen . $offset . $lenlen . $length . $patch;
          }
          else
            $pointer++;
        }
        else
          $pointer = $pointer + 0x200;
      }
      if(strlen($modfile)>strlen($srcfile)) {
        $offset = dechex($pointer);
        if(strlen($offset) % 2 != 0)
          $offset = pack("H*", str_pad($offset, strlen($offset)+1, "0", STR_PAD_LEFT));
        else
          $offset = pack("H*", $offset);
        $offlen = chr(strlen($offset));
        $patch = substr($modfile, $pointer, (strlen($modfile)-$pointer));
        $length = dechex(strlen($patch));
        if(strlen($length) % 2 != 0)
          $length = pack("H*", str_pad($length, strlen($length)+1, "0", STR_PAD_LEFT));
        else
          $length = pack("H*", $length);
        $lenlen = chr(strlen($length));
        $running_patch.= $offlen . $offset . $lenlen . $length . $patch;
      }
    }
    else {
      while($pointer<strlen($modfile)) {
        $src_chunk = substr($srcfile, $pointer, 0x200);
        $mod_chunk = substr($modfile, $pointer, 0x200);
        if($src_chunk != $mod_chunk) {
          if($srcfile[$pointer] != $modfile[$pointer]) {
            $offset = dechex($pointer);
            if(strlen($offset) % 2 != 0)
              $offset = pack("H*", str_pad($offset, strlen($offset)+1, "0", STR_PAD_LEFT));
            else
             $offset = pack("H*", $offset);
            $offlen = chr(strlen($offset));
            $patch = "";
            while($srcfile[$pointer] != $modfile[$pointer]) {
              $patch.= $modfile[$pointer];
              $pointer++;
            }
            $length = dechex(strlen($patch));
            if(strlen($length) % 2 != 0)
              $length = pack("H*", str_pad($length, strlen($length)+1, "0", STR_PAD_LEFT));
            else
              $length = pack("H*", $length);
            $lenlen = chr(strlen($length));
            $running_patch.= $offlen . $offset . $lenlen . $length . $patch;
          }
          else
            $pointer++;
        }
        else
          $pointer = $pointer + 0x200;
      }
      if(strlen($modfile)<strlen($srcfile)) {
        $offset = dechex($pointer);
        if(strlen($offset) % 2 != 0)
          $offset = pack("H*", str_pad($offset, strlen($offset)+1, "0", STR_PAD_LEFT));
        else
          $offset = pack("H*", $offset);
        //$offlen = chr(strlen($offset));
        //$patch = substr($modfile, $pointer, (strlen($modfile)-$pointer));
        //$length = dechex(strlen($patch));
        //if(strlen($length) % 2 != 0)
        //  $length = pack("H*", str_pad($length, strlen($length)+1, "0", STR_PAD_LEFT));
        //else
        //  $length = pack("H*", $length);
        //$lenlen = chr(strlen($length));
        $running_patch.= chr(0) . chr(1) . $offset;
      }
    }
    
    print " done!\nWriting patch to $outfile...\n";
    $fo = fopen($outfile, "wb");
    if($compressed == 1)
      fputs($fo, "NINJA1BZ" . gzcompress($formbyte . $crc32 . $md5 . $sha1 . $running_patch . chr(3) . "EOF", 9));
    else
      fputs($fo, "NINJA1B " . $formbyte . $crc32 . $md5 . $sha1 . $running_patch . chr(3) . "EOF");
  }
  else {
    print "Opening $source and $modified for reading...\n";
    $source_stream = fopen($source, "rb");
    $modified_stream = fopen($modified, "rb");
    $pointer = 0;
    $running_patch = "";
    print "Creating the patch...";
    if(strlen($srcfile)<=strlen($modfile)) {
      while($pointer<filesize($source)) {
        $src_chunk = fread($source_stream, 0x200);
        $mod_chunk = fread($modified_stream, 0x200);
        if($src_chunk != $mod_chunk) {
      	  $chunkpoint = 0;
          while($chunkpoint < strlen($src_chunk)) {
            if($src_chunk[$chunkpoint] != $mod_chunk[$chunkpoint]) {
              $offset = dechex($pointer+$chunkpoint);
              if(strlen($offset) % 2 != 0)
                $offset = pack("H*", str_pad($offset, strlen($offset)+1, "0", STR_PAD_LEFT));
              else
                $offset = pack("H*", $offset);
              $offlen = chr(strlen($offset));
              $patch = "";
              while($src_chunk[$chunkpoint] != $mod_chunk[$chunkpoint]) {
                $patch.= $mod_chunk[$chunkpoint];
                $chunkpoint++;
              }
              $length = dechex(strlen($patch));
              if(strlen($length) % 2 != 0)
                $length = pack("H*", str_pad($length, strlen($length)+1, "0", STR_PAD_LEFT));
              else
                $length = pack("H*", $length);
              $lenlen = chr(strlen($length));
              $running_patch.= $offlen . $offset . $lenlen . $length . $patch;
            }
            else
              $chunkpoint++;
          }
        }
        else
          $pointer = $pointer + 0x200;
      }
      if(filesize($modified)>filesize($source)) {
        $offset = dechex($pointer);
        if(strlen($offset) % 2 != 0)
          $offset = pack("H*", str_pad($offset, strlen($offset)+1, "0", STR_PAD_LEFT));
        else
          $offset = pack("H*", $offset);
        $offlen = chr(strlen($offset));
        $patch = fread($modified_stream, filesize($modified)-$pointer);
        $length = dechex(strlen($patch));
        if(strlen($length) % 2 != 0)
          $length = pack("H*", str_pad($length, strlen($length)+1, "0", STR_PAD_LEFT));
        else
          $length = pack("H*", $length);
        $lenlen = chr(strlen($length));
        $running_patch.= $offlen . $offset . $lenlen . $length . $patch;
      }
    }
    else {
      while($pointer<filesize($modified)) {
        $src_chunk = fread($source_stream, 0x200);
        $mod_chunk = fread($modified_stream, 0x200);
        if($src_chunk != $mod_chunk) {
      	  $chunkpoint = 0;
          while($chunkpoint < strlen($src_chunk)) {
            if($src_chunk[$chunkpoint] != $mod_chunk[$chunkpoint]) {
              $offset = dechex($pointer+$chunkpoint);
              if(strlen($offset) % 2 != 0)
                $offset = pack("H*", str_pad($offset, strlen($offset)+1, "0", STR_PAD_LEFT));
              else
                $offset = pack("H*", $offset);
              $offlen = chr(strlen($offset));
              $patch = "";
              while($src_chunk[$chunkpoint] != $mod_chunk[$chunkpoint]) {
                $patch.= $mod_chunk[$chunkpoint];
                $chunkpoint++;
              }
              $length = dechex(strlen($patch));
              if(strlen($length) % 2 != 0)
                $length = pack("H*", str_pad($length, strlen($length)+1, "0", STR_PAD_LEFT));
              else
                $length = pack("H*", $length);
              $lenlen = chr(strlen($length));
              $running_patch.= $offlen . $offset . $lenlen . $length . $patch;
            }
            else
              $chunkpoint++;
          }
        }
        else
          $pointer = $pointer + 0x200;
      }
      if(filesize($modified)>filesize($source)) {
        $offset = dechex($pointer);
        if(strlen($offset) % 2 != 0)
          $offset = pack("H*", str_pad($offset, strlen($offset)+1, "0", STR_PAD_LEFT));
        else
          $offset = pack("H*", $offset);
        $offlen = chr(strlen($offset));
        $patch = fread($modified_stream, filesize($modified)-$pointer);
        $length = dechex(strlen($patch));
        if(strlen($length) % 2 != 0)
          $length = pack("H*", str_pad($length, strlen($length)+1, "0", STR_PAD_LEFT));
        else
          $length = pack("H*", $length);
        $lenlen = chr(strlen($length));
        $running_patch.= $offlen . $offset . $lenlen . $length . $patch;
      }
    }
    fclose($source_stream);
    fclose($modified_stream);
    print " done!\nWriting patch to $outfile...\n";
    $fo = fopen($outfile, "wb");
    if($compressed == 1)
      fputs($fo, "NINJA1BZ" . gzcompress($formbyte . $crc32 . $md5 . $sha1 . $running_patch . chr(3) . "EOF", 9));
    else
      fputs($fo, "NINJA1B " . $formbyte . $crc32 . $md5 . $sha1 . $running_patch . chr(3) . "EOF");
  }
  print "All done!\n";
  exit;
}


// Function for using a textual patch
function rup_text_patch($infile, $outfile, $compressed) {
  print "Reading patches into RAM...";
  $fd = fopen($infile, "rb");
  fseek($fd, 8, SEEK_SET);
  if($compressed == "0") {
    // Use preg_split(), trust me, it's about 4000x faster because of the size
    // of what we're splitting.
    $temp = preg_split("/\n/", trim(fread($fd, (filesize($infile)-8))));
    $patch_count = 0;
    $patch_lines = array();
    for($i=0; $i<count($temp); $i++)
      if($temp[$i][0] != "#") {
        $patch_lines[$patch_count] = trim($temp[$i]);
        $patch_count++;
      }
  }
  elseif($compressed == "1") {
    // Use preg_split(), trust me, it's about 4000x faster because of the size
    // of what we're splitting.
    $temp = preg_split("/\n/", trim(gzuncompress(fread($fd, (filesize($infile)-8)))));
    $patch_count = 0;
    for($i=0; $i<count($temp); $i++)
      if(substr($temp[$i], 0, 1) != "#") {
        $patch_lines[$patch_count] = trim($temp[$i]);
        $patch_count++;
      }
  }
  print " $patch_count patches found!\n";
  fclose($fd);
  print "Backing up $outfile...\n";
  rename($outfile, $outfile . ".bak");
  print "Verifying $outfile matches the settings in the patch...\n";
  list($format, $crc32, $md5, $sha1) = split(" ", $patch_lines[0]);
  if(($crc32 == "unk")&&($md5 == "unk")&&($sha1 == "unk"))
    print "This patch lacks any data verification information...\n";
  else {
    $readfile = fopen($outfile . ".bak", "rb");
    $filesize = filesize($outfile . ".bak");
    if($filesize>0x1e00000) {
      print "The file is too large to test, taking a 30MB sample...\n";
      $sample = fread($readfile, 0x1400000);
      fseek($readfile, ($filesize - 0xa00000), SEEK_SET);
      $sample.= fread($readfile, 0xa00000) . $filesize;
      $fileoff=0;
      fclose($readfile);
    }
    else {
      if(strtolower($format) == "raw") {
        $sample = fread($readfile, $filesize);
        $fileoff = 0;
        fclose($readfile);
      }
      elseif(strtolower($format) == "snes") {
        fclose($readfile);
        list($sample, $fileoff) = snes_read($outfile . ".bak");
      }
      elseif(strtolower($format) == "mega") {
        fclose($readfile);
        list($sample, $fileoff) = mega_read($outfile . ".bak");
      }
      elseif(strtolower($format) == "gb") {
        fclose($readfile);
        list($sample, $fileoff) = gb_read($outfile . ".bak");
      }
      else {
        die(print "ERROR: This file's definition is not supported\n");
      }
    }
    if($crc32 != "unk") {
      sscanf(crc32($sample), "%u", $crc);
      $testcrc = str_pad(dechex($crc), 8, "0", STR_PAD_LEFT);
      if($crc32 != $testcrc)
        die(print "ERROR: Supplied file is not suitable for this patch\n");
      unset($crc, $testcrc);
    }
    if($md5 != "unk")
      if($md5 != md5($sample))
        die(print "ERROR: Supplied file is not suitable for this patch\n");
    if($sha1 != "unk")
      if($sha1 != sha1($sample))
        die(print "ERROR: Supplied file is not suitable for this patch\n");
    print "Supplied file matches source\n";
    unset($crc32, $sha1, $md5);
  }
  $fd = fopen($outfile . ".bak", "rb");
  $fo = fopen($outfile, "x");
  $pointer = 0;
  if($filesize>0x1e00000)
    unset($sample);

  print "Applying patches...\n";
  $filesize = $filesize-$fileoff;
  if(isset($sample)) {
    for($i=1; $i<$patch_count; $i++) {
      list($offset, $patch) = preg_split("/ /", $patch_lines[$i]);
      if($offset != "cut") {
        $decoff = hexdec($offset);
        if($pointer<$decoff){
          fputs($fo, substr($sample, $pointer, $decoff-$pointer));
          $pointer = $decoff;
        }
        fputs($fo, pack("H*", $patch));
        $pointer = $pointer + (strlen($patch)/2);
      }
      else
        $cutpoint = hexdec($patch);
    }
    if(isset($cutpoint))
      fputs($fo, substr($sample, $pointer, $cutpoint-$pointer));
    elseif($pointer<strlen($sample))
      fputs($fo, substr($sample, $pointer, strlen($sample)-$pointer));
    fclose($fd);
    fclose($fo);
    print "Patching complete!\nYour original file has been moved to:\n> $outfile.bak\nDo you wish to delete it? (Y/N) ";
    $value = consoleread();
    while((strtolower($value) != "y")&&(strtolower($value) != "n")) {
      print "Invalid input! ";
      $value = consoleread();
    }
    if(strtolower($value) == "y")
      unlink($outfile . ".bak");
    print "All done!\n";
  }
  else {
    for($i=1; $i<$patch_count; $i++) {
      list($offset, $patch) = preg_split("/ /", $patch_lines[$i]);
      if($offset != "cut") {
        $decoff = hexdec($offset);
        if($pointer<$decoff){
          fseek($fd, $pointer);
          fputs($fo, fread($fd, $decoff-$pointer));
          $pointer=$decoff;
        }
        fputs($fo, pack("H*", $patch));
        $pointer = $pointer + (strlen($patch)/2);
      }
      else
        $cutpoint = hexdec($patch);
    }
    if(isset($cutpoint)) {
      fseek($fd, $pointer);
      fputs($fo, fread($fd, $cutpoint-$pointer));
    }
    elseif($pointer<filesize($outfile . ".bak")-$fileoff) {
      fseek($fd, $pointer);
      fputs($fo, fread($fd, filesize($outfile . ".bak")-$pointer));
    }
    fclose($fd);
    fclose($fo);
    print "Patching complete!\nYour original file has been moved to:\n> $outfile.bak\nDo you wish to delete it? (Y/N) ";
    $value = consoleread();
    while((strtolower($value) != "y")&&(strtolower($value) != "n")) {
      print "Invalid input! ";
      $value = consoleread();
    }
    if(strtolower($value) == "y")
      unlink($outfile . ".bak");
    print "All done!\n";
  }
  exit;
}


// Function for using a binary patch
function rup_bin_patch($infile, $outfile, $compressed) {
  global $extra_info;
  print "Opening $outfile for patching...\n";
  $fd = fopen($infile, "rb");
  fseek($fd, 8, SEEK_SET);
  if($compressed == "0") {
    $format = $extra_info[hexdec(bin2hex(fread($fd, 1)))];
    $crc32 = bin2hex(fread($fd, 4));
    $md5 = bin2hex(fread($fd, 16));
    $sha1 = bin2hex(fread($fd, 20));
    $patch = fread($fd, (filesize($infile)-49));
  }
  elseif($compressed == "1") {
    $patch_block = gzuncompress(fread($fd, (filesize($infile)-8)));
    $format = $extra_info[hexdec(bin2hex(substr($patch_block, 0, 1)))];
    $crc32 = bin2hex(substr($patch_block, 1, 4));
    $md5 = bin2hex(substr($patch_block, 5, 16));
    $sha1 = bin2hex(substr($patch_block, 21, 20));
    $patch = substr($patch_block, 41, (strlen($patch_block)-41));
    unset($patch_block);
  }
  fclose($fd);
  print "Backing up $outfile...\n";
  rename($outfile, $outfile . ".bak");
  print "Verifying $outfile matches the settings in the patch...\n";
  if(($crc32 == "unk")&&($md5 == "unk")&&($sha1 == "unk"))
    print "This patch lacks any data verification information...\n";
  else {
    $readfile = fopen($outfile . ".bak", "rb");
    $filesize = filesize($outfile . ".bak");
    if($filesize>0x1e00000) {
      print "File too large to test\nTaking a 30MB sample...\n";
      $sample = fread($readfile, 0x1400000);
      fseek($readfile, ($filesize - 0xa00000), SEEK_SET);
      $fileoff=0;
      $sample.= fread($readfile, 0xa00000) . $filesize;
      fclose($readfile);
    }
    else {
      if(strtolower($format) == "raw") {
        $sample = fread($readfile, $filesize);
        $fileoff = 0;
        fclose($readfile);
      }
      elseif(strtolower($format) == "snes") {
        fclose($readfile);
        list($sample, $fileoff) = snes_read($outfile . ".bak");
      }
      elseif(strtolower($format) == "mega") {
        fclose($readfile);
        list($sample, $fileoff) = mega_read($outfile . ".bak");
      }
      elseif(strtolower($format) == "gb") {
        fclose($readfile);
        list($sample, $fileoff) = gb_read($outfile . ".bak");
      }
      else {
        die(print "ERROR: This file's definition is not supported\n");
      }
    }
    if($crc32 != "00000000") {
      sscanf(crc32($sample), "%u", $crc);
      $testcrc = str_pad(dechex($crc), 8, "0", STR_PAD_LEFT);
      if($crc32 != $testcrc)
        die(print "ERROR: Supplied file is not suitable for this patch\n");
      unset($crc, $testcrc);
    }
    if($md5 != "00000000000000000000000000000000")
      if($md5 != md5($sample))
        die(print "ERROR: Supplied file is not suitable for this patch\n");
    if($sha1 != "0000000000000000000000000000000000000000")
      if($sha1 != sha1($sample))
        die(print "ERROR: Supplied file is not suitable for this patch\n");
    print "Supplied file matches source\n";
    unset($crc32, $sha1, $md5);
  }
  $fd = fopen($outfile . ".bak", "rb");
  $fo = fopen($outfile, "x");
  $pointer = 0;
  if($filesize>0x1e00000)
    unset($sample);
  if(isset($sample)) {
    $all_your_base = 0;
    $pointer = 0;
    $zig = 0;
    print "Applying patches...\n";
    $filesize = $filesize-$fileoff;
    while($all_your_base != 1) {
      $offlen = hexdec(bin2hex($patch[$zig]));
      $zig++;
      $temp = substr($patch, $zig, $offlen);
      if($temp != "EOF") {
        $offset = hexdec(bin2hex($temp));
        $zig = $zig+$offlen;
        if($pointer<$filesize) {
          if($pointer<$offset) {
            fputs($fo, substr($sample, $pointer, $offset-$pointer));
            $pointer = $offset;
          }
        }
        $lenlen = hexdec(bin2hex($patch[$zig]));
        $zig++;
        $length = hexdec(bin2hex(substr($patch, $zig, $lenlen)));
        $zig = $zig+$lenlen;
        fputs($fo, substr($patch, $zig, $length));
        $zig = $zig+$length;
        $pointer = $pointer+$length;
      }
      else
        $all_your_base = 1;
        // The victor is Cats
    }
    if($pointer<strlen($sample))
      fputs($fo, substr($sample, $pointer, strlen($sample)-$pointer));
    fclose($fd);
    print "Patching complete!\nYour original file has been moved to:\n> $outfile.bak\nDo you wish to delete it? (Y/N) ";
    $value = consoleread();
    while((strtolower($value) != "y")&&(strtolower($value) != "n")) {
      print "Invalid input! ";
      $value = consoleread();
    }
    if(strtolower($value) == "y")
      unlink($outfile . ".bak");
    print "All done!\n";
    fclose($fo);
  }
  else {
    $all_your_base = 0;
    $pointer = 0;
    $zig = 0;
    print "Applying patches...\n";
    while($all_your_base != 1) {
      $offlen = hexdec(bin2hex($patch[$zig]));
      $zig++;
      $temp = substr($patch, $zig, $offlen);
      if($temp != "EOF") {
        $offset = hexdec(bin2hex($temp));
        $zig = $zig+$offlen;
        if($pointer<$filesize) {
          if($pointer<$offset) {
            fseek($fd, $pointer, SEEK_SET);
            fputs($fo, fread($fd, $offset-$pointer));
            $pointer=$offset;
          }
        }
        $lenlen = hexdec(bin2hex($patch[$zig]));
        $zig++;
        $length = hexdec(bin2hex(substr($patch, $zig, $lenlen)));
        $zig = $zig+$lenlen;
        fputs($fo, substr($patch, $zig, $length));
        $zig = $zig+$length;
        $pointer = $pointer+$length;
      }
      else
        $all_your_base = 1;
        // The victor is Cats
    }
    if($pointer<$filesize) {
      fseek($fd, $pointer, SEEK_SET);
      fputs($fo, fread($fd, filesize($outfile . ".bak")-$pointer));
      $pointer++;
    }
    fclose($fd);
    print "Patching complete!\nYour original file has been moved to:\n> $outfile.bak\nDo you wish to delete it? (Y/N) ";
    $value = consoleread();
    while((strtolower($value) != "y")&&(strtolower($value) != "n")) {
      print "Invalid input! ";
      $value = consoleread();
    }
    if(strtolower($value) == "y")
      unlink($outfile . ".bak");
    print "All done!\n";
    fclose($fo);
  }
  exit;
}


// Function for using an IPS patch
function ips_patch($infile, $outfile) {
  $patch = fopen($infile, "rb");
  fseek($patch, 5, SEEK_SET);
  print "Opening $outfile for patching...\n";
  $fd = fopen($outfile, "rb");
  $fddump = fread($fd, filesize($outfile));
  print "Beginning patching...\n";
  $all_your_base = 0;
  $pointer = 0;
  $filesize = filesize($outfile);
  while($all_your_base != 1) {
    $temp = fread($patch, 3);
    if($temp != "EOF") {
      $offset = hexdec(bin2hex($temp));
      $tempfile = substr($fddump, 0, $offset);
      $length = hexdec(bin2hex(fread($patch, 2)));
      if($length==0) {
        //Oh fuck, it's RLE time...
        $rle_length = hexdec(bin2hex(fread($patch, 2)));
        $rle_char = fgetc($patch);
        $rle_expand = "";
        for($rle_count=0; $rle_count<$rle_length; $rle_count++) {
          $rle_expand.= $rle_char;
        }
        $tempfile.= $rle_expand;
        unset($rle_char, $rle_expand, $rle_length, $rle_count);
      }
      else {
        $tempfile.= fread($patch, $length);
      }
      $tempfile.= substr($fddump, strlen($tempfile));
      $fddump = $tempfile;
    }
    else
      $all_your_base = 1;
      // The victor is Cats
  }
  unset($tempfile);
  //Add support for LunarIPS cut
  if(ftell($patch)<filesize($infile)) {
    $offset = hexdec(bin2hex(fread($patch, 3)));
    print "Cutting at 0x" . dechex($offset) . "...\n";
    $fddump = substr($fddump, 0, $offset);
  }
  fclose($fd);
  fclose($patch);
  print "Patching complete!\nWould you like to keep a backup of the original file? (Y/N) ";
  $value = consoleread();
  while((strtolower($value) != "y")&&(strtolower($value) != "n")) {
    print "Invalid input! ";
    $value = consoleread();
  }
  if(strtolower($value) == "y")
    rename($outfile, $outfile . ".bak");
  $fo = fopen($outfile, "wb");
  fputs($fo, $fddump);
  print "All done!\n";
  fclose($fo);
  exit;
}


// Read in a Megadrive ROM
function mega_read($infile) {
  $fd = fopen($infile, "rb");
  fseek($fd, 0x100, SEEK_SET);
  if(fread($fd, 4) == "SEGA") {
    print "File appears to be in BIN format already...\n";
    fseek($fd, 0, SEEK_SET);
    return(array(fread($fd, filesize($infile)), 0));
  }
  fseek($fd, 0x8, SEEK_SET);
  if(bin2hex(fread($fd, 2)) == "AABB") {
    print "File appears to be in SMD format...\nConverting to BIN... ";
    fseek($fd, 0x200, SEEK_SET);
    $num_blocks = (filesize($infile)-0x200)/(0x4000);
    $output = "";
    for($i=0; $i<$num_blocks; $i++) {
      $output.= smd_deinterleave(fread($fd, 0x4000));
    }
    return(array($output, 0x200));
  }
  else
    die(print "ERROR: Invalid Genesis ROM!\n");
}


// Read in a Game Boy ROM
function gb_read($infile) {
  $fd = fopen($infile, "rb");
  // Test for an 0x200 SmartCard header
  if((filesize($infile) % 0x4000) != 0) {
    fseek($fd, 0x200, SEEK_SET);
    return(array(fread($fd, (filesize($infile)-0x200)), 0x200));
  }
  else
    return(array(fread($fd, (filesize($infile))), 0));
}


// Read in a SNES ROM
function snes_read($infile) {
  // ROM type codes used:
  // SLOT I:
  //   0 - No header
  //   1 - 0x200 byte header
  // SLOT II:
  //   0 - Plain HiROM
  //   1 - Plain LoROM
  //   2 - Interleaved HiROM
  //   3 - Interleaved LoROM
  //   4 - Extended HiROM
  $fd = fopen($infile, "rb");
  fseek($fd, 0x7fdc, SEEK_SET);
  $inverse = hexdec(bin2hex(fread($fd, 2)));
  $checksum = hexdec(bin2hex(fread($fd, 2)));
  if(strtolower(dechex($inverse + $checksum)) == "ffff") {
    fseek($fd, 0x7fd5, SEEK_SET);
    list($upper, $lower) = hilo_nibble(fread($fd, 1));
    if($lower % 2 != 0)
      $romtype = array(0, 2);
    else
      $romtype = array(0, 1);
  }
  if(!isset($romtype)) {
    fseek($fd, 0x81dc, SEEK_SET);
    $inverse = hexdec(bin2hex(fread($fd, 2)));
    $checksum = hexdec(bin2hex(fread($fd, 2)));  
    if(strtolower(dechex($inverse + $checksum)) == "ffff") {
      fseek($fd, 0x81d5, SEEK_SET);
      list($upper, $lower) = hilo_nibble(fread($fd, 1));
      if($lower % 2 != 0)
        $romtype = array(1, 2);
      else
        $romtype = array(1, 1);
    }
  }
  if(!isset($romtype)) {
    fseek($fd, 0xffdc, SEEK_SET);
    $inverse = hexdec(bin2hex(fread($fd, 2)));
    $checksum = hexdec(bin2hex(fread($fd, 2)));  
    if(strtolower(dechex($inverse + $checksum)) == "ffff") {
      fseek($fd, 0xffd5, SEEK_SET);
      list($upper, $lower) = hilo_nibble(fread($fd, 1));
      if($lower % 2 != 0)
        $romtype = array(0, 1);
      else
        $romtype = array(0, 0);
    }
  }
  if(!isset($romtype)) {
    fseek($fd, 0x101dc, SEEK_SET);
    $inverse = hexdec(bin2hex(fread($fd, 2)));
    $checksum = hexdec(bin2hex(fread($fd, 2)));  
    if(strtolower(dechex($inverse + $checksum)) == "ffff") {
      fseek($fd, 0x101d5, SEEK_SET);
      list($upper, $lower) = hilo_nibble(fread($fd, 1));
      if($lower % 2 != 0)
        $romtype = array(1, 1);
      else
        $romtype = array(1, 0);
    }
  }
  if((filesize($infile)>0x400000)&&(!isset($romtype))) {
    fseek($fd, 0x40ffd5, SEEK_SET);
    $inverse = hexdec(bin2hex(fread($fd, 2)));
    $checksum = hexdec(bin2hex(fread($fd, 2)));  
    if(strtolower(dechex($inverse + $checksum)) == "ffff")
      $romtype = array(0, 4);
    fseek($fd, 0x4101d5, SEEK_SET);
    $inverse = hexdec(bin2hex(fread($fd, 2)));
    $checksum = hexdec(bin2hex(fread($fd, 2)));  
    if(strtolower(dechex($inverse + $checksum)) == "ffff")
      $romtype = array(0, 4);
  }
  if(($romtype[0] == 0)&&($romtype[1] < 2)) {
    fseek($fd, 0, SEEK_SET);
    return(array(fread($fd, filesize($infile)), 0));
  }
  elseif(($romtype[0] == 1)&&($romtype[1] < 2)) {
    fseek($fd, 0x200, SEEK_SET);
    return(array(fread($fd, (filesize($infile)-0x200)), 0x200));
  }
  elseif(($romtype[0] == 0)&&($romtype[1] == 4)) {
    fseek($fd, 0, SEEK_SET);
    return(array(fread($fd, filesize($infile)), 0));
  }
  elseif(($romtype[0] == 1)&&($romtype[1] == 4)) {
    fseek($fd, 0x200, SEEK_SET);
    return(array(fread($fd, (filesize($infile)-0x200)), 0x200));
  }
  // It's interleaving time!
  // Only HiROM files interleave (unless they were broke by some dickass first,
  // in which case we don't care about them). However, HiROM games under
  // 0x200000 bytes do not appear to interleave.
  // 0x200000 - 0x400000 can be interleaved by Game Doctor III/Professor SF II
  // copiers.
  // Since 0x280000 and 0x300000 size interleaved roms have very odd patterns,
  // we just store the pattern and form the chunks around it. It's lazy but it
  // works.
  elseif(($romtype[0] == 0)&&(($romtype[1] > 1)&&($romtype[1] < 4))) {
    $chart_0x280000 = array(1, 3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 23, 25, 27, 29, 31, 33, 35, 37, 39, 41, 43, 45, 47, 49, 51, 53, 55, 57, 59, 61, 63, 65, 67, 69, 71, 73, 75, 77, 79, 64, 66, 68, 70, 72, 74, 76, 78, 32, 34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60, 62, 0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30);
    $chart_0x300000 = array(1, 3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 23, 25, 27, 29, 31, 33, 35, 37, 39, 41, 43, 45, 47, 49, 51, 53, 55, 57, 59, 61, 63, 65, 67, 69, 71, 73, 75, 77, 79, 81, 83, 85, 87, 89, 91, 93, 95, 64, 66, 68, 70, 72, 74, 76, 78, 80, 82, 84, 86, 88, 90, 92, 94, 0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60, 62);
    fseek($fd, 0, SEEK_SET);
    if(filesize($infile) == 0x280000) {
      $chunks = (filesize($infile) / 0x8000);
      $chunk_array = array("", "", "");
      for($i=0; $i<$chunks; $i++)
        $chunk_array[$i] = fread($fd, 0x8000);
      $deinterleave = "";
      for($i=0; $i<count($chart_0x280000); $i++)
        $deinterleave.= $chunk_array[array_search($i, $chart_0x280000)];
      return(array($deinterleave, 0));
    }
    elseif(filesize($infile) == 0x300000) {
      $chunks = (filesize($infile) / 0x8000);
      $chunk_array = array("", "", "");
      for($i=0; $i<$chunks; $i++)
        $chunk_array[$i] = fread($fd, 0x8000);
      $deinterleave = "";
      for($i=0; $i<count($chart_0x300000); $i++)
        $deinterleave.= $chunk_array[array_search($i, $chart_0x300000)];
      return(array($deinterleave, 0));
    }
    else {
      $chunks = (filesize($infile) / 0x8000);
      $odds = "";
      $evens = "";
      for($i=0; $i<($chunks/2); $i++) {
        $evens .= fread($fd, 0x8000);
        $odds .= fread($fd, 0x8000);
      }
      return(array($odds . $evens, 0));
    }
  }
  elseif(($romtype[0] == 1)&&(($romtype[1] > 1)&&($romtype[1] < 4))) {
    $chart_0x280000 = array(1, 3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 23, 25, 27, 29, 31, 33, 35, 37, 39, 41, 43, 45, 47, 49, 51, 53, 55, 57, 59, 61, 63, 65, 67, 69, 71, 73, 75, 77, 79, 64, 66, 68, 70, 72, 74, 76, 78, 32, 34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60, 62, 0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30);
    $chart_0x300000 = array(1, 3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 23, 25, 27, 29, 31, 33, 35, 37, 39, 41, 43, 45, 47, 49, 51, 53, 55, 57, 59, 61, 63, 65, 67, 69, 71, 73, 75, 77, 79, 81, 83, 85, 87, 89, 91, 93, 95, 64, 66, 68, 70, 72, 74, 76, 78, 80, 82, 84, 86, 88, 90, 92, 94, 0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60, 62);
    fseek($fd, 0x200, SEEK_SET);
    if(filesize($infile)-0x200 == 0x280000) {
      $chunks = ((filesize($infile)-0x200) / 0x8000);
      $chunk_array = array("", "", "");
      for($i=0; $i<$chunks; $i++)
        $chunk_array[$i] = fread($fd, 0x8000);
      $deinterleave = "";
      for($i=0; $i<count($chart_0x280000); $i++)
        $deinterleave.= $chunk_array[array_search($i, $chart_0x280000)];
      return(array($deinterleave, 0x200));
    }
    elseif(filesize($infile)-0x200 == 0x300000) {
      $chunks = ((filesize($infile)-0x200) / 0x8000);
      $chunk_array = array("", "", "");
      for($i=0; $i<$chunks; $i++)
        $chunk_array[$i] = fread($fd, 0x8000);
      $deinterleave = "";
      for($i=0; $i<count($chart_0x300000); $i++)
        $deinterleave.= $chunk_array[array_search($i, $chart_0x300000)];
      return(array($deinterleave, 0x200));
    }
    else {
      $chunks = ((filesize($infile)-0x200) / 0x8000);
      $odds = "";
      $evens = "";
      for($i=0; $i<($chunks/2); $i++) {
        $evens .= fread($fd, 0x8000);
        $odds .= fread($fd, 0x8000);
      }
      return(array($odds . $evens, 0x200));
    }
  }
  else
    return(array(fread($fd, (filesize($infile))), 0));
}


// Deinterleaves a 16KB block of SMD data   
function smd_deinterleave($chunk) {
  $low   = 1;
  $high  = 0;
  $block = "";
  for($i=0; $i<0x2000; $i++) {
    $block[$low] = $chunk[$i];
    $block[$high] = $chunk[(0x2000+$i)];
    $low  = $o+2;
    $high = $e+2;
  }
  return($block);
}


// Split a byte to high and low nibbles
function hilo_nibble($byte) {
  $bytestr = bin2hex($byte);
  return(array(hexdec(substr($bytestr, 0, 1)), hexdec(substr($bytestr, 1, 1))));
}


// Show options if input is missing
function DisplayOptions() {
  echo <<<OPTIONS
Welcome to NINJA, an open-source, multi-platform patching system designed for
hacks and translations. NINJA is able to create format independant patches that
get around the age-old problem of headers and alternate dumps. It also supports
strong data verification with CRC32, MD5 and SHA1 hashes.

NINJA uses its own RUP patch format, which can be text or compressed, as well
as the common IPS.

Apply a patch:
  ninja.php -p patch target_file

Create a binary patch (use -bz for a compressed patch):
  ninja.php -b format source_file modified_file output_patch

Create a textual patch (use -tz for a compressed patch):
  ninja.php -t format source_file modified_file output_patch

Please see readme.txt for a complete list of supported game formats.

OPTIONS;
}


// Gets time since the epoch for speed clocking
function getmicrotime(){ 
  list($usec, $sec) = explode(" ",microtime()); 
  return ((float)$usec + (float)$sec); 
}


// Reads user input from the text console
function consoleread($length='10') {
  if (!isset ($GLOBALS['StdinPointer']))
    $GLOBALS['StdinPointer'] = fopen("php://stdin", "r");
  $line = fgets($GLOBALS['StdinPointer'],$length);
  return trim($line);
}

?>