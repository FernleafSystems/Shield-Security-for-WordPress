<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;

/**
 * @property string|BaseAction $action
 * @property array             $aux
 * @property bool              $is_ajax
 * @property bool              $ip_in_nonce
 * @property bool              $unique
 * @property array             $excluded_fields
 */
class ActionDataVO extends DynPropertiesClass {

	/**
	 * @throws \Exception
	 */
	public function __get( string $key ) {
		$val = parent::__get( $key );

		switch ( $key ) {

			case 'action_class':
				if ( empty( $val ) || !@\class_exists( $val ) ) {
					throw new \Exception( '$action_class is empty!' );
				}
				break;
			case 'aux':
			case 'excluded_fields':
				if ( !\is_array( $val ) ) {
					$val = [];
				}
				break;
			case 'is_ajax':
			case 'ip_in_nonce':
				if ( !\is_bool( $val ) ) {
					$val = true;
				}
				break;
			case 'unique':
				if ( !\is_bool( $val ) ) {
					$val = false;
				}
				break;

			default:
				break;
		}

		return $val;
	}
}