<?php
/**
 * This file is a utility script designed to bulk update downloadable
 * file nodes (data files) in the VectorBase Drupal 7 framework.
 *
 * Usage: Update.php -m MODE [-f DIR] [-l DIR]
 *
 * Tool to bulk update filenames and other metadata for the "downloadable_file"
 * content type in VectorBase's instance of Drupal 7. This script must be exectued via commandline on a php server
 * which is running the target drupal 7 instance locally. This script 'bootstraps'
 * the drupal environment via the "drupal_bootstrap" function:
 * http://api.drupal.org/api/drupal/includes%21bootstrap.inc/function/drupal_bootstrap/7
 *
 * -m generate, --mode=generate
 * Generates a file with all updatable information from all the "downloadable_file"
 * nodes.
 *
 * -m update, --mode=update
 * Uses the "generated_###.csv" file given by the "-f DIR" flag to update the
 * "downloadable_file" nodes in the drupal database. Only existing records will get
 * updated. If a node is not recognized, the set will be ignored (seee NOTES section).
 *
 * -f DIR, --file=DIR
 * With "-m generate", DIR if optional and specifies where the generated file will be written. If not provided the file will be generated in the current cirectory.
 * With "-m update", DIR specifies the path to the update file itself. This is a required flag in this context.
 *
 * -l DIR, --log=DIR
 * Info/error/warning log output will be written to this directory location OR to
 * the local directory if not provided. The log will be called "generated_###.log"
 * if this script is in "generate" mode, or "updated_###.log" if in "update" mode.
 *
 * NOTES:
 * The file contents of "generated_###.csv" are in comma separated value (CSV) format.
 * The first line is the column header list, each describing an updatable field
 * in the database for "downloadable_file" nodes. Every subsequent line represents
 * an individual node, and Each value can be updated EXCEPT the first, which is the
 * primary key ("nid").  When all changes are made to this file, the "-m update" version of this
 * script will take these values and update the drupal database.
 *
 * @package DataFiles
 * @filesource
 */

define('DRUPAL_ROOT', '/vectorbase/web/root');
$base_path = DRUPAL_ROOT;
define('DL_COUNT_TAG', 'download count');
if(!isset($_SERVER['REMOTE_ADDR'])) {
	$_SERVER['REMOTE_ADDR'] = 'db.vectorbase.org';
} else {
	print 'Remote address: ' . $_SERVER['REMOTE_ADDR'] . "\n";
}
require_once(DRUPAL_ROOT . '/includes/bootstrap.inc');
$phase = drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

CONST NID = 'nid [nid]';
CONST OLD_TITLE = 'original [title]';
CONST NEW_TITLE = 'new [title]';
CONST OLD_FILENAME = 'original [filename]';
CONST NEW_FILENAME = 'new [filename]';
CONST OLD_DESC = 'original [field_description_value]';
CONST NEW_DESC = 'new [field_description_value]';
CONST OLD_DESC_FORMAT = 'original [field_description_format]';
CONST NEW_DESC_FORMAT = 'new [field_description_format]';
$columns = array(NID,OLD_TITLE,NEW_TITLE,OLD_FILENAME,NEW_FILENAME,OLD_DESC,NEW_DESC,OLD_DESC_FORMAT,NEW_DESC_FORMAT);
CONST NUM_CSV_FIELDS =9;
CONST FILE_PUB_PATH = 'file_public_path';
$usage = <<<'USAGE'

Usage: Update.php -m MODE [-f DIR] [-l DIR]

Tool to bulk update filenames and other metadata for the "downloadable_file"
content type in VectorBase's instance of Drupal 7. This script must be exectued via commandline on a php server
which is running the target drupal 7 instance locally. This script 'bootstraps'
the drupal environment via the "drupal_bootstrap" function:
http://api.drupal.org/api/drupal/includes%21bootstrap.inc/function/drupal_bootstrap/7

-m generate, --mode=generate
Generates a file with all updatable information from all the "downloadable_file"
nodes.

-m update, --mode=update
Uses the "generated_###.csv" file given by the "-f DIR" flag to update the
"downloadable_file" nodes in the drupal database. Only existing records will get
updated. If a node is not recognized, the set will be ignored (seee NOTES section).

-f DIR, --file=DIR
With "-m generate", DIR if optional and specifies where the generated file will be written. If not provided the file will be generated in the current cirectory.
With "-m update", DIR specifies the path to the update file itself. This is a required flag in this context.

