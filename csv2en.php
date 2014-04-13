<?php
 
/**
 * SPLIT FILE EVERY 60k ROWS AND ADD TWO COLUMNS
 * Split every 60,000 rows, beginning with column headers.
 * Now add N, Y and a P column to the end for Engaging Networks to
 * know that they are email_ok=Y and they "P"articipated 
 * in the campaign. The N is to optionally opt-out users from something.
 *
 * Use from the command line like this:
 * php csv2en.php example.csv
 *
 * Author: Eoin Dubsky <edubsky@greenpeace.org>
 */
 
 
// get filename from the command line
$input = $_SERVER["argv"][1];
 
// split file every $split lines
$split = "60000"; 
 
// Open the temporary output csv file
$outputfp = fopen("output.tmp", "w");
 
// Open the irregular.csv output csv file for lines causing errors
$errorsfp = fopen("errors.csv", "w");
 
// Read from the input csv file
if (($inputfp = fopen($input, "r")) !== FALSE) {
	
	// get number of columns as array
	$f = fopen($input, "r");
	$cols = fgetcsv($f);
	$cols_count = count($cols);
	fclose($f);
	
	$row = "1";
	while (($data = fgetcsv($inputfp, 1000, ",")) !== FALSE) {
		
		// count fields
		$fields = count($data);

		// number of fields in this row and header row match
		if ($fields == $cols_count) {
		
			// Add N, Y and P columns
//			$data[] = "N"; // optionally mapped to email_ok instead of Y if opting-out users			
			$data[] = "Y"; // normally mapped to email_ok
			$data[] = "P"; // optionally mapped to a campaign
			
			// Write the line to the file
			fputcsv($outputfp, $data);
			
		} else {
			
			echo "Line $row had an irregular column count, saving to errors.csv for you to clean up.\n";
			// Write the bad line to the error file
			fputcsv($errorsfp, $data);
			
		}

		$row++;

	}

	// clean up
	fclose($inputfp);
	fclose($outputfp);
	fclose($errorsfp);
}

// now split
$in = file("output.tmp");
$counter = "1";   
while ($chunk = array_splice($in, 0, $split)){
      $f = fopen("part".($counter++).".csv", "w");
      fputs($f, implode("", $chunk));
      fclose($f);
}
 
// remove tmp file
unlink("output.tmp");
?>
