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
	 * @deprecated
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @deprecated
	 * @return string
	 */
	public function getDateCreated() {
		return empty( $this->created_at ) ? 0 : strtotime( $this->created_at );
	}

	/**
	 * @deprecated
	 * @return string
	 */
	public function getDatePublished() {
		return empty( $this->published_date ) ? 0 : strtotime( $this->published_date );
	}

	/**
	 * @deprecated
	 * @return string
	 */
	public function getDateUpdated() {
		return empty( $this->updated_at ) ? 0 : strtotime( $this->updated_at );
	}

	/**
	 * @deprecated
	 * @return \stdClass
	 */
	public function getReferences() {
		return $this->getRawProperty( 'references', new \stdClass() );
	}

	/**
	 * @deprecated
	 * @return string
	 */
	public function getTitle() {
		return $this->getRawProperty( 'title', 'No Title Available' );
	}

	/**
	 * @deprecated
	 * @return string
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
	 * @deprecated
	 * @return int
	 */
	public function getVersionFixedIn() {
		return $this->getRawProperty( 'fixed_in', 'Unknown Fixed Version' );
	}
}