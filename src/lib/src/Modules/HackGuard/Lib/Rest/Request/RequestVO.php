<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Rest\Request;

/**
 * @property string[] $scan_slugs
 */
class RequestVO extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\RequestVO {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {

			case 'scan_slugs':
				// we know only valid slugs are ever supplied as it's in the API schema
				$value = (array)$value;
				break;
		}

		return $value;
	}
}