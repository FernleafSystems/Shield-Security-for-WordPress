<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class HandleUpgrade {

	use Modules\PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		$previous = self::con()->cfg->previous_version;
		return !empty( $previous );
	}

	protected function run() {
		$con = self::con();
		$prev = $con->cfg->previous_version;

		$hook = $con->prefix( 'plugin-upgrade' );
		if ( \version_compare( $prev, $con->cfg->version(), '<' ) && !wp_next_scheduled( $hook, [ $prev ] ) ) {
			$con->plugin->deleteAllPluginCrons();
			Services::ServiceProviders()->clearProviders();
			wp_schedule_single_event( Services::Request()->ts() + 1, $hook, [ $prev ] );
		}

		add_action( $hook, function ( $previousVersion ) {
			$con = self::con();

			Services::ServiceProviders()->clearProviders();
			$con->plugin->deleteAllPluginCrons();
			$this->clearCaches();

			if ( $con->extensions_controller->canRunExtensions() ) {
				foreach ( $con->extensions_controller->getAvailableExtensions() as $availableExtension ) {
					$handler = $availableExtension->getUpgradesHandler();
					if ( !empty( $handler ) && \method_exists( $handler, 'forceUpdateCheck' ) ) {
						$handler->forceUpdateCheck();
					}
				}
			}
		} );

		$con->cfg->previous_version = $con->cfg->version();
	}

	public function clearCaches() {
		\array_map(
			function ( $func ) {
				\function_exists( $func ) && call_user_func( $func );
			},
			[
				'wpfc_clear_all_cache', // WP Fastest Cache
				'rocket_clean_domain', // WP Rocket
				'w3tc_pgcache_flush', // W3 Total Cache
			]
		);
		if ( \function_exists( 'wp_cache_clean_cache' ) ) {
			// WP Super Cache
			global $file_prefix;
			wp_cache_clean_cache( $file_prefix, true );
		}
		if ( \class_exists( '\LiteSpeed\Purge' ) && \method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
		}
		if ( \class_exists( '\WP_Optimize' ) && \method_exists( '\WP_Optimize', 'get_page_cache' ) ) {
			$wpOptimisePageCache = \WP_Optimize()->get_page_cache();
			if ( \method_exists( $wpOptimisePageCache, 'purge' ) ) {
				$wpOptimisePageCache->purge();
			}
		}
	}

	protected function upgradeModule() {
		/*
		$upgrades = self::con()->cfg->version_upgrades;
		\asort( $upgrades );
		foreach ( $upgrades as $version ) {
			$upgradeMethod = 'upgrade_'.\str_replace( '.', '', $version );
			if ( \version_compare( $this->previous, $version, '<' ) && \method_exists( $this, $upgradeMethod ) ) {
				$this->{$upgradeMethod}();
			}
		}
		*/
	}
}