<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use FernleafSystems\Wordpress\Services\Services;
use MainWP\Dashboard\MainWP_DB;

/**
 * Class MWPSiteVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common
 * @property object  $siteobj // For use with MainWP functions
 * @property array[] $plugins
 * @property array[] $themes
 */
class MWPSiteVO {

	use StdClassAdapter {
		__get as __adapterGet;
	}

	/**
	 * @param int $siteID
	 * @return MWPSiteVO
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
	 * @param string $property
	 * @return mixed
	 */
	public function __get( $property ) {

		$mValue = $this->__adapterGet( $property );

		switch ( $property ) {
			case 'siteobj':
				$mValue = Services::DataManipulation()->convertArrayToStdClass( $this->getRawDataAsArray() );
				break;
			case 'plugins':
			case 'themes':
				$mValue = json_decode( $mValue ?? '[]', true );
				break;
			default:
				break;
		}

		return $mValue;
	}
}