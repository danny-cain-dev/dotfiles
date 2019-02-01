<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
spl_autoload_register(function($class){
	$baseNS = 'Danny\\Scripts\\';
	$baseFS = __DIR__.'/classes/';

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

function findConfig($id) {
	$paths = getConfigPaths($id);
	foreach($paths as $path) {
		if (file_exists($path))
			return $path;
	}
	return null;
}

function fetchConfig($id) {
	$path = findConfig($id);
	if ($path !== null)
		return json_decode(file_get_contents($path), true);

	return null;
}

function saveConfig($id, $config) {
	$path = findConfig($id);
	if ($path !== null)
		file_put_contents($path, json_encode($config));
	else
		file_put_contents(defaultConfigPath($id), json_encode($config));
}

function defaultConfigPath($id) {
	return home_directory().'/.danny-scripts/'.$id.'.json';
}

function getConfigPaths($id) {
	return array(
		__DIR__.'/.'.$id.'.json',
		home_directory().'/.'.$id.'.json',
		home_directory().'/.danny-scripts/'.$id.'.json',
	);
}

function home_directory() {
	$home = getenv('HOME');
	if (!empty($home)) {
		$home = rtrim($home, '/');
	}
	elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
		$home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
		$home = rtrim($home, '\\/');
	}
	return empty($home) ? NULL : $home;
}