-l DIR, --log=DIR
Info/error/warning log output will be written to this directory location OR to
the local directory if not provided. The log will be called "generated_###.log"
if this script is in "generate" mode, or "updated_###.log" if in "update" mode.

NOTES:
The file contents of "generated_###.csv" are in comma separated value (CSV) format.
The first line is the column header list, each describing an updatable field
in the database for "downloadable_file" nodes. Every subsequent line represents
an individual node, and Each value can be updated EXCEPT the first, which is the
primary key ("nid").  When all changes are made to this file, the "-m update" version of this
script will take these values and update the drupal database'.


USAGE;

$shortopts = "m:f:l:";
$longopts = array(
		"mode:",
		"file:",
		"log:"
		);

$arguments = getopt($shortopts, $longopts);

$mode = isset($arguments['m']) ? $arguments['m'] : (isset($arguments['mode']) ? $arguments['mode'] : null);
if($mode !== 'generate' && $mode !== 'update') {
	die("\nMissing the mode flag.\n$usage");
}

$logPath = isset($arguments['l']) ? $arguments['l'] : (isset($arguments['log']) ? $arguments['log'] : getcwd());
$filePath = isset($arguments['f']) ? $arguments['f'] : (isset($arguments['file']) ? $arguments['file'] : getcwd());

if(!is_dir($logPath) || !is_writable($logPath)) {
	die("\nThe log file path is either not a directory or is not writable.\n$usage");
}
if(($mode === 'generate' && (!is_dir($filePath) || !is_writable($filePath))) ||
		($mode === 'update' && (!is_file($filePath) || !is_readable($filePath)))) {
	die("\nThe generated file path either does not exist or does not have the proper permissions for this script to use it.\n$usage");
}


// Timestamp to be used in filenames.
$time = time();

