<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class MWPSiteVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common
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