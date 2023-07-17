<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string|int $id
 * @property string     $title
 * @property string     $description
 * @property string     $vuln_type
 * @property string     $fixed_in
 * @property array      $references
 * @property int        $disclosed_at
 * @property int        $created_at
 * @property string     $provider
 */
class VulnVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( \preg_match( '#_at$#', $key ) ) {
			$value = (int)$value;
		}
		else {
			switch ( $key ) {
				case 'references':
					if ( !\is_array( $value ) ) {
						$value = [];
					}
					break;
				default:
					break;
			}
		}

		return $value;
	}
}