if($mode === 'generate') {
	print "Generating...\n";

	// Create a generated results file.
	$file = fopen(rtrim($filePath, '/') . "/generated_$time.csv", 'w');

	// Create a log file.
	$log = fopen(rtrim($logPath, '/') . "/generated_$time.log", 'w');

	fwrite($log, 'Writing to: ' . rtrim($filePath, '/') . "/generated_$time.csv\n");

	$q = db_select('{node}', 'n');
	$q->addField('n', 'nid');
	$q->addField('n', 'vid');
	$q->addField('n', 'title'); // Change if this originally matches the filename
	$q->addField('d', 'field_description_value'); // Description value
	$q->addField('d', 'field_description_format'); // Format, if this field is empty, should be filled in as "filtered_html"
	$q->addField('f', 'filename');
	// 	$q->addField('f', 'uri');
	$q->innerJoin('{field_data_field_description}', 'd', 'n.nid = d.entity_id');
	$q->innerJoin('{field_data_field_file}', 'ff', 'n.nid = ff.entity_id');
	$q->innerJoin('{file_managed}', 'f', 'ff.field_file_fid = f.fid');
	$q->condition('n.type', 'downloadable_file', '=');
	$q->distinct();
	$results = $q->execute();
	better_fputcsv($file, $columns, ',', '"', '"');
	while($record = $results->fetchAssoc()) {
		$lineRecord = array(
				$record['nid'],
				$record['title'],
				$record['title'],
				$record['filename'],
				$record['filename'],
				$record['field_description_value'],
				$record['field_description_value'],
				$record['field_description_format'],
				$record['field_description_format']);
		better_fputcsv($file, $lineRecord, ',', '"');
	}
	fwrite($log, rtrim($filePath, '/') . "/generated.csv has been populated.\n");
	fclose($file);
	fclose($log);

} else if($mode === 'update') {

	print "Updating...\n";

	$nodesUpdated = 0;
	$updatableNodesTotal = 0;	
	// Opening the CSV file to read.
	$file = fopen($filePath, 'r');

	// Creating a log file.
	$log = fopen(rtrim($logPath, '/') . "/updated_$time.log", 'w');

	fwrite($log, 'Reading from: ' . "$filePath\n");

	$updates = array();

	fgetcsv($file, 0, ',');// skips past the first line, which is the header text
	$line = 0;
	while(($data = fgetcsv($file, 0, ',')) !== FALSE) {
		$line++;
		$csv = array();	
		for($i = 0; $i < count($data); $i++) {
			$csv[$columns[$i]] = $data[$i];			
		}
		$errorArray = validateDataFileDataCsv($csv);
		if(!empty($errorArray)) {
			fwrite($log, "\nErrors for {$csv[NEW_FILENAME]} (nid={$csv[NID]}), line $line");
			foreach($errorArray as $error) {
				fwrite($log, "\n\t$error");	
			}
		} else {
			$updates[$csv[NID]] = $csv;
		}
	}

	// Close the csv file.
	fclose($file);

	// For dev purposes
	if(!empty($updates)) {
		fwrite($log, "\n");
	}
	foreach($updates as $update) {
		fwrite($log, "\n{$update[NEW_FILENAME]} (nid={$update[NID]}) is valid and will be a candidate to be updated.");
	}

	$answered = false;
	$continueUpdates = false;
	while(!$answered) {	
		$answer = readline(count($updates) . ' file nodes will be updated, and ' . ($line - count($updates)) . ' will be skipped. See the log for details. Do you want to continue with the updates? (y/n): ');
		if($answer !== false) {
			if(strcasecmp($answer, 'y') === 0) {
				$answered = true;
				$continueUpdates = true;
			} else if (strcasecmp($answer, 'n') === 0) {
				$answered = true;
			}
		} else {
			print "\nScript terminated by user.\n";
			fclose($log);
			exit;
		}
	}

	if($continueUpdates) {
		print "\nUpdates will be applied.";
	} else {
		print "\nNo updates will be applied. See log for results.\n";
		fclose($log);
		exit;
	}

	$query = new EntityFieldQuery();
	$query->entityCondition('entity_type', 'node');
	$query->propertyCondition('type', 'downloadable_file');
	$query->entityCondition('entity_id', array_keys($updates), 'IN');
	$result = $query->execute();
	$errors = array();
	if(isset($result['node'])) {
		// Looping through the drupal nodes. The drupal nid will map to it's update info in the updates array.

		$originalPubPath = variable_get(FILE_PUB_PATH, 'sites/default/files/ftp');
		variable_set(FILE_PUB_PATH, '/vectorbase/web/root/sites/default/files/ftp');

		$updatableNodesTotal = count($result['node']);
		foreach(array_keys($result['node']) as $nodeId) {
			$fileNode = node_load($nodeId);
			if($fileNode === false) {
				fwrite($log, "\n\tNode $nodeId could not be loaded, so its updates will be skipped.");
				continue;	
			}
			$update = $updates[$nodeId];
			fwrite($log, "\nExamining {$fileNode->title} ($nodeId)");
			$updateNode = false;
			$newFile = null;
			if($update[OLD_FILENAME] === $fileNode->field_file['und'][0]['filename']) {
				if($update[OLD_FILENAME] !== $update[NEW_FILENAME]) {
					$fileObj = file_load($fileNode->field_file['und'][0]['fid']);

					$newFile = file_move($fileObj, dirname($fileNode->field_file['und'][0]['uri']) . '/' . trim($update[NEW_FILENAME]), FILE_EXISTS_REPLACE);

					if($newFile !== false) {
						$updateNode = true;
					} else {
						fwrite($log, "\n\tIgnoring this drupal node because there was a problem changing the name of the file for node $nodeId.");
						continue;
					}
				} else {
					fwrite($log, "\n\tSkipping FILENAME since it has not changed.");
				}
			} else {
				fwrite($log, "\n\tIgnoring this drupal node $nodeId because the its FILENAME is out of sync with the update file.");
				continue;
			}
			// Update the title.
			if($update[OLD_TITLE] === $fileNode->title) {
				if($update[OLD_TITLE] !== $update[NEW_TITLE]) {
					$fileNode->title = $update[NEW_TITLE];
					$updateNode = true;
				} else {
					fwrite($log, "\n\tSkipping TITLE since it has not changed.");
				}
			} else {
				fwrite($log, "\n\tIgnoring this node $nodeId because the its TITLE is out of sync with the update file.");
				continue;
			}
			// Update the description.
			if($update[OLD_DESC] === $fileNode->field_description['und'][0]['value']) {
				if($update[OLD_DESC] !== $update[NEW_DESC]) {
					$fileNode->field_description['und'][0]['value'] = $update[NEW_DESC];
					$updateNode = true;
				} else {
					fwrite($log, "\n\tSkipping DESCRIPTION update of node $nodeId since it has not changed.");
				}
			} else {
				fwrite($log, "\n\tIgnoring this drupal node $nodeId because the its DESCRIPTION is out of sync with the update file.");
				continue;
			}
			// Update the description format (default to "filtered_html" if the update field is empty).
			if($update[OLD_DESC_FORMAT] === $fileNode->field_description['und'][0]['format']) {
				if($update[OLD_DESC_FORMAT] !== $update[NEW_DESC_FORMAT]) {
					$fileNode->field_description['und'][0]['format'] = $update[NEW_DESC_FORMAT];
					$updateNode = true;
				} else if(empty($update[NEW_DESC_FORMAT])) {
					$fileNode->field_description['und'][0]['format'] = 'filtered_html';
					fwrite($log, "\n\tDesc format was empty. Filling it in with \'filtered_html\'.");
					$updateNode = true;
				} else {
					fwrite($log, "\n\tSkipping DESCRIPTION FORMAT update of node $nodeId since it has not changed.");
				}
			} else {
				fwrite($log, "\n\tIgnoring this drupal node $nodeId because the its DESCRIPTION FORMAT is out of sync with the update file.");
				continue;
			}
			// Only save changes if fields were modified.
			if($updateNode) {
				node_save($fileNode);
				$newFile->filename = $update[NEW_FILENAME];
				file_save($newFile);
				fwrite($log, "\n\tUpdated drupal node $nodeId.");
				$nodesUpdated++;
			}
		}
		variable_set(FILE_PUB_PATH, $originalPubPath);
	} else {
		fwrite($log, "\nProblem with drupal query.  EntityFieldQuery did not return a \"node\" index.");
		print 'Problem with drupal query.';
	}

	fclose($log);
	print "Nodes updated: $nodesUpdated out of $updatableNodesTotal\n";
}

