<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string   $slug
 * @property string   $name
 * @property string[] $flags
 * @property string[] $conditions
 * @property array[]  $responses
 */
class RuleVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'flags':
			case 'triggers':
			case 'responses':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				$this->{$key} = $value;
				break;
			default:
				break;
		}
		return $value;
	}
}