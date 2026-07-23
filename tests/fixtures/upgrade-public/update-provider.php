<?php declare( strict_types=1 );

/**
 * Plugin Name: Shield Upgrade Test Update Provider
 * Description: Test-only update metadata provider for the public-to-current Shield upgrade lane.
 */

if ( !\function_exists( 'shield_upgrade_test_update_config_path' ) ) {
	function shield_upgrade_test_update_config_path() :string {
		return \defined( 'WP_CONTENT_DIR' )
			? WP_CONTENT_DIR.'/shield-upgrade-test/update.json'
			: '';
	}
}

if ( !\function_exists( 'shield_upgrade_test_read_update_config' ) ) {
	/**
	 * @return array<string,mixed>
	 */
	function shield_upgrade_test_read_update_config() :array {
		$path = shield_upgrade_test_update_config_path();
		if ( $path === '' || !\is_file( $path ) ) {
			return [];
		}

		$decoded = \json_decode( (string)\file_get_contents( $path ), true );
		return \is_array( $decoded ) ? $decoded : [];
	}
}

if ( !\function_exists( 'shield_upgrade_test_build_update_offer' ) ) {
	/**
	 * @param array<string,mixed> $config
	 */
	function shield_upgrade_test_build_update_offer( array $config ) :?\stdClass {
		foreach ( [ 'plugin', 'slug', 'new_version', 'package', 'url' ] as $key ) {
			if ( !\is_string( $config[ $key ] ?? null ) || \trim( (string)$config[ $key ] ) === '' ) {
				return null;
			}
		}

		return (object)[
			'id'          => (string)( $config[ 'id' ] ?? $config[ 'slug' ] ),
			'slug'        => (string)$config[ 'slug' ],
			'plugin'      => (string)$config[ 'plugin' ],
			'new_version' => (string)$config[ 'new_version' ],
			'url'         => (string)$config[ 'url' ],
			'package'     => (string)$config[ 'package' ],
		];
	}
}

if ( !\function_exists( 'shield_upgrade_test_apply_update_metadata' ) ) {
	function shield_upgrade_test_apply_update_metadata( $transient, ?array $config = null ) {
		$offer = shield_upgrade_test_build_update_offer( $config ?? shield_upgrade_test_read_update_config() );
		if ( $offer === null ) {
			return $transient;
		}

		if ( !\is_object( $transient ) ) {
			$transient = new \stdClass();
		}
		if ( !isset( $transient->response ) || !\is_array( $transient->response ) ) {
			$transient->response = [];
		}
		if ( !isset( $transient->checked ) || !\is_array( $transient->checked ) ) {
			$transient->checked = [];
		}
		if ( isset( $transient->no_update ) && \is_array( $transient->no_update ) ) {
			unset( $transient->no_update[ $offer->plugin ] );
		}

		$transient->last_checked = \time();
		$transient->response[ $offer->plugin ] = $offer;

		return $transient;
	}
}

if ( !\function_exists( 'shield_upgrade_test_plugins_api' ) ) {
	function shield_upgrade_test_plugins_api( $result, string $action, $args ) {
		$offer = shield_upgrade_test_build_update_offer( shield_upgrade_test_read_update_config() );
		if ( $offer === null
			 || $action !== 'plugin_information'
			 || !\is_object( $args )
			 || (string)( $args->slug ?? '' ) !== $offer->slug ) {
			return $result;
		}

		return (object)[
			'name'          => 'Shield Security',
			'slug'          => $offer->slug,
			'version'       => $offer->new_version,
			'download_link' => $offer->package,
			'sections'      => [
				'description' => 'Shield upgrade test package.',
			],
		];
	}
}

if ( !\function_exists( 'shield_upgrade_test_allow_package_host' ) ) {
	/**
	 * @param array<string,mixed>|null $config
	 */
	function shield_upgrade_test_allow_package_host( bool $isExternal, string $host, string $url, ?array $config = null ) :bool {
		$config = $config ?? shield_upgrade_test_read_update_config();
		$packageHost = \is_string( $config[ 'package' ] ?? null ) ? \parse_url( $config[ 'package' ], PHP_URL_HOST ) : null;

		return \is_string( $packageHost ) && $packageHost !== '' && $host === $packageHost ? true : $isExternal;
	}
}

