<?php declare( strict_types=1 );

/**
 * Plugin Name: Shield Runtime Test Error Collector
 * Description: Test-only PHP error collector for Shield package runtime lanes.
 */

if ( !\function_exists( 'shield_runtime_test_error_dir' ) ) {
	function shield_runtime_test_error_dir() :string {
		return \defined( 'WP_CONTENT_DIR' )
			? WP_CONTENT_DIR.'/shield-runtime-test'
			: '';
	}
}

if ( !\function_exists( 'shield_runtime_test_record_event' ) ) {
	/**
	 * @param array<string,mixed> $event
	 */
	function shield_runtime_test_record_event( array $event ) :void {
		$dir = shield_runtime_test_error_dir();
		if ( $dir === '' ) {
			return;
		}
		if ( !\is_dir( $dir ) && !\mkdir( $dir, 0777, true ) && !\is_dir( $dir ) ) {
			return;
		}

		$event = \array_merge( [
			'time' => \gmdate( DATE_ATOM ),
			'uri'  => (string)( $_SERVER[ 'REQUEST_URI' ] ?? '' ),
		], $event );

		\file_put_contents(
			$dir.'/error-events.jsonl',
			\json_encode( $event, \JSON_UNESCAPED_SLASHES ).\PHP_EOL,
			\FILE_APPEND
		);
	}
}

if ( \defined( 'WP_CONTENT_DIR' ) ) {
	$shieldRuntimeTestDir = shield_runtime_test_error_dir();
	if ( $shieldRuntimeTestDir !== '' && ( \is_dir( $shieldRuntimeTestDir ) || \mkdir( $shieldRuntimeTestDir, 0777, true ) || \is_dir( $shieldRuntimeTestDir ) ) ) {
		\ini_set( 'log_errors', '1' );
		\ini_set( 'display_errors', '0' );
		\ini_set( 'error_log', $shieldRuntimeTestDir.'/wordpress-debug.log' );
		\error_reporting( E_ALL );
	}

	\set_error_handler(
		static function ( int $severity, string $message, string $file = '', int $line = 0 ) :bool {
			shield_runtime_test_record_event( [
				'type'     => 'php-error',
				'severity' => $severity,
				'message'  => $message,
				'file'     => $file,
				'line'     => $line,
			] );
			return false;
		}
	);

	\set_exception_handler(
		static function ( \Throwable $throwable ) :void {
			shield_runtime_test_record_event( [
				'type'    => 'uncaught-exception',
				'class'   => \get_class( $throwable ),
				'message' => $throwable->getMessage(),
				'file'    => $throwable->getFile(),
				'line'    => $throwable->getLine(),
				'trace'   => $throwable->getTraceAsString(),
			] );
		}
	);

	\register_shutdown_function(
		static function () :void {
			$error = \error_get_last();
			if ( !\is_array( $error ) ) {
				return;
			}
			if ( !\in_array( (int)( $error[ 'type' ] ?? 0 ), [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
				return;
			}
			shield_runtime_test_record_event( [
				'type'     => 'shutdown-fatal',
				'severity' => (int)( $error[ 'type' ] ?? 0 ),
				'message'  => (string)( $error[ 'message' ] ?? '' ),
				'file'     => (string)( $error[ 'file' ] ?? '' ),
				'line'     => (int)( $error[ 'line' ] ?? 0 ),
			] );
		}
	);
}
