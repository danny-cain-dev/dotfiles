<?php

namespace Danny\Scripts\Composer;

use Danny\Scripts\Composer\Models\Package;

class Packagist {
	protected $domain = 'https://packagist.org';
	// https://packagist.org/packages.json indexy type thing

	protected $searchURL = '';
	protected $providers = [];

	public function getProviders() {
		$providerListURL = $this->domain.'/packages.json';
		$curl = curl_init($providerListURL);
		curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		$response = \json_decode($response, true);

		$this->searchURL = $response['search'];
		$providers = [];
		foreach($response['provider-includes'] as $provider => $config) {
			$url = $provider;
			$url = strtr($url, ["%hash%" => $config['sha256']]);
			$url = $this->domain.'/'.$url;

			$providers[$provider] = $url;
		}
		$this->providers = $providers;
	}

	/**
	 * @param $type
	 * @param $query
	 *
	 * @return Package[]
	 */
	public function search($type, $query) {
		$url = strtr($this->searchURL, [
			"%query%" => $query,
			"%type%" => $type
		]);

		$curl = curl_init($url);
		curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		$response = \json_decode($response, true);

		$results = [];
		$hasNext = true;
		while($hasNext) {
			foreach($response['results'] as $result) {
				$model = new Package();
				$model->setName($result['name']);
				$model->setDescription($result['description']);
				$model->setUrl($result['url']);
				$model->setRepo($result['repository']);
				if (isset($result['downloads']))
					$model->setDownloads($result['downloads']);
				if (isset($result['favers']))
					$model->setFavourites($result['favers']);
				if (isset($result['abandoned']))
					$model->setAbandoned($result['abandoned']);
				if (isset($result['virtual']))
					$model->setVirtual($result['virtual']);

				$results[] = $model;
			}
			$hasNext = isset($response['next']);
			if ($hasNext) {
				$curl = curl_init($response['next']);
				curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec($curl);
				$response = \json_decode($response, true);
			}
		}


		return $results;
	}

	public function find($package) {
		foreach($this->search('', $package) as $result) {
			if ($result->getName() != $package)
				continue;

			return $result;
		}
		return null;
	}

	public function fetch($package_name) {
		$package = $this->find($package_name);
		if ($package === null) {
			return false;
		}

		$repo = $package['repository'];

		$tmp = \tempnam(\sys_get_temp_dir(), 'pck');
		chdir(dirname($tmp));
		unlink($tmp);

		exec("git clone $repo ".basename($tmp));
		$composer = \file_get_contents($tmp.\DIRECTORY_SEPARATOR.'composer.json');
		exec("rm -rf ".basename($tmp));

		\var_dump(\json_decode($composer, true));
//		\var_dump($package);
//		$url = $package['url'];
//		echo $url."\n";
//		exit;
//		$curl = curl_init($url);
//		curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
//		$response = curl_exec($curl);
//
//		\var_dump(\json_decode($response, true));

		/*
 array(5) {
      'name' =>
      string(15) "phpunit/phpunit"
      'description' =>
      string(0) ""
      'url' =>
      string(47) "https://packagist.org/providers/phpunit/phpunit"
      'repository' =>
      string(0) ""
      'virtual' =>
      bool(true)
		 */

		/*
		[5] =>
    array(6) {
      'name' =>
      string(30) "phpunit/phpunit-dom-assertions"
      'description' =>
      string(26) "DOM assertions for PHPUnit"
      'url' =>
      string(61) "https://packagist.org/packages/phpunit/phpunit-dom-assertions"
      'repository' =>
      string(50) "https://github.com/lstrojny/phpunit-dom-assertions"
      'downloads' =>
      int(87619)
      'favers' =>
      int(20)
    }
		 */
	}
}