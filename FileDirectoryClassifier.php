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
$logPath = './FileDirectoryClassifier_' . $time . '.log';
$outPath = './FileDirectoryClassifer_' . $time . '.csv';
$log = fopen($logPath, 'w');
$out = fopen($outPath, 'w');
print "Logging errors to $logPath.\n";
print "Writing output to $outPath.\n";

CONST FILENAME = 'filename';
CONST MOVED = 'moved';
CONST DESTINATION = 'destination'; 
CONST ORIGIN = 'origin';
$columns = array(FILENAME, MOVED, DESTINATION, ORIGIN);

$query = new EntityFieldQuery();
$query->entityCondition('entity_type', 'node');
$query->propertyCondition('type', 'downloadable_file');
$result = $query->execute();
$errors = array();

if(isset($result['node'])) {
	$originalPubPath = variable_get(FILE_PUB_PATH, 'sites/default/files/ftp');
	variable_set(FILE_PUB_PATH, '/vectorbase/web/root/sites/default/files/ftp');
	better_fputcsv($out, $columns, ',', '"', '"'); // Create headers for output.
	$details = null;
	foreach(array_keys($result['node']) as $nodeId) {
		
		if(!empty($details)) {
			better_fputcsv($out, ',', '"', '"');
		}
		$details = array();
		$details[FILENAME] = $nodeId;
		$details[MOVED] = false;
		$details[DESTINATION] = '';
		$details[ORIGIN] = '';

		$fileNode = node_load($nodeId);
		if($fileNode == false) {
			fwrite($log, "\nSkipping file node $nodeId because it could not be loaded.");
			continue;
		}
		$fileObj = file_load($fileNode->field_file['und'][0]['fid']);
		$details[FILENAME] = $fileObj->filename;	
		$details[ORIGIN] = $fileObj->uri;
		
		$finalPath = variable_get(FILE_PUB_PATH, '/vectorbase/web/root/sites/default/files/ftp') . '/downloads';
		if(!empty($fileNode->field_organism_taxonomy)) {
			$orgName = taxonomy_term_load($fileNode->field_organism_taxonomy['und'][0]['tid']);
			$orgName = str_replace(' ', '_', $orgName); // get rid of spaces
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
			$root = strtolower($root->shortname['und'][0]['value']);
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
		$details[DESTINATION] = $finalPath;
		/*if(!is_dir($finalPath)) {
			if(!mkdir($finalPath)) {
				fwrite($log, "\nSkipping node $nodeId because we could not create directory \"$root\" in $finalPath.");
				continue;
			}
		}*/
		if(!empty($details[DESTINATION])) {
			$details[MOVED] = true;
		}

		// Yay, we have our directory. Now to move the file to its new home. For now though, just print where you'd move it to.
		// Another thought. Create a csv report listing each existing file, where it will be moved to, and if it can't be moved for some reason, list that.
		// 	Have a "able to move/failed to move" column.

	}
	fclose($out);
	fclose($log);
	variable_set(FILE_PUB_PATH, $originalPubPath); // set this value back to the way it was
}

print "Done\n";

