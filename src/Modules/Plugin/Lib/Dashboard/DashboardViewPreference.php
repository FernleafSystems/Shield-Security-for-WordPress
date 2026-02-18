<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Dashboard;

class DashboardViewPreference {

	public const META_KEY = 'shield_dashboard_view';
	public const VIEW_SIMPLE = 'simple';
	public const VIEW_ADVANCED = 'advanced';

	public function getCurrent() :string {
		$userId = (int)get_current_user_id();
		if ( $userId <= 0 ) {
			return self::VIEW_SIMPLE;
		}

		return $this->sanitize( (string)get_user_meta( $userId, self::META_KEY, true ) );
	}

	public function getToggleTarget() :string {
		return $this->getCurrent() === self::VIEW_ADVANCED ? self::VIEW_SIMPLE : self::VIEW_ADVANCED;
	}

	public function setCurrent( string $view ) :void {
		$userId = (int)get_current_user_id();
		if ( $userId > 0 ) {
			update_user_meta( $userId, self::META_KEY, $this->sanitize( $view ) );
		}
	}

	public function sanitize( string $view ) :string {
		$view = \strtolower( \trim( $view ) );
		return \in_array( $view, [ self::VIEW_SIMPLE, self::VIEW_ADVANCED ], true ) ? $view : self::VIEW_SIMPLE;
	}
}

