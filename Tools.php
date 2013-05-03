<?php

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

function aUselessFcnForTest() {
return 0;
}
