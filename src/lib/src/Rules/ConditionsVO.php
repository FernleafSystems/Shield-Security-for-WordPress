<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

/**
 * @property string|Base|callable|ConditionsVO[] $conditions
 * @property array                               $params
 * @property bool                                $is_callable
 * @property bool                                $is_single
 * @property bool                                $is_group
 * @property string                              $type
 * @property string                              $logic
 */
class ConditionsVO extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'params':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			case 'conditions':
				if ( \is_array( $value ) ) {
					$value = \array_map( function ( array $sub ) {
						return ( new ConditionsVO() )->applyFromArray( $sub );
					}, $value );
				}
				break;
			case 'type':
				$conditions = $this->conditions;
				$value = \is_array( $conditions ) ? 'group' : ( \is_callable( $conditions ) ? 'callable' : 'single' );
				break;
			case 'is_group':
				$value = $this->type === 'group';
				break;
			case 'is_callable':
				$value = $this->type === 'callable';
				break;
			case 'is_single':
				$value = $this->type === 'single';
				break;
			case 'logic':
				if ( $this->type === 'single' ) {
					if ( !\in_array( $value, [ EnumLogic::LOGIC_ASIS, EnumLogic::LOGIC_INVERT ] ) ) {
						$value = EnumLogic::LOGIC_ASIS;
					}
				}
				elseif ( !\in_array( $value, [ EnumLogic::LOGIC_AND, EnumLogic::LOGIC_OR ] ) ) {
					$value = EnumLogic::LOGIC_AND;
				}
				break;
			default:
				break;
		}
		return $value;
	}
}