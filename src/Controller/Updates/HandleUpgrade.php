<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\OptionsCorrections;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class HandleUpgrade {

	use Modules\PluginControllerConsumer;
	use ExecOnce;

	protected const CACHE_PURGE_FUNCTIONS = [
		'wpfc_clear_all_cache', // WP Fastest Cache
		'rocket_clean_domain', // WP Rocket
		'w3tc_pgcache_flush', // W3 Total Cache
	];

	protected function canRun() :bool {
		$previous = self::con()->cfg->previous_version;
		return !empty( $previous );
	}

	protected function run() {
		$con = self::con();
		$prev = $con->cfg->previous_version;

		$hook = $con->prefix( 'plugin-upgrade' );
		add_action( $hook, fn() => $this->runScheduledUpgrade(), 10, 0 );
		if ( \version_compare( $prev, $con->cfg->version(), '<' ) && !wp_next_scheduled( $hook, [ $prev ] ) ) {
			wp_schedule_single_event( Services::Request()->ts() + 1, $hook, [ $prev ] );
		}

		$con->cfg->previous_version = $con->cfg->version();
		$con->cfg->persist_required = true;
	}

	private function runScheduledUpgrade() :void {
		$con = self::con();

		( new OptionsCorrections() )->runUpgradeMigrations();
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}

		Services::ServiceProviders()->clearProviders();
		$con->plugin->deleteAllPluginCrons();
		$this->clearCaches();

		if ( $con->extensions_controller->canRunExtensions() ) {
			foreach ( $con->extensions_controller->getAvailableExtensions() as $availableExtension ) {
				$this->runUpgradeSideEffect( 'extension upgrade check', function () use ( $availableExtension ) {
					$handler = $availableExtension->getUpgradesHandler();
					if ( !empty( $handler ) && \method_exists( $handler, 'forceUpdateCheck' ) ) {
						$handler->forceUpdateCheck();
					}
				} );
			}
		}

		$result = $con->comps->scans->startNewScans( \array_values( \array_filter(
			$con->comps->scans->getAllScanCons(),
			static fn( $scanCon ) :bool => $scanCon->isReady()
		) ) );
		if ( $result->hasFailures() ) {
			error_log( $result->getFailureLogMessage() );
		}
	}

	public function clearCaches() :void {
		foreach ( static::CACHE_PURGE_FUNCTIONS as $function ) {
			if ( \function_exists( $function ) ) {
				$this->runUpgradeSideEffect(
					sprintf( 'cache purge %s', $function ),
					fn() => \call_user_func( $function )
				);
			}
		}
		if ( \function_exists( 'wp_cache_clean_cache' ) ) {
			// WP Super Cache
			global $file_prefix;
			$this->runUpgradeSideEffect( 'cache purge wp_cache_clean_cache',
				fn() => wp_cache_clean_cache( $file_prefix, true )
			);
		}
		// @phpstan-ignore-next-line
		if ( \class_exists( '\LiteSpeed\Purge' ) && \method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			$this->runUpgradeSideEffect( 'cache purge LiteSpeed\Purge::purge_all',
				fn() => \LiteSpeed\Purge::purge_all()
			);
		}
		// @phpstan-ignore-next-line
		if ( \class_exists( '\WP_Optimize' ) && \method_exists( '\WP_Optimize', 'get_page_cache' ) ) {
			$this->runUpgradeSideEffect( 'cache purge WP_Optimize', function () {
				$wpOptimisePageCache = \WP_Optimize()->get_page_cache();
				if ( \method_exists( $wpOptimisePageCache, 'purge' ) ) {
					$wpOptimisePageCache->purge();
				}
			} );
		}
	}

	private function runUpgradeSideEffect( string $context, callable $callback ) :void {
		try {
			$callback();
		}
		catch ( \Throwable $e ) {
			error_log( sprintf(
				'Shield upgrade side effect failed: %s: %s',
				$context,
				$e->getMessage()
			) );
		}
	}
}
