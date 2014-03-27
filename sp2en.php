<?php

/**
 * sp2en.php - Copy data from Silverpop to Engaging Networks
 *
 * Use this script with cron to regularly download new signups from Silverpop
 * and upload them into Engaging Networks. Saves you doing it by hand, which
 * is annoying and can often lead to file corruption (yes Excel you!!). 
 *
 *
 * @category   Databases
 * @package    sp2en
 * @author     Eoin Dubsky <edubsky@greenpeace.org>
 * @license    https://www.gnu.org/licenses/gpl.html GPL v3
 * @version    0.1
 */

/**
 * LOGIN DETAILS 
 * Include from config.php
 */


include("config.php");
include("SilverpopAPI.php");
set_include_path(get_include_path() . PATH_SEPARATOR . 'phpseclib');
include('Net/SFTP.php');

/**
 * DATABASES TO SYNC
 * 
 */

$databases = array(
	// Silverpop ID => Engaging Networks import format name
	"12141314" => "Tiger Manifesto Signups (Silverpop)",
	"9131026" => "SaveTheArctic Signups (Silverpop)",
	"9858509" => "GPI Main List Signups (Silverpop)"
);

foreach ($databases as $sp_database_id => $en_format_name) {

	/**
	 * DATE/TIME
	 * Get the date/time of the last download, so we only download
	 * records from then up to now. Then update the file.
	 */


	date_default_timezone_set("Europe/Paris");

	$last_downloaded_timestamp_file = $sp_database_id."_timestamp.txt";
	$silverpop_response_file = $sp_database_id."_response.xml";
	$upload_file = $sp_database_id."_upload.csv";
	
	// Read the timestamp from the file
	$last_download_timestamp = file_get_contents($last_downloaded_timestamp_file);

	// Write the timestamp of right now
	file_put_contents($last_downloaded_timestamp_file, time());

	// Format date/time for Silverpop API
	$now = date("m/d/Y H:i:s"); 
	$back_then = date("m/d/Y H:i:s", $last_download_timestamp);



	/**
	 * SILVERPOP API REQUEST
	 * Requesting the CSV file to be made, so it can be downloaded from Silverpop
	 */

	define("SILVERPOP_ENDPOINT", "https://api4.silverpop.com/XMLAPI");
	define("SILVERPOP_USERNAME", $sp_username);
	define("SILVERPOP_PASSWORD", $sp_password);

	try {
	  $silverpop_api = new SilverpopAPI(
	    SILVERPOP_ENDPOINT,
	    SILVERPOP_USERNAME,
	    SILVERPOP_PASSWORD
	  );

		$xml="<LIST_ID>".$sp_database_id."</LIST_ID> 
			<EXPORT_TYPE>OPT_IN</EXPORT_TYPE>
			<EXPORT_FORMAT>CSV</EXPORT_FORMAT>
			<DATE_START>".$back_then."</DATE_START>
			<DATE_END>".$now."</DATE_END>
			<INCLUDE_LEAD_SOURCE>TRUE</INCLUDE_LEAD_SOURCE>";

		$silverpop_api->build("ExportList", $xml);
		$response = $silverpop_api->execute();
		file_put_contents($silverpop_response_file, $response);
   
	}
	catch (SilverpopConnectionException $e) {
	  // Handle connection exceptions.
	  print $e;
	}
	catch (SilverpopDataException $e) {
	  // Handle malformed XML exceptions.
	  print $e;
	}
	
	// Give Silverpop two minutes to create the file
	sleep(120);


	/**
	 * DOWNLOAD THE CSV FILE
	 * Download the CSV file with SFTP from Silverpop's server
	 */

	// now lets read what Silverpop said
	$xml = simplexml_load_file($silverpop_response_file);

	$success = $xml->Body->RESULT->SUCCESS; // should be "TRUE"

	If ($success != "TRUE") {
		echo "I'm exiting - it didn't download\n\n";
		exit;
	}

	$file_path = $xml->Body->RESULT->FILE_PATH; 
	$file_name = substr($file_path, 10); // removing the /download/ bit

	$sftp = new Net_SFTP("transfer4.silverpop.com");
	if (!$sftp->login($sp_username, $sp_password)) {
	    exit('Login Failed');
	}

	$sftp->chdir('download');
	$sftp->get($file_name, $file_name);



	/**
	 * SIMPLY ADD TWO COLUMNS
	 * You could validate phone numbers and do other cool things here too.
	 * Now add N, Y and a P column to the end for Engaging Networks to
	 * know that they are email_ok=Y and they "P"articipated 
	 * in the campaign.
	 */
	$data = file($file_name,FILE_IGNORE_NEW_LINES);

	$fp = fopen($upload_file,"w");

	foreach((array)$data as $val) {
	
	   fwrite($fp,$val.",Y,P\r\n");   
	}
	fclose($fp);




	
	/**
	 * POST THE DATA TO ENGAGING NETWORKS
	 * Post the data using cURL
	 */
	
	// URL on which we have to post data
	$url = "https://e-activist.com/ea-dataservice/import.service";
	// Any other field you might want to catch
	$post_data['token'] = $en_token;
	$post_data['name'] = $en_format_name.$today;
	// File you want to upload/post
	$post_data['upload'] = "@$upload_file";
	$post_data['formatName'] = $en_format_name;
 
	// Initialize cURL
	$ch = curl_init();
	// Set URL on which you want to post the Form and/or data
	curl_setopt($ch, CURLOPT_URL, $url);
	// Data+Files to be posted
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	// Pass TRUE or 1 if you want to wait for and catch the response against the request made
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	// For Debug mode; shows up any error encountered during the operation
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	// Execute the request
	$response = curl_exec($ch);
 
	// get the Engaging Networks Job Number from the response
	if (filter_var($response, FILTER_SANITIZE_NUMBER_INT)) {
		$en_job_number = filter_var($response, FILTER_SANITIZE_NUMBER_INT);
	} else {
		$en_job_number = "ERROR: ".$response;
	}
	



	/**
	 * Write to the log file
	 * Keep a log for debugging
	 */

	$log_entry = $back_then.",".$now.",".$file_name.",".$en_format_name.",".$en_job_number."\n";

	file_put_contents("log.csv", $log_entry, FILE_APPEND);



	/**
	 * DELETE LOCAL CSV FILES
	 * Delete the CSV file downloaded from Silverpop and the copy made for uploading.
	 */

	unlink($silverpop_response_file);
	unlink($file_name);
	unlink($upload_file);
}
?>
