<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Rest\Request;

/**
 * @property string[] $scan_slugs
 */
class RequestVO extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request\RequestVO {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'scan_slugs':
				$value = (array)$value;
				if ( empty( $value ) ) {
					$value = self::con()->comps->scans->getScanSlugs();
				}
				break;
		}

		return $value;
	}
}