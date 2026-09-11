<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\OptionsCorrections;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
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
		$importExport = new ImportExportController();

		( new OptionsCorrections() )->runUpgradeMigrations();
		$this->runUpgradeSideEffect( 'import/export site registry legacy import', function () use ( $importExport ) {
			$importExport->ensureSitesRegistryImported();
		} );
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
		$this->runUpgradeSideEffect( 'MALai status column alignment', fn() => $this->alignMalaiStatusColumnWidth() );
		$this->runUpgradeSideEffect( 'scan metadata column alignment', fn() => $this->alignScansMetaColumnWidth() );

		Services::ServiceProviders()->clearProviders();
		$con->plugin->deleteAllPluginCrons();
		$this->runUpgradeSideEffect(
			'asset coordinator wakeup reconciliation',
			fn() => $con->comps->asset_coordinator->reconcileWakeup()
		);
		$this->runUpgradeSideEffect( 'import/export sites queue schedule', function () use ( $importExport ) {
			$importExport->scheduleQueueSoonIfSyncEnabled();
		} );
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
			$message = $result->getFailureLogMessage( [ Modules\HackGuard\Scan\StartScansResult::REASON_ALREADY_EXISTS ] );
			if ( $message !== '' ) {
				error_log( $message );
			}
		}
	}

	private function alignMalaiStatusColumnWidth() :void {
		$schema = self::con()->db_con->malware->getTableSchema();
		$targetLength = (int)( $schema->getColumnDef( 'malai_status' )[ 'length' ] ?? 0 );
		$targetDefinition = $schema->enumerateColumns()[ 'malai_status' ] ?? '';
		$columns = Services::WpDb()->selectCustom( sprintf(
			"SHOW COLUMNS FROM `%s` WHERE `Field`='malai_status';",
			$schema->table
		) );
		$actualType = \is_array( $columns ) ? (string)( $columns[ 0 ][ 'Type' ] ?? '' ) : '';
		if ( $targetLength > 0
			 && $targetDefinition !== ''
			 && \preg_match( '/^varchar\((\d+)\)$/i', $actualType, $matches )
			 && (int)$matches[ 1 ] < $targetLength ) {
			if ( Services::WpDb()->doSql( sprintf(
				'ALTER TABLE `%s` MODIFY COLUMN `malai_status` %s;',
				$schema->table,
				$targetDefinition
			) ) === false ) {
				throw new \RuntimeException( 'Could not widen the MALai status column.' );
			}
		}
	}

	private function alignScansMetaColumnWidth() :void {
		$schema = self::con()->db_con->scans->getTableSchema();
		$targetDefinition = \trim( (string)( $schema->enumerateColumns()[ 'meta' ] ?? '' ) );
		if ( !\preg_match( '/^mediumtext\b/i', $targetDefinition ) ) {
			throw new \RuntimeException( 'The configured scan metadata column definition is not mediumtext.' );
		}

		global $wpdb;
		$columns = Services::WpDb()->selectCustom( \sprintf(
			"SHOW COLUMNS FROM `%s` WHERE `Field`='meta';",
			$schema->table
		) );
		if ( !\is_array( $columns )
			 || ( \is_object( $wpdb ) && (string)( $wpdb->last_error ?? '' ) !== '' )
			 || !isset( $columns[ 0 ][ 'Type' ] ) ) {
			throw new \RuntimeException( 'Could not inspect the scan metadata column.' );
		}

		$actualType = \strtolower( \trim( (string)$columns[ 0 ][ 'Type' ] ) );
		if ( \in_array( $actualType, [ 'mediumtext', 'longtext' ], true ) ) {
			return;
		}
		if ( !\in_array( $actualType, [ 'tinytext', 'text' ], true ) ) {
			throw new \RuntimeException( 'The scan metadata column has an unexpected type.' );
		}

		if ( Services::WpDb()->doSql( \sprintf(
			'ALTER TABLE `%s` MODIFY COLUMN `meta` %s;',
			$schema->table,
			$targetDefinition
		) ) === false ) {
			throw new \RuntimeException( 'Could not widen the scan metadata column.' );
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
