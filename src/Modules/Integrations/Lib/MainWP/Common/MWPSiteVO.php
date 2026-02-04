<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Services\Services;
use MainWP\Dashboard\MainWP_DB;

/**
 * @property string  $id
 * @property string  $userid
 * @property string  $adminname
 * @property string  $name
 * @property string  $url
 * @property string  $siteurl
 * @property object  $siteobj // For use with MainWP functions
 * @property array[] $plugins
 * @property array[] $themes
 */
class MWPSiteVO extends DynPropertiesClass {

	/**
	 * @throws \Exception
	 */
	public static function LoadByID( int $siteID ) :MWPSiteVO {
		$raw = MainWP_DB::instance()->get_website_by_id( $siteID );
		if ( empty( $raw ) ) {
			throw new \Exception( 'Invalid Site ID' );
		}
		return ( new MWPSiteVO() )->applyFromArray(
			Services::DataManipulation()->convertStdClassToArray( $raw )
		);
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'siteobj':
				$value = Services::DataManipulation()->convertArrayToStdClass( $this->getRawData() );
				break;
			case 'plugins':
			case 'themes':
				$value = \json_decode( $value ?? '[]', true );
				break;
			default:
				break;
		}

		return $value;
	}
}