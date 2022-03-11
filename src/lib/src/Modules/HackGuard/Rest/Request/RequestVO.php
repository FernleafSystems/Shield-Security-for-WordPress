<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

/**
 * @property string[] $scan_slugs
 */
class RequestVO extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\RequestVO {

	public function __get( string $key ) {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'scan_slugs':
				$value = (array)$value;
				if ( empty( $value ) ) {
					$value = $opts->getScanSlugs();
				}
				break;
		}

		return $value;
	}
}