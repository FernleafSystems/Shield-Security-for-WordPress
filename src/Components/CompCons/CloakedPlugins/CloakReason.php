<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

final class CloakReason {

	public const WpDiscoveryCacheGap = 'wp_discovery_cache_gap';

	public const AllPlugins = 'all_plugins';

	public const ShowAdvancedPlugins = 'show_advanced_plugins';

	public const PluginsList = 'plugins_list';

	public const ALL = [
		self::WpDiscoveryCacheGap,
		self::AllPlugins,
		self::ShowAdvancedPlugins,
		self::PluginsList,
	];

	/**
	 * @phpstan-param value-of<self::ALL> $reason
	 */
	public static function label( string $reason ) :string {
		self::assertValid( $reason );

		switch ( $reason ) {
			case self::WpDiscoveryCacheGap:
				return __( 'Missing from WordPress discovery results', 'wp-simple-firewall' );
			case self::AllPlugins:
				return __( 'Removed by the all_plugins filter', 'wp-simple-firewall' );
			case self::ShowAdvancedPlugins:
				return __( 'The Must-Use plugins view is disabled', 'wp-simple-firewall' );
			case self::PluginsList:
				return __( 'Removed from the final Plugins page list', 'wp-simple-firewall' );
		}
	}

	/**
	 * @phpstan-assert value-of<self::ALL> $reason
	 */
	public static function assertValid( string $reason ) :void {
		if ( !\in_array( $reason, self::ALL, true ) ) {
			throw new \InvalidArgumentException( \sprintf( 'Unsupported cloaked plugin reason: %s', $reason ) );
		}
	}
}