print "Done.\n";

/**
 * Extends php.net/manual/en/function.fputcsv.php 
 * 
 * This function does not return any values. Rather,
 * it writes to the given file handle by reference.
 *
 * @param object $handle File handle to write to
 * @param string[] $fields Arrayed data to print.
 * @param string $delimiter Character that separates the CSV data.
 * @param string $enclosure Character used to quote data that might contain delimiters
 * and other abnormal characters.
 * @param string $escape Character to escape special characters, like delimiters, enclosures, and
 * this escape character.
 */
function better_fputcsv($handle, $fields, $delimiter = ',', $enclosure = '"', $escape = '\\') {
	$first = 1;
	foreach ($fields as $field) {
		if ($first == 0) fwrite($handle, ",");
		$f = preg_replace('/(\r\n|\r|\n)/','<br>',$field);
		$f = str_replace($enclosure, $enclosure.$enclosure, $f);
		if ($enclosure != $escape) {
			$f = str_replace($escape.$enclosure, $escape, $f);
		}
		if (strpbrk($f, " \t\n\r".$delimiter.$enclosure.$escape) || strchr($f, "\000")) {
			fwrite($handle, $enclosure.$f.$enclosure);
		} else {
			fwrite($handle, $f);
		}

		$first = 0;
	}
	fwrite($handle, "\n");
}


/**
 * Validates the data for each line from the generated file.
 *
 * Only certain fields are checked, namely the "filename" field. This is the primary reason for
 * this function.
 *
 * @param string[] $csvArray List of data, defined by the global $columns array in this script
 * that is comprised of string constants.
 * @return string[] List of errors and/or things wrong with the given data. If this list is empty,
 * then the given data is valid.
 */
function validateDataFileDataCsv($csvArray) {

	// If this is filled, something went wrong with the validation.
	$errorArray = array();

	if(empty($csvArray) || count($csvArray) !== NUM_CSV_FIELDS) {
		$errorArray[] = 'Provided data was empty or null';
		return $errorArray;
	}
	$errorArray = array_merge($errorArray, filenameValidator($csvArray[NEW_FILENAME]));
	$errorArray = array_merge($errorArray, validateDescription($csvArray[NEW_DESC]));
	return $errorArray;
}


/**
 * Validates the filename defined here: https://www.vectorbase.org/content/data-file-format-guide
 * 
 * @param string $filename Name of the file
 * @return srting[] A list of errors, if they occur. An empty array indicates a valid name.
 */
