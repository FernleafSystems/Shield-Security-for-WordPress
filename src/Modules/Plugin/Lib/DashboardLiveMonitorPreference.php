<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class DashboardLiveMonitorPreference {

	use PluginControllerConsumer;

	public const FLAG_KEY = 'dashboard_live_monitor_collapsed';

	public function isCollapsed() :bool {
		$flags = $this->getFlags();
		return !empty( $flags[ self::FLAG_KEY ] );
	}

	public function setCollapsed( bool $isCollapsed ) :void {
		$meta = self::con()->user_metas->current();
		if ( empty( $meta ) ) {
			return;
		}

		$flags = $this->getFlags();
		if ( $isCollapsed ) {
			$flags[ self::FLAG_KEY ] = Services::Request()->ts();
		}
		else {
			unset( $flags[ self::FLAG_KEY ] );
		}
		$meta->flags = $flags;
	}

	private function getFlags() :array {
		$meta = self::con()->user_metas->current();
		$flags = !empty( $meta ) && \is_array( $meta->flags ) ? $meta->flags : [];
		return \array_filter( $flags, fn( $flag ) => !empty( $flag ) );
	}
}
