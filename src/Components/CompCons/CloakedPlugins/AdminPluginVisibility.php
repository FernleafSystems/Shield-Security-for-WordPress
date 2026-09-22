<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

class AdminPluginVisibility {

	private ?AdminPluginVisibilitySnapshot $observation = null;

	public function beginPluginsList( $plugins ) {
		$this->observation = $this->snapshot();
		$this->observation->wpDiscoveredPlugins = \is_array( $plugins ) ? $plugins : null;
		$this->observation->adminAllPlugins = null;
		$this->observation->showMustUsePlugins = null;
		$this->observation->isPageObservation = true;
		return $plugins;
	}

	public function observeAllPlugins( $plugins ) {
		if ( $this->observation !== null ) {
			$this->observation->adminAllPlugins = \is_array( $plugins ) ? $plugins : null;
		}
		return $plugins;
	}

	public function observeAdvancedPlugins( $show, string $type ) {
		if ( $type === 'mustuse' && $this->observation !== null ) {
			$this->observation->showMustUsePlugins = \is_bool( $show ) ? $show : null;
		}
		return $show;
	}

	public function finishPluginsList( $plugins ) :?AdminPluginVisibilitySnapshot {
		$observation = $this->observation;
		$this->observation = null;
		if ( $observation !== null ) {
			$observation->finalPluginsList = \is_array( $plugins ) ? $plugins : null;
		}
		return $observation;
	}

	public function snapshot() :AdminPluginVisibilitySnapshot {
		if ( !\function_exists( 'get_plugins' ) ) {
			require_once ABSPATH.'wp-admin/includes/plugin.php';
		}
		$plugins = \get_plugins();
		$plugins = \is_array( $plugins ) ? $plugins : null;
		$muPlugins = \get_mu_plugins();
		$active = \get_option( 'active_plugins', [] );
		$networkActive = \get_site_option( 'active_sitewide_plugins', [] );

		return new AdminPluginVisibilitySnapshot(
			$plugins,
			$plugins,
			$muPlugins,
			true,
			null,
			\array_values( \array_filter( \is_array( $active ) ? $active : [], '\is_string' ) ),
			\array_values( \array_filter( \array_keys( \is_array( $networkActive ) ? $networkActive : [] ), '\is_string' ) )
		);
	}
}
