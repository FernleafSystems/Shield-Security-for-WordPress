<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

class AdminPluginVisibility {

	public function snapshot( ?array $finalPluginsList = null ) :AdminPluginVisibilitySnapshot {
		$this->ensurePluginApiLoaded();

		$wpPlugins = \function_exists( 'get_plugins' ) ? \get_plugins() : [];
		$adminAllPlugins = $this->normalizePluginMap( $this->applyFilter( 'all_plugins', $wpPlugins ) );

		$wpMuPlugins = \function_exists( 'get_mu_plugins' ) ? \get_mu_plugins() : [];
		$showMuPlugins = (bool)$this->applyFilter( 'show_advanced_plugins', true, 'mustuse' );
		$adminMuPlugins = $showMuPlugins ? $this->normalizePluginMap( $wpMuPlugins ) : [];

		$activePlugins = $this->activePlugins();
		$networkActivePlugins = $this->networkActivePlugins();

		return new AdminPluginVisibilitySnapshot(
			$this->normalizePluginMap( $wpPlugins ),
			$adminAllPlugins,
			$this->normalizePluginMap( $wpMuPlugins ),
			$showMuPlugins,
			$adminMuPlugins,
			$finalPluginsList ?? $this->filteredPluginsList( $adminAllPlugins, $adminMuPlugins, $activePlugins, $networkActivePlugins ),
			$activePlugins,
			$networkActivePlugins
		);
	}

	private function ensurePluginApiLoaded() :void {
		if ( !\function_exists( 'get_plugins' ) && \defined( 'ABSPATH' ) ) {
			$pluginApi = \rtrim( \str_replace( '\\', '/', ABSPATH ), '/' ).'/wp-admin/includes/plugin.php';
			if ( \is_file( $pluginApi ) ) {
				require_once $pluginApi;
			}
		}
	}

	private function applyFilter( string $hook, $value, ...$args ) {
		return \function_exists( 'apply_filters' ) ? \apply_filters( $hook, $value, ...$args ) : $value;
	}

	private function normalizePluginMap( $plugins ) :array {
		return \is_array( $plugins ) ? $plugins : [];
	}

	private function filteredPluginsList( array $adminAllPlugins, array $adminMuPlugins, array $activePlugins, array $networkActivePlugins ) :array {
		$plugins = [
			'all'                  => $adminAllPlugins,
			'search'               => [],
			'active'               => [],
			'inactive'             => [],
			'recently_activated'   => [],
			'upgrade'              => [],
			'mustuse'              => $adminMuPlugins,
			'dropins'              => [],
			'paused'               => [],
			'auto-update-enabled'  => [],
			'auto-update-disabled' => [],
		];

		foreach ( $adminAllPlugins as $file => $pluginData ) {
			$group = \in_array( $file, $activePlugins, true ) || \in_array( $file, $networkActivePlugins, true )
				? 'active'
				: 'inactive';
			$plugins[ $group ][ $file ] = $pluginData;
		}

		$filtered = $this->applyFilter( 'plugins_list', $plugins );
		return \is_array( $filtered ) ? $filtered : $plugins;
	}

	/**
	 * @return list<string>
	 */
	private function activePlugins() :array {
		$active = \function_exists( 'get_option' ) ? \get_option( 'active_plugins', [] ) : [];
		return \array_values( \array_filter( \is_array( $active ) ? $active : [], '\is_string' ) );
	}

	/**
	 * @return list<string>
	 */
	private function networkActivePlugins() :array {
		$active = \function_exists( 'get_site_option' ) ? \get_site_option( 'active_sitewide_plugins', [] ) : [];
		return \array_values( \array_filter( \array_keys( \is_array( $active ) ? $active : [] ), '\is_string' ) );
	}
}
