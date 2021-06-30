<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * Class VulnVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb
 * @property string|int $id
 * @property string     $title
 * @property string     $description
 * @property string     $vuln_type
 * @property string     $fixed_in
 * @property array      $references
 * @property int        $published_at
 * @property int        $disclosed_at
 * @property int        $created_at
 * @property int        $updated_at
 */
class VulnVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( preg_match( '#_at$#', '_at' ) ) {
			$value = (int)$value;
		}

		switch ( $key ) {
			case 'references':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}

		return $value;
	}
}