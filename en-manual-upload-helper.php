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
 * 
 * More details:
 * 1. In Silverpop: Create a query containing only the people who've participated in, say, 
 * TSW (e.g. "WHERE 'TSW' is not NULL").
 * 2. In Silverpop: Export the query, with only the field "email".
 * 3. In this script: CSV export file from Silverpop is split every 20,000 
 * lines, with ",Y,P" added to the end of each line too.
 * 4. In Engaging Networks: A campaign is created - in the case of TSW for the Brazil account 
 * it will be called "pt.forests.tigers.signup.manifesto".
 * 5. In Engaging Networks: The CSV files created in step 3 are imported using the 
 * Supporter data > Import Supporters wizard. The first time it is done we create a saved format 
 * like "Tiger Shop Window Manifesto (Silverpop)", then we use that format to speed up the other 
 * file imports too. Each time we're just mapping email=email, Y=email_ok 
 * and P=pt.forests.tigers.signup.manifesto.
 * 
 * 
 * 
 */


// get filename from the command line
$input = $_SERVER['argv'][1];

$in = file($input);
$header=$in[0]; // header row
$counter = 1; 

// split into files called upload_part_#.csv every 20,000 lines
while ($chunk = array_splice($in, 0, 20000)){
      $f = fopen("upload_part_".($counter++).".csv", "a");
	  fwrite($f, $header);
      fwrite($f, implode("", $chunk));
      fclose($f);
}


// add the Y and P columns
while ($counter > 0) {

	$output = "upload_part_".($counter--).".csv";
	$data = file($output,FILE_IGNORE_NEW_LINES);

	$fp = fopen($output,"w");

	foreach((array)$data as $val) {

	   fwrite($fp,$val.",Y,P\r\n");   
	}
	fclose($fp);
}

// remove duplicate first (header) row from first file
$contents = file($output, FILE_IGNORE_NEW_LINES);
$first_line = array_shift($contents);
file_put_contents($output, implode("\r\n", $contents));

?>
