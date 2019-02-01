<?php

require dirname(__DIR__).'/vendor/autoload.php';

spl_autoload_register(function($class){
	$baseNS = 'Danny\\Scripts\\';
	$baseFS = dirname(__DIR__).'/classes/';

	if (substr(strtolower($class), 0, strlen($baseNS)) != strtolower($baseNS))
		return false;

	$path = strtolower(substr($class, strlen($baseNS))).'.php';
	$path = strtr($path, array('\\' => DIRECTORY_SEPARATOR));
	$path = $baseFS.$path;
	if (!file_exists($path))
		return false;

	require_once $path;
	return true;
});