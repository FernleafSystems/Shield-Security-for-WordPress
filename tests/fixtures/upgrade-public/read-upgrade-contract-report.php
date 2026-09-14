<?php

$report = \get_option( 'shield_upgrade_test_contract_report', [] );
$report = \is_array( $report ) ? $report : [];
$scheduled = static function ( string $hook ) :array {
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
};

$key = 'icwp-wpsf-asset_coordinator_state';
$state = \is_multisite() ? \get_site_option( $key, [] ) : \get_option( $key, [] );
$report[ 'after_upgrade_cleanup' ] = [
	'coordinator_state'   => \is_array( $state ) ? $state : [],
	'legacy_crons'        => [
		'afs'   => $scheduled( 'icwp-wpsf-afs_asset_change_cleanup' ),
		'build' => $scheduled( 'icwp-wpsf-ptg_build_snapshots' ),
		'wpv'   => $scheduled( 'icwp-wpsf-ondemand_scan_wpv' ),
	],
	'canonical_wakeup'    => $scheduled( 'icwp-wpsf-asset_coordinator' ),
	'upgrade_cleanup_cron'=> $scheduled( 'icwp-wpsf-plugin-upgrade' ),
];

echo \json_encode( $report, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR );
