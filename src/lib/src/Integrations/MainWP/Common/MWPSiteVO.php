<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class MWPSiteVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common
 * @property object  $siteobj // For use with MainWP functions
 * @property array[] $plugins
 * @property array[] $themes
 */
class MWPSiteVO {

	use StdClassAdapter {
		__get as __adapterGet;
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