function filenameValidator($filename) {

	$errorArray = array();
	
	$fileNameParts = array_reverse(array_map('strrev', explode('.', strrev($filename), 3)));
	if(count($fileNameParts) !== 2 && count($fileNameParts) !== 3) {
		$errorArray[] = "Filename ($filename) does not have the expected two or three parts separated by periods";
		return $errorArray;
	}

	$vocab = taxonomy_vocabulary_machine_name_load('download_file_formats');
	$tree = taxonomy_get_tree($vocab->vid, 0, null, true);
	$fieldValid = false;	
	foreach($tree as $tax) {
		if($tax->field_extension['und'][0]['value'] === $fileNameParts[1]) {
			$fieldValid = true;
			break;
		}
	}
	if(!$fieldValid) {
		$errorArray[] = "Format extension ($fileNameParts[1]) not recognized";
	}

	if(count($fileNameParts) > 2) {
		$fieldValid = false;
		$extensions = array('bam', 'bai', 'fa', 'txt', 'gff', 'gff3', 'gtf', 'gz', 'obo', 'qual', 'agp', 'zip', 'tgz');
		foreach($extensions as $ext) {
			if($ext === $fileNameParts[2]) {
				$fieldValid = true;
				break;
			}

		}
		if(!$fieldValid) {
			$errorArray[] = "File extension ($fileNameParts[2]) not recognized";
		}

	}

	// Validate the first part of the filename

	$bodyParts = explode('_',$fileNameParts[0]);
	$bodyPartIndex = 0;
	$vocab = taxonomy_vocabulary_machine_name_load('organisms_taxonomy');
	$tree = taxonomy_get_tree($vocab->vid);
	$fieldValid = false;
	foreach($tree as $tax) {
		$taxStr = str_replace(' ', '-', $tax->name);
		$taxStr = str_replace('.', '', $taxStr);
		// Checking if the org name is within the first part of the filename
		if(strpos(strtolower($bodyParts[$bodyPartIndex]), strtolower($taxStr)) !== false) {
			$fieldValid = true;
			break;
		}
	}

	// If true, we have a non-onology file and the category/type is
	// in index 1.  If not, we have an onotlogy and it is in spot 0.
	if($fieldValid) {
		$bodyPartIndex++;
	}

	// Check the category
	$vocab = taxonomy_vocabulary_machine_name_load('download_file_types');
	$tree = taxonomy_get_tree($vocab->vid, 0, null, true);
	$fieldValid = false;
	foreach($tree as $tax) {
		if($tax->field_shortname['und'][0]['value'] === $bodyParts[$bodyPartIndex]) {
			$fieldValid = true;
			break;
		}
	}
	if(!$fieldValid) {
		$errorArray[] = 'File category/type (' . $bodyParts[$bodyPartIndex] . ') is not recognized';
	}

	$bodyPartIndex++;

	// Check the version
	//$v = explode('-', $bodyParts[$bodyPartIndex]);

	$haveVersionDate = false;

	// skip the "version" field as its format is wonky and not worth looking at right now.
	if($bodyPartIndex < sizeof($bodyParts) - 1) {
		$bodyPartIndex++;
		$haveVersionDate = true;
	}


	/*if(count($v) === 1 || !is_numeric($v[0])) {
		if(!is_numeric(str_replace('v', '', $v[count($v) - 1]))) {
			$errorArray[] = "File version ({$bodyParts[$bodyPartIndex]}) does not contain a valid number";
		}
		$haveVersionDate = true;
		$bodyPartIndex++;
	}*/ 
	if($bodyPartIndex < sizeof($bodyParts)) {
		$dateParts = explode('-', $bodyParts[$bodyPartIndex]);
		// checkdate(month, day, year) - so dumb that ordering, right?
		if(count($dateParts) <= 3 && count($dateParts) >= 2 && array_reduce($dateParts, 'is_numeric', true)) {
			if((count($dateParts) === 3 && !checkdate($dateParts[1],$dateParts[2], $dateParts[0])) ||
				(count($dateParts) === 2 && !checkdate($dateParts[1], '01', $dateParts[0]))) {
				$errorArray[] = 'File date (' . $bodyParts[$bodyPartIndex] . ') is not a valid date';
			}
		}
		$haveVersionDate = true;
	}

	if(!$haveVersionDate) {
		$errorArray[] = 'Missing version and/or date field(s)';
	}

	return $errorArray; 
}


/**
 * Validates the description field.
 *
 * This exteremely simple check errors if the text 'Please describe.' shows up.
 * current policy is that if there is nothing to describe, leave this field blank.
 *
 * @param string $description Gives some detail on the file.
 * @return srting[] A list of errors, if they occur. An empty array indicates a valid description.
 */
function validateDescription($description) {

	$errorArray = array();
	if($description === 'Please describe.') {
		$errorArray[] = 'Please remove this description \'todo\' text as this field should be left blank if there is nothing to describe';
	}
	return $errorArray;
}

