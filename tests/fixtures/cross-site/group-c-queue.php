<?php declare( strict_types=1 );

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

const SHIELD_GROUP_C_QUEUE_STATE = 'shield_group_c_queue_state';

function shield_group_c_queue_state() :array {
	$state = get_option( SHIELD_GROUP_C_QUEUE_STATE, [] );
	return is_array( $state ) ? $state : [];
}

add_action( 'muplugins_loaded', static function () :void {
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		$state = shield_group_c_queue_state();
		$state[ 'cron_entries' ] = (int)( $state[ 'cron_entries' ] ?? 0 ) + 1;
		update_option( SHIELD_GROUP_C_QUEUE_STATE, $state, false );
	}
	$action = (string)( $_REQUEST[ 'action' ] ?? '' );
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX && strpos( $action, 'importexport_sites_queue_' ) !== false ) {
		$state = shield_group_c_queue_state();
		$state[ 'ajax_entries' ] = (int)( $state[ 'ajax_entries' ] ?? 0 ) + 1;
		update_option( SHIELD_GROUP_C_QUEUE_STATE, $state, false );
	}
} );

add_filter( 'http_request_args', static function ( array $args, string $url ) :array {
	if ( strpos( $url, 'admin-ajax.php' ) !== false && strpos( $url, 'importexport_sites_queue_' ) !== false ) {
		$state = shield_group_c_queue_state();
		$state[ 'dispatch_attempts' ] = (int)( $state[ 'dispatch_attempts' ] ?? 0 ) + 1;
		$state[ 'dispatch_blocking' ] = !empty( $args[ 'blocking' ] );
		$state[ 'dispatch_url' ] = $url;
		update_option( SHIELD_GROUP_C_QUEUE_STATE, $state, false );
	}
	return $args;
}, 10, 2 );

add_filter( 'pre_http_request', static function ( $preempt, array $args, string $url ) {
	$state = shield_group_c_queue_state();
	$mode = (string)( $state[ 'mode' ] ?? '' );
	if ( $mode === '' ) {
		return $preempt;
	}

	if ( strpos( $url, 'group-c-queue-' ) === false ) {
		return $preempt;
	}

	$queueHook = 'icwp-wpsf-importexport_sites_queue';
	$futureEvent = wp_next_scheduled( $queueHook );
	$row = ( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository() )->findByUrl( $url, true );
	$state[ 'notification_starts' ] = (int)( $state[ 'notification_starts' ] ?? 0 ) + 1;
	$state[ 'attempt_counters' ][] = (int)( $row->meta[ 'notification_attempts_started' ] ?? 0 );
	$state[ 'future_event_observed' ] = $futureEvent !== false;
	update_option( SHIELD_GROUP_C_QUEUE_STATE, $state, false );
	if ( $futureEvent === false ) {
		return new \WP_Error( 'shield_group_c_missing_health_event', 'Production health event was missing.' );
	}

	if ( $mode === 'terminated' && empty( $state[ 'terminated_once' ] ) ) {
		$state[ 'terminated_once' ] = true;
		update_option( SHIELD_GROUP_C_QUEUE_STATE, $state, false );
		exit;
	}
	if ( $mode === 'healthy' && empty( $state[ 'slow_once' ] ) ) {
		$state[ 'slow_once' ] = true;
		update_option( SHIELD_GROUP_C_QUEUE_STATE, $state, false );
		usleep( 10100000 );
	}

	return [
		'headers'  => [],
		'body'     => '',
		'response' => [ 'code' => 204, 'message' => 'No Content' ],
		'cookies'  => [],
		'filename' => null,
	];
}, 1, 3 );
