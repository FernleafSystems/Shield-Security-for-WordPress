<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property array  $properties
 * @property array  $options
 * @property array  $sections
 *                                   -- not part of config file --
 * @property string $slug
 */
class ModConfigVO extends DynPropertiesClass {

	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'options':
			case 'sections':
			case 'properties':
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