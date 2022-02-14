<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;

/**
 * @property array $scan_slugs
 */
class RequestVO extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\RequestVO {

	public function __get( string $key ) {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$value = parent::__get( $key );

		switch ( $key ) {

			case 'scan_slug':
				// we know only valid slugs are ever supplied as it's in the API schema
				$scansToFilter = $opts->getScanSlugs();
				$value = empty( $value ) ? $scansToFilter
					: array_intersect( $scansToFilter, explode( ',', $value ) );
				break;
		}

		return $value;
	}
}