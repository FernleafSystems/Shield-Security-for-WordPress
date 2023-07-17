<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string $slug
 * @property array  $added
 * @property array  $removed
 * @property array  $changed
 * @property bool   $has_diffs
 */
class DiffVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'added':
			case 'removed':
			case 'changed':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			case 'has_diffs':
				$value = \count( $this->added ) + \count( $this->removed ) + \count( $this->changed ) > 0;
				break;
			default:
				break;
		}
		return $value;
	}
}