if ( !\function_exists( 'shield_upgrade_test_contract_report' ) ) {
	function shield_upgrade_test_contract_report() :array {
		$report = \get_option( 'shield_upgrade_test_contract_report', [] );
		return \is_array( $report ) ? $report : [];
	}
}

if ( !\function_exists( 'shield_upgrade_test_store_contract_report' ) ) {
	function shield_upgrade_test_store_contract_report( array $changes ) :void {
		\update_option(
			'shield_upgrade_test_contract_report',
			\array_replace_recursive( shield_upgrade_test_contract_report(), $changes ),
			false
		);
	}
}

if ( !\function_exists( 'shield_upgrade_test_is_shield_upgrade' ) ) {
	function shield_upgrade_test_is_shield_upgrade( $hookExtra, ?array $config = null ) :bool {
		if ( !\is_array( $hookExtra ) ) {
			return false;
		}
		$config = $config ?? shield_upgrade_test_read_update_config();
		$target = \is_string( $config[ 'plugin' ] ?? null ) ? (string)$config[ 'plugin' ] : '';
		if ( $target === '' ) {
			return false;
		}
		$plugins = \is_array( $hookExtra[ 'plugins' ] ?? null ) ? $hookExtra[ 'plugins' ] : [];
		return (string)( $hookExtra[ 'plugin' ] ?? '' ) === $target
			   || \in_array( $target, $plugins, true );
	}
}

if ( !\function_exists( 'shield_upgrade_test_contract_classes' ) ) {
	function shield_upgrade_test_contract_classes() :array {
		$root = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\';
		return [
			'callback_owners' => [
				'afs'                => $root.'Modules\\HackGuard\\Scan\\Controller\\Afs',
				'cleanup'            => $root.'Modules\\HackGuard\\Scan\\AssetChange\\Cleanup',
				'schedule_build_all' => $root.'Modules\\HackGuard\\Lib\\Snapshots\\StoreAction\\ScheduleBuildAll',
				'wpv'                => $root.'Modules\\HackGuard\\Scan\\Controller\\Wpv',
				'scan_base'          => $root.'Modules\\HackGuard\\Scan\\Controller\\Base',
				'audit_plugins'      => $root.'Modules\\AuditTrail\\Auditors\\Plugins',
				'audit_themes'       => $root.'Modules\\AuditTrail\\Auditors\\Themes',
				'audit_wordpress'    => $root.'Modules\\AuditTrail\\Auditors\\Wordpress',
				'capture_my_upgrade' => $root.'Controller\\Updates\\CaptureMyUpgrade',
			],
			'lazy_dependencies' => [
				'find_assets_to_snap' => $root.'Modules\\HackGuard\\Lib\\Snapshots\\FindAssetsToSnap',
				'store_base_action'   => $root.'Modules\\HackGuard\\Lib\\Snapshots\\StoreAction\\BaseAction',
				'store_build'         => $root.'Modules\\HackGuard\\Lib\\Snapshots\\StoreAction\\Build',
				'store_load'          => $root.'Modules\\HackGuard\\Lib\\Snapshots\\StoreAction\\Load',
				'snapshot_store'      => $root.'Modules\\HackGuard\\Lib\\Snapshots\\Store',
				'submit_hashes'       => $root.'Modules\\HackGuard\\Lib\\Snapshots\\CrowdSourced\\SubmitHashes',
				'audit_ops_build'     => $root.'Modules\\AuditTrail\\Lib\\Snapshots\\Ops\\Build',
				'audit_ops_delete'    => $root.'Modules\\AuditTrail\\Lib\\Snapshots\\Ops\\Delete',
				'audit_ops_store'     => $root.'Modules\\AuditTrail\\Lib\\Snapshots\\Ops\\Store',
				'audit_con'           => $root.'Modules\\AuditTrail\\Lib\\AuditCon',
				'events_service'      => $root.'Events\\EventsService',
			],
		];
	}
}

