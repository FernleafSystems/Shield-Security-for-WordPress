<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\GeoIp;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EntryVO
 * @property string rid
 * @property int    uid
 * @property string ip
 * @property string path
 * @property string code
 * @property string ua
 * @property string verb
 * @property bool   trans
 */
class EntryVO extends Base\EntryVO {

	/**
	 * @return string
	 */
	public function getCountryCode() {
		return $this->meta[ 'countryCode' ] ?? '';
	}

	/**
	 * @return string
	 */
	public function getCountryName() {
		return $this->meta[ 'countryName' ] ?? '';
	}

	/**
	 * @return string
	 */
	public function getLatitude() {
		return $this->meta[ 'latitude' ] ?? '';
	}

	/**
	 * @return string
	 */
	public function getLongitude() {
		return $this->meta[ 'longitude' ] ?? '';
	}

	/**
	 * @return string
	 */
	public function getTimezone() {
		return $this->meta[ 'timeZone' ] ?? '';
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {
		switch ( $key ) {
			case 'ip':
				$value = inet_ntop( parent::__get( $key ) );
				break;

			default:
				$value = parent::__get( $key );
		}
		return $value;
	}

	/**
	 * @inheritDoc
	 */
	public function __set( string $key, $value ) {

		switch ( $key ) {

			case 'ip':
				$value = inet_pton( $value );
				break;

			default:
				break;
		}

		parent::__set( $key, $value );
	}
}