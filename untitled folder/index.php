<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('textdomaincheck.php');

function checkcount()
{
	global $checkcount;
	$checkcount++;
}

function tc_filename($file)
{
	$filename = (preg_match('/themes\/[a-z0-9-]*\/(.*)/', $file, $out)) ? $out[1] : basename($file);
	return $filename;
}
function getDirContents($dir, &$results = array())
{
	$files = scandir($dir);

	foreach ($files as $key => $value) {
		$path = realpath($dir . DIRECTORY_SEPARATOR . $value);
		if (!is_dir($path)) {
			$results[] = $path;
		} else if ($value != "." && $value != "..") {
			getDirContents($path, $results);
			$results[] = $path;
		}
	}

	return $results;
}

function tc_strip_comments($code)
{
	$strip = array(T_COMMENT => true, T_DOC_COMMENT => true);
	$newlines = array("\n" => true, "\r" => true);
	$tokens = token_get_all($code);
	reset($tokens);
	$return = '';
	$token = current($tokens);
	while ($token) {
		if (!is_array($token)) {
			$return .= $token;
		} elseif (!isset($strip[$token[0]])) {
			$return .= $token[1];
		} else {
			for ($i = 0, $token_length = strlen($token[1]); $i < $token_length; ++$i)
				if (isset($newlines[$token[1][$i]]))
					$return .= $token[1][$i];
		}
		$token = next($tokens);
	}
	return $return;
}

$files = getDirContents("licence");

foreach ($files as $key => $filename) {

	if (substr($filename, -4) == '.php' && !is_dir($filename)) {
		$php[$filename] = file_get_contents($filename);
		$php[$filename] = tc_strip_comments($php[$filename]);
	} else if (substr($filename, -4) == '.css' && !is_dir($filename)) {
		$css[$filename] = file_get_contents($filename);
	} else {
		$other[$filename] = (!is_dir($filename)) ? file_get_contents($filename) : '';
	}
}

$chk = new TextDomainCheck;
$chk->check($php, $css, $other);
echo "<pre>", print_r($chk->getError());
