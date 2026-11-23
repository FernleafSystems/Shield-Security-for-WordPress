<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * The dashboard Live Monitor starts closed and is opened from the sidebar; whether a user left it
 * open is remembered. Only the "open" choice is stored, so closed is the default for everyone.
 */
class DashboardLiveMonitorPreference {

	use PluginControllerConsumer;

	public const FLAG_KEY = 'dashboard_live_monitor_open';
	/** Stored by earlier versions, when the monitor was open by default. */
	private const LEGACY_COLLAPSED_FLAG_KEY = 'dashboard_live_monitor_collapsed';

	public function isCollapsed() :bool {
		$flags = $this->getFlags();
		return empty( $flags[ self::FLAG_KEY ] );
	}

	public function setCollapsed( bool $isCollapsed ) :void {
		$meta = self::con()->user_metas->current();
		if ( empty( $meta ) ) {
			return;
		}

		$flags = $this->getFlags();
		unset( $flags[ self::LEGACY_COLLAPSED_FLAG_KEY ] );
		if ( $isCollapsed ) {
			unset( $flags[ self::FLAG_KEY ] );
		}
		else {
			$flags[ self::FLAG_KEY ] = Services::Request()->ts();
		}
		$meta->flags = $flags;
	}

	private function getFlags() :array {
		$meta = self::con()->user_metas->current();
		$flags = !empty( $meta ) && \is_array( $meta->flags ) ? $meta->flags : [];
		return \array_filter( $flags, fn( $flag ) => !empty( $flag ) );
	}
}
