<?php

/**
 * SPLIT FILE EVERY 20k ROWS AND ADD TWO COLUMNS
 * Split every 20,000 rows, beginning with column headers.
 * Now add N, Y and a P column to the end for Engaging Networks to
 * know that they are email_ok=Y and they "P"articipated 
 * in the campaign.
 *
 * Use from the command line like this:
 * php en-manual-upload-helper.php example.csv
 *
 * Author: Eoin Dubsky <edubsky@greenpeace.org>
 */


// get filename from the command line
$input = $_SERVER['argv'][1];

$in = file($input);
$header=$in[0]; // header row
$counter = 1; 

// split into files called upload_part_#.csv every 60,000 lines
while ($chunk = array_splice($in, 0, 60000)){
      $f = @fopen("upload_part_".($counter++).".csv", "a");
	  fwrite($f, $header);
      fwrite($f, implode("", $chunk));
      fclose($f);
}

// go back one, so we don't make too many files
$counter=$counter-1; 

// add the Y and P columns
while ($counter > 0) {

	$output = "upload_part_".($counter--).".csv";
	$data = file($output,FILE_IGNORE_NEW_LINES);

	$fp = fopen($output,"w");

	foreach((array)$data as $val) {
	   // first remove stray "double quotes"
	   $val = preg_replace('/[^,]""[^,]/','',$val);
	   // replace any field missing a double-quote with ""
	   //$val = preg_replace('([^"]+,")','""',$val); // sorry it was too greedy!
	   // then add our Y and P columns 
	   fwrite($fp,$val.",Y,P\r\n");   
	}
	fclose($fp);
}

// remove duplicate first (header) row from first file
$contents = file($output, FILE_IGNORE_NEW_LINES);
$first_line = array_shift($contents);
file_put_contents($output, implode("\r\n", $contents));

?>
