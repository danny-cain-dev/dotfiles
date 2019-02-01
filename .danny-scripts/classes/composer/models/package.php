<?php

namespace Danny\Scripts\Composer\Models;

class Package {
	protected $name = '';
	protected $description = '';
	protected $url = '';
	protected $repo = '';
	protected $downloads = 0;
	protected $favourites = 0;
	protected $abandoned = '';
	protected $virtual = false;

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName( $name ) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @param string $description
	 */
	public function setDescription( $description ) {
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @param string $url
	 */
	public function setUrl( $url ) {
		$this->url = $url;
	}

	/**
	 * @return string
	 */
	public function getRepo() {
		return $this->repo;
	}

	/**
	 * @param string $repo
	 */
	public function setRepo( $repo ) {
		$this->repo = $repo;
	}

	/**
	 * @return int
	 */
	public function getDownloads() {
		return $this->downloads;
	}

	/**
	 * @param int $downloads
	 */
	public function setDownloads( $downloads ) {
		$this->downloads = $downloads;
	}

	/**
	 * @return int
	 */
	public function getFavourites() {
		return $this->favourites;
	}

	/**
	 * @param int $favourites
	 */
	public function setFavourites( $favourites ) {
		$this->favourites = $favourites;
	}

	/**
	 * @return string
	 */
	public function getAbandoned() {
		return $this->abandoned;
	}

	/**
	 * @param string $abandoned
	 */
	public function setAbandoned( $abandoned ) {
		$this->abandoned = $abandoned;
	}

	/**
	 * @return bool
	 */
	public function isVirtual() {
		return $this->virtual;
	}

	/**
	 * @param bool $virtual
	 */
	public function setVirtual( $virtual ) {
		$this->virtual = $virtual;
	}
}