if ( !\function_exists( 'shield_upgrade_test_class_loaded_map' ) ) {
	function shield_upgrade_test_class_loaded_map( array $classes, bool $autoload = false ) :array {
		return \array_map(
			static fn( string $class ) :bool => \class_exists( $class, $autoload ),
			$classes
		);
	}
}

if ( !\function_exists( 'shield_upgrade_test_scheduled_instances' ) ) {
	function shield_upgrade_test_scheduled_instances( string $hook ) :array {
		$instances = [];
		foreach ( (array)\_get_cron_array() as $timestamp => $hooks ) {
			foreach ( (array)( $hooks[ $hook ] ?? [] ) as $instance ) {
				$instances[] = [
					'timestamp' => (int)$timestamp,
					'args'      => \is_array( $instance[ 'args' ] ?? null ) ? $instance[ 'args' ] : [],
				];
			}
		}
		return $instances;
	}
}

if ( !\function_exists( 'shield_upgrade_test_seed_legacy_crons' ) ) {
	function shield_upgrade_test_seed_legacy_crons() :array {
		$seeded = [
			[ 'timestamp' => \time() + 600, 'hook' => 'icwp-wpsf-afs_asset_change_cleanup', 'args' => [ 'plugin', 'wp-simple-firewall/icwp-wpsf.php', 0 ] ],
			[ 'timestamp' => \time() + 601, 'hook' => 'icwp-wpsf-afs_asset_change_cleanup', 'args' => [ 'plugin', 'wp-simple-firewall/icwp-wpsf.php', 1 ] ],
			[ 'timestamp' => \time() + 1800, 'hook' => 'icwp-wpsf-ptg_build_snapshots', 'args' => [] ],
			[ 'timestamp' => \time() + 603, 'hook' => 'icwp-wpsf-ondemand_scan_wpv', 'args' => [] ],
			[ 'timestamp' => \time() + 1500, 'hook' => 'icwp-wpsf-ondemand_scan_wpv', 'args' => [] ],
		];
		foreach ( $seeded as &$event ) {
			$event[ 'scheduled' ] = \wp_schedule_single_event( $event[ 'timestamp' ], $event[ 'hook' ], $event[ 'args' ] );
		}
		unset( $event );
		return $seeded;
	}
}

if ( !\function_exists( 'shield_upgrade_test_capture_early_translation_trace' ) ) {
	function shield_upgrade_test_capture_early_translation_trace( string $functionName, string $message, string $version ) :void {
		if ( $functionName !== '_load_textdomain_just_in_time' || \strpos( $message, 'wp-simple-firewall' ) === false ) {
			return;
		}
		shield_upgrade_test_store_contract_report( [
			'early_translation' => [
				'function' => $functionName,
				'version'  => $version,
			],
		] );
	}
}

if ( !\function_exists( 'shield_upgrade_test_capture_pre_replace_contract' ) ) {
	function shield_upgrade_test_capture_pre_replace_contract( $response, $hookExtra ) {
		if ( !shield_upgrade_test_is_shield_upgrade( $hookExtra ) ) {
			return $response;
		}
		$classes = shield_upgrade_test_contract_classes();
		$owners = shield_upgrade_test_class_loaded_map( $classes[ 'callback_owners' ] );
		$lazy = shield_upgrade_test_class_loaded_map( $classes[ 'lazy_dependencies' ] );
		shield_upgrade_test_store_contract_report( [
			'pre_replace' => [
				'captured'                    => true,
				'is_shield_upgrade'            => true,
				'callback_owners_loaded'       => $owners,
				'unresolved_lazy_dependencies' => \array_keys( \array_filter(
					$lazy,
					static fn( bool $loaded ) :bool => !$loaded
				) ),
				'seeded_legacy_crons'          => shield_upgrade_test_seed_legacy_crons(),
			],
		] );
		return $response;
	}
}

