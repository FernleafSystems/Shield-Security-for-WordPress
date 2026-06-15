<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

enum HiddenReason :string {
	case WpDiscoveryCacheGap = 'wp_discovery_cache_gap';
	case AllPlugins = 'all_plugins';
	case ShowAdvancedPlugins = 'show_advanced_plugins';
	case PluginsList = 'plugins_list';

	public function label() :string {
		return match ( $this ) {
			self::WpDiscoveryCacheGap => __( 'Missing From WordPress Discovery Cache', 'wp-simple-firewall' ),
			self::AllPlugins          => __( 'Removed By all_plugins Filter', 'wp-simple-firewall' ),
			self::ShowAdvancedPlugins => __( 'Must-Use Plugins Hidden', 'wp-simple-firewall' ),
			self::PluginsList         => __( 'Removed From Final Plugins List', 'wp-simple-firewall' ),
		};
	}
}
