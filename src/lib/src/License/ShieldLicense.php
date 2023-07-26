<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\License;

/**
 * @property bool   $is_central
 * @property string $aff_ref
 * @property array  $crowdsec
 * @property array  $capabilities
 * @property int    $lic_version
 */
class ShieldLicense extends \FernleafSystems\Wordpress\Services\Utilities\Licenses\EddLicenseVO {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'capabilities':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			case 'lic_version':
				$value = empty( $value ) ? 0 : (int)$value;
				break;
			default:
				break;
		}
		return $value;
	}
}