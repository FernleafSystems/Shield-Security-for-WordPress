<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @property string[] $filter_fields
 */
class RequestVO extends \FernleafSystems\Wordpress\Plugin\Core\Rest\Request\RequestVO {

	use PluginControllerConsumer;

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {

			case 'filter_fields':
				$value = (array)$value;
				if ( \in_array( 'all', $value ) ) {
					$value = [];
				}
				else {
					$value = \array_merge( $this->getDefaultFilterFields(), $value );
				}

				$value = \array_flip( \array_filter( $value ) );
				break;
		}

		return $value;
	}

	protected function getDefaultFilterFields() :array {
		return [];
	}
}