<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class OperatorModePreference {

	public const META_KEY_DEFAULT_MODE = 'shield_default_operator_mode';

	public function getCurrent() :string {
		$userId = (int)get_current_user_id();
		if ( $userId <= 0 ) {
			return '';
		}

		return $this->sanitize( (string)get_user_meta( $userId, self::META_KEY_DEFAULT_MODE, true ) );
	}

	public function setCurrent( string $mode ) :void {
		$userId = (int)get_current_user_id();
		if ( $userId > 0 ) {
			update_user_meta( $userId, self::META_KEY_DEFAULT_MODE, $this->sanitize( $mode ) );
		}
	}

	public function sanitize( string $mode ) :string {
		$mode = \strtolower( \trim( $mode ) );
		return \in_array( $mode, PluginNavs::allOperatorModes(), true ) ? $mode : '';
	}
}
