<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Extensions;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string $file
 * @property string $slug
 * @property array  $options
 * @property array  $sections
 */
class ExtensionConfigVO extends DynPropertiesClass {

	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'sections':
			case 'options':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}

		return $value;
	}
}