if ( !\function_exists( 'shield_upgrade_test_capture_post_replace_contract' ) ) {
	function shield_upgrade_test_capture_post_replace_contract( $response, $hookExtra, $result ) {
		unset( $result );
		if ( !shield_upgrade_test_is_shield_upgrade( $hookExtra ) ) {
			return $response;
		}
		$pluginRoot = WP_PLUGIN_DIR.'/wp-simple-firewall/';
		shield_upgrade_test_store_contract_report( [
			'post_replace' => [
				'captured'                       => true,
				'is_shield_upgrade'               => true,
				'retained_executor_files_present' => [
					'cleanup'            => \is_file( $pluginRoot.'src/Modules/HackGuard/Scan/AssetChange/Cleanup.php' ),
					'schedule_build_all' => \is_file( $pluginRoot.'src/Modules/HackGuard/Lib/Snapshots/StoreAction/ScheduleBuildAll.php' ),
				],
			],
		] );
		return $response;
	}
}

if ( !\function_exists( 'shield_upgrade_test_validate_lazy_contracts' ) ) {
	function shield_upgrade_test_validate_lazy_contracts() :array {
		$classes = shield_upgrade_test_contract_classes();
		$resolved = shield_upgrade_test_class_loaded_map( $classes[ 'lazy_dependencies' ], true );
		$methodContracts = [
			'find_assets_to_snap_run' => [ $classes[ 'lazy_dependencies' ][ 'find_assets_to_snap' ], 'run' ],
			'base_action_set_asset'   => [ $classes[ 'lazy_dependencies' ][ 'store_base_action' ], 'setAsset' ],
			'store_build_run'         => [ $classes[ 'lazy_dependencies' ][ 'store_build' ], 'run' ],
			'store_load_run'          => [ $classes[ 'lazy_dependencies' ][ 'store_load' ], 'run' ],
			'snapshot_store_verify'   => [ $classes[ 'lazy_dependencies' ][ 'snapshot_store' ], 'verify' ],
			'snapshot_store_get_data' => [ $classes[ 'lazy_dependencies' ][ 'snapshot_store' ], 'getSnapData' ],
			'snapshot_store_get_meta' => [ $classes[ 'lazy_dependencies' ][ 'snapshot_store' ], 'getSnapMeta' ],
			'snapshot_store_set_meta' => [ $classes[ 'lazy_dependencies' ][ 'snapshot_store' ], 'setSnapMeta' ],
			'snapshot_store_save_meta'=> [ $classes[ 'lazy_dependencies' ][ 'snapshot_store' ], 'saveMeta' ],
			'submit_hashes_run'       => [ $classes[ 'lazy_dependencies' ][ 'submit_hashes' ], 'run' ],
			'audit_ops_build_run'     => [ $classes[ 'lazy_dependencies' ][ 'audit_ops_build' ], 'run' ],
			'audit_ops_delete'        => [ $classes[ 'lazy_dependencies' ][ 'audit_ops_delete' ], 'delete' ],
			'audit_ops_store'         => [ $classes[ 'lazy_dependencies' ][ 'audit_ops_store' ], 'store' ],
			'audit_update_snapshot'   => [ $classes[ 'lazy_dependencies' ][ 'audit_con' ], 'updateStoredSnapshot' ],
			'events_fire_event'       => [ $classes[ 'lazy_dependencies' ][ 'events_service' ], 'fireEvent' ],
		];
		$methods = [];
		foreach ( $methodContracts as $key => $contract ) {
			$methods[ $key ] = \method_exists( $contract[ 0 ], $contract[ 1 ] );
		}
		return [
			'lazy_dependencies_resolved' => $resolved,
			'method_contracts_callable'  => $methods,
		];
	}
}

if ( !\function_exists( 'shield_upgrade_test_capture_old_request_shutdown' ) ) {
	function shield_upgrade_test_capture_old_request_shutdown() :void {
		$report = shield_upgrade_test_contract_report();
		if ( empty( $report[ 'post_replace' ][ 'captured' ] )
			 || !empty( $report[ 'old_request_shutdown' ][ 'captured' ] ) ) {
			return;
		}
		$contracts = shield_upgrade_test_validate_lazy_contracts();
		shield_upgrade_test_store_contract_report( [
			'old_request_shutdown' => [
				'captured'                    => true,
				'lazy_dependencies_resolved' => $contracts[ 'lazy_dependencies_resolved' ],
				'method_contracts_callable'  => $contracts[ 'method_contracts_callable' ],
				'legacy_crons_after_old_callbacks' => [
					'afs'   => shield_upgrade_test_scheduled_instances( 'icwp-wpsf-afs_asset_change_cleanup' ),
					'build' => shield_upgrade_test_scheduled_instances( 'icwp-wpsf-ptg_build_snapshots' ),
					'wpv'   => shield_upgrade_test_scheduled_instances( 'icwp-wpsf-ondemand_scan_wpv' ),
				],
			],
		] );
	}
}

if ( !\function_exists( 'shield_upgrade_test_read_coordinator_state' ) ) {
	function shield_upgrade_test_read_coordinator_state() :array {
		$key = 'icwp-wpsf-asset_coordinator_state';
		$state = \is_multisite() ? \get_site_option( $key, [] ) : \get_option( $key, [] );
		return \is_array( $state ) ? $state : [];
	}
}

if ( !\function_exists( 'shield_upgrade_test_capture_new_boot_before_cleanup' ) ) {
	function shield_upgrade_test_capture_new_boot_before_cleanup() :void {
		$report = shield_upgrade_test_contract_report();
		if ( empty( $report[ 'old_request_shutdown' ][ 'captured' ] )
			 || !empty( $report[ 'new_boot_before_cleanup' ][ 'captured' ] )
			 || !\class_exists( '\\FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\HackGuard\\Lib\\AssetCoordinator\\AssetCoordinator', false ) ) {
			return;
		}
		$legacy = [
			'afs'   => shield_upgrade_test_scheduled_instances( 'icwp-wpsf-afs_asset_change_cleanup' ),
			'build' => shield_upgrade_test_scheduled_instances( 'icwp-wpsf-ptg_build_snapshots' ),
			'wpv'   => shield_upgrade_test_scheduled_instances( 'icwp-wpsf-ondemand_scan_wpv' ),
		];
		$canonicalHook = 'icwp-wpsf-asset_coordinator';
		$upgradeHook = 'icwp-wpsf-plugin-upgrade';
		$canonical = shield_upgrade_test_scheduled_instances( $canonicalHook );
		$upgrade = shield_upgrade_test_scheduled_instances( $upgradeHook );
		shield_upgrade_test_store_contract_report( [
			'new_boot_before_cleanup' => [
				'captured'            => true,
				'imported_state'      => shield_upgrade_test_read_coordinator_state(),
				'legacy_crons'        => $legacy,
				'canonical_wakeup'    => $canonical,
				'upgrade_cleanup_cron'=> $upgrade,
			],
		] );

		foreach ( $upgrade as $event ) {
			\wp_unschedule_event( $event[ 'timestamp' ], $upgradeHook, $event[ 'args' ] );
			\wp_schedule_single_event( \time() - 2, $upgradeHook, $event[ 'args' ] );
		}
		foreach ( $canonical as $event ) {
			\wp_unschedule_event( $event[ 'timestamp' ], $canonicalHook, $event[ 'args' ] );
		}
		if ( $canonical !== [] ) {
			\wp_schedule_single_event( \time() + 3600, $canonicalHook, [ \time() + 3600 ] );
		}
	}
}

if ( \function_exists( 'add_filter' ) ) {
	\add_filter( 'site_transient_update_plugins', 'shield_upgrade_test_apply_update_metadata', 99 );
	\add_filter( 'pre_set_site_transient_update_plugins', 'shield_upgrade_test_apply_update_metadata', 99 );
	\add_filter( 'plugins_api', 'shield_upgrade_test_plugins_api', 99, 3 );
	\add_filter( 'http_request_host_is_external', 'shield_upgrade_test_allow_package_host', 99, 3 );
	\add_filter( 'upgrader_pre_install', 'shield_upgrade_test_capture_pre_replace_contract', 999, 2 );
	\add_filter( 'upgrader_post_install', 'shield_upgrade_test_capture_post_replace_contract', 999, 3 );
	\add_action( 'icwp-wpsf-pre_plugin_shutdown', 'shield_upgrade_test_capture_old_request_shutdown', PHP_INT_MAX );
	\add_action( 'plugins_loaded', 'shield_upgrade_test_capture_new_boot_before_cleanup', PHP_INT_MAX );
	\add_action( 'doing_it_wrong_run', 'shield_upgrade_test_capture_early_translation_trace', 10, 3 );
}
