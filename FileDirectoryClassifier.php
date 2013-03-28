<?php

print "Setting up Drupal environment.\n";
define('DRUPAL_ROOT', '/vectorbase/web/root');
$base_path = DRUPAL_ROOT;
define('DL_COUNT_TAG', 'download count');
if(!isset($_SERVER['REMOTE_ADDR'])) {
	$_SERVER['REMOTE_ADDR'] = 'db.vectorbase.org';
} else {
	print 'Remote address: ' . $_SERVER['REMOTE_ADDR'] . "\n";
}
require_once(DRUPAL_ROOT . '/includes/bootstrap.inc');
require_once('Tools.php');
$phase = drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
print "Setup complete.\n";

$time = time();
$logPath = "./$time.log";
$outPath = "./$time.csv";
$log = fopen($logPath, 'w');
$out = fopen($outPath, 'w');
print "Logging errors to $logPath.\n";
print "Writing output to $outPath.\n";

CONST FILE_PUB_PATH = 'file_public_path';
CONST NEW_FILE_PUB_PATH = '/vectorbase/web/root/sites/default/files/ftp';
CONST FILENAME = 'filename';
CONST MOVED = 'moved';
CONST DESTINATION = 'destination'; 
CONST ORIGIN = 'origin';
$columns = array(FILENAME, MOVED, DESTINATION, ORIGIN);

$query = new EntityFieldQuery();
$query->entityCondition('entity_type', 'node');
$query->propertyCondition('type', 'downloadable_file');
$result = $query->execute();

if(isset($result['node'])) {
	$originalPubPath = variable_get(FILE_PUB_PATH, 'sites/default/files/ftp');
	variable_set(FILE_PUB_PATH, NEW_FILE_PUB_PATH);
	better_fputcsv($out, $columns, ',', '"', '"'); // Create headers for output.
	$details = null;
	foreach(array_keys($result['node']) as $nodeId) {
		if(!empty($details)) {
			better_fputcsv($out, $details, ',', '"', '"');
		}
		$details = array();
		$details[FILENAME] = $nodeId;
		$details[MOVED] = 'false';
		$details[DESTINATION] = '';
		$details[ORIGIN] = '';

		$fileNode = node_load($nodeId);
		if($fileNode == false) {
			fwrite($log, "\nSkipping file node $nodeId because it could not be loaded.");
			continue;
		}
		$fileObj = file_load($fileNode->field_file['und'][0]['fid']);
		$details[FILENAME] = $fileObj->filename;	
		$details[ORIGIN] = dirname($fileObj->uri);
		
		$finalPath = 'downloads/managed';
		if(!empty($fileNode->field_organism_taxonomy)) {
			$orgTaxon = taxonomy_term_load($fileNode->field_organism_taxonomy['und'][0]['tid']);
			$orgName = str_replace(' ', '_', $orgTaxon->name); // get rid of spaces
			$orgName = strtolower($orgName); // make everything lowercase
			$finalPath .= "/$orgName";
		} else {
			
			$finalPath .= '/other';
			/*if(!is_dir($finalPath)) {
				if(!mkdir($finalPath)) {
					fwrite($log, "\nSkipping node $nodeId because we could not create directory \"other\" in $finalPath.");
					continue;
				}
			}*/
		}
		
		if(!empty($fileNode->field_download_file_type)) {
		
			$parents = taxonomy_get_parents_all($fileNode->field_download_file_type['und'][0]['tid']);
			if(empty($parents)) {
				fwrite($log, "\nSkipping node $nodeId because we could not find the root parent of this node's file type.");
				continue;
			}	
			$root = $parents[count($parents) - 1];
			$root = strtolower($root->field_shortname['und'][0]['value']);
			$finalPath .= "/$root";
			/*if(!is_dir($finalPath)) {
				if(!mkdir($finalPath)) {
					fwrite($log, "\nSkipping node $nodeId because we could not create directory \"$root\" in $finalPath.");
					continue;
				}
			}*/
	
				
		} else {
			fwrite($log, "\nSkipping node $nodeId because it does not have a file type (category).");
			continue;

		}
		$details[DESTINATION] = "public://$finalPath";
		/*if(!is_dir($finalPath)) {
			if(!mkdir($finalPath)) {
				fwrite($log, "\nSkipping node $nodeId because we could not create directory \"$root\" in $finalPath.");
				continue;
			}
		}*/
		if(!empty($details[DESTINATION]) && $details[ORIGIN] !== $details[DESTINATION]) { 
			//$orgName = str_replace(' ', '_', $orgTaxon->name); // get rid of spaces
	//$originalPubPath = variable_get(FILE_PUB_PATH, 'sites/default/files/ftp');
			$absoluteDestination = str_replace('public:/', variable_get(FILE_PUB_PATH, NEW_FILE_PUB_PATH), $details[DESTINATION]);
			if(!file_prepare_directory($absoluteDestination, FILE_CREATE_DIRECTORY)) {
				fwrite($log, "\nSkipping node $nodeId because we could not create directory \"$absoluteDestination\".");
				continue;
			}
			$absoluteOrigin = str_replace('public:/', variable_get(FILE_PUB_PATH, NEW_FILE_PUB_PATH), $details[ORIGIN] . '/' . $details[FILENAME]);
			if(!file_exists($absoluteOrigin)) {
				fwrite($log, "\nSkipping node $nodeId because \"$absoluteOrigin\" does not exist.");
				continue;
			}
			$newFile = file_move($fileObj, $details[DESTINATION] . '/' . trim($details[FILENAME]), FILE_EXISTS_REPLACE);
			if($newFile !== false) {
				$details[MOVED] = 'true';
			} else {
				fwrite($log, "\nSkiping node $nodeId because There was a problem moving the file {$details[FILENAME]} to \"{$details[DESTINATION]}\".");
				continue;
			}
		} else {
			$cause = empty($details[DESTINATION]) ? 'could not be determined' : 'did not change';
			fwrite($log, "\nDestination for \"{$details[FILENAME]}\" $cause.");
		}
	}

	// Print out the last file's detials.
	if(!empty($details)) {
		better_fputcsv($out, $details, ',', '"', '"');
	}

	fclose($out);
	fclose($log);
	variable_set(FILE_PUB_PATH, $originalPubPath); // set this value back to the way it was
}

print "Done\n";

