<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

/**
 * @property array[]  $options
 * @property string[] $filter_keys
 */
class RequestVO extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\RequestVO {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'filter_keys':
				$value = (array)$value;
				break;
		}

		return $value;
	}

	protected function getDefaultFilterFields() :array {
		return [
			'key',
			'value',
			'module',
		];
	}
}