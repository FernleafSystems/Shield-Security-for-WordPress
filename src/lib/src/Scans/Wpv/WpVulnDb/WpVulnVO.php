<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * Class WpVulnVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb
 * @property int    $id
 * @property string $url
 * @property string $title
 * @property string $vuln_type
 * @property string $fixed_in
 * @property string $references
 * @property int    $updated_at
 * @property int    $created_at
 * @property int    $published_date
 */
class WpVulnVO extends DynPropertiesClass {

	const URL_BASE = 'https://wpscan.com/vulnerability/%s';

	/**
	 * @inheritDoc
	 */
	public function __get( string $key ) {
		$val = parent::__get( $key );
		switch ( $key ) {

			case 'url':
				if ( empty( $val ) ) {
					$val = sprintf( self::URL_BASE, $this->id );
				}
				break;

			default:
				break;
		}

		return $val;
	}
}