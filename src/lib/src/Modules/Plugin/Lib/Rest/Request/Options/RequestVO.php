<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

/**
 * @property array[]  $options
 * @property string[] $filter_fields
 * @property string[] $filter_keys
 */
class RequestVO extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\RequestVO {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {

			case 'filter_keys':
				$value = (array)$value;
				break;

			case 'filter_fields':
				$value = (array)$value;
				if ( in_array( 'all', $value ) ) {
					$value = [];
				}
				else {
					$value = array_merge( [
						'key',
						'value',
						'module',
					], $value );
				}

				$value = array_flip( array_filter( $value ) );
				break;
		}

		return $value;
	}
}