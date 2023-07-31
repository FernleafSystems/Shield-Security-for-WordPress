<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Config;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property array  $properties
 * @property array  $reqs
 * @property array  $menus
 * @property array  $meta
 * @property array  $options
 * @property array  $sections
 * @property array  $admin_notices
 * @property array  $wpcli
 * @property array  $definitions
 *                                   -- not part of config file --
 * @property string $slug
 */
class ModConfigVO extends DynPropertiesClass {

	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'reqs':
				$value = \array_merge(
					[
						'dbs' => [],
					],
					\is_array( $value ) ? $value : []
				);
				break;
			default:
				break;
		}

		return $value;
	}
}