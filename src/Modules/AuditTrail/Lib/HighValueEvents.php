<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class HighValueEvents {

	use PluginControllerConsumer;

	/**
	 * @return string[]
	 */
	public function forDashboardTicker() :array {
		$slugs = [
			'plugin_upgraded',
			'plugin_activated',
			'plugin_deactivated',
			'plugin_installed',
			'plugin_uninstalled',
			'theme_upgraded',
			'theme_activated',
			'theme_installed',
			'theme_uninstalled',
			'core_updated',
			'login_block',
			'ip_offense',
			'ip_blocked',
			'ip_block_auto',
			'firewall_block',
			'conn_kill',
			'conn_kill_crowdsec',
			'options_imported',
			'options_exported',
		];

		return \array_values( \array_filter(
			\array_unique( $slugs ),
			fn( string $slug ) => self::con()->comps->events->eventExists( $slug )
		) );
	}
}
