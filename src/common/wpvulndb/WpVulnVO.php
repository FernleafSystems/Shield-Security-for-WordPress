<?php

class ICWP_WPSF_WpVulnVO {

	/**
	 * @var stdClass
	 */
	protected $oRaw;

	public function __construct( $oRawVuln ) {
		$this->oRaw = $oRawVuln;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->getRawProperty( 'id', 0 );
	}

	/**
	 * @return string
	 */
	public function getDateCreated() {
		$sDate = $this->getRawProperty( 'created_at', '' );
		return empty( $sDate ) ? 0 : strtotime( $sDate );
	}

	/**
	 * @return string
	 */
	public function getDatePublished() {
		$sDate = $this->getRawProperty( 'published_date', '' );
		return empty( $sDate ) ? 0 : strtotime( $sDate );
	}

	/**
	 * @return string
	 */
	public function getDateUpdated() {
		$sDate = $this->getRawProperty( 'updated_at', '' );
		return empty( $sDate ) ? 0 : strtotime( $sDate );
	}

	/**
	 * @return stdClass
	 */
	public function getReferences() {
		return $this->getRawProperty( 'references', new stdClass() );
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->getRawProperty( 'title', 'No Title Available' );
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->getRawProperty( 'vuln_type', 'No Type Available' );
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return sprintf( 'https://wpvulndb.com/vulnerabilities/%s', $this->getId() );
	}

	/**
	 * @return int
	 */
	public function getVersionFixedIn() {
		return $this->getRawProperty( 'fixed_in', 'Unknown Fixed Version' );
	}

	/**
	 * @param string $sProperty
	 * @param mixed $mDefault
	 * @return mixed
	 */
	public function getRawProperty( $sProperty, $mDefault = null ) {
		return isset( $this->oRaw->{$sProperty} ) ? $this->oRaw->{$sProperty} : $mDefault;
	}

	/**
	 * @return stdClass
	 */
	public function getRaw() {
		return $this->oRaw;
	}
}