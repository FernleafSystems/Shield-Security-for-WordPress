<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class WpVulnVO
 * @property int    id
 * @property string title
 * @property string vuln_type
 * @property string fixed_in
 * @property string references
 * @property int    updated_at
 * @property int    created_at
 * @property int    published_date
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb
 */
class WpVulnVO {

	use StdClassAdapter;

	/**
	 * @param string $sProperty
	 * @return int
	 */
	public function getAsTimeStamp( $sProperty ) {
		return strtotime( $this->{$sProperty} );
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getDateCreated() {
		return empty( $this->created_at ) ? 0 : strtotime( $this->created_at );
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getDatePublished() {
		return empty( $this->published_date ) ? 0 : strtotime( $this->published_date );
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getDateUpdated() {
		return empty( $this->updated_at ) ? 0 : strtotime( $this->updated_at );
	}

	/**
	 * @return \stdClass
	 * @deprecated
	 */
	public function getReferences() {
		return $this->getRawProperty( 'references', new \stdClass() );
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getTitle() {
		return $this->getRawProperty( 'title', 'No Title Available' );
	}

	/**
	 * @return string
	 * @deprecated
	 */
	public function getType() {
		return $this->getRawProperty( 'vuln_type', 'No Type Available' );
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return sprintf( 'https://wpvulndb.com/vulnerabilities/%s', $this->id );
	}

	/**
	 * @return int
	 * @deprecated
	 */
	public function getVersionFixedIn() {
		return $this->getRawProperty( 'fixed_in', 'Unknown Fixed Version' );
	}
}