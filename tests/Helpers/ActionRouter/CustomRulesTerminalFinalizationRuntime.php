<?php declare( strict_types=1 );

/**
 * Runtime-only observers for the terminal-finalization fixture. This file is
 * deliberately passive: it observes hooks and never replays lifecycle work.
 */
if ( ! function_exists( 'shield_terminal_fixture_runtime_install' ) ) {
	function shield_terminal_fixture_runtime_key() :string {
		return 'shield_browser_fixture_custom_rules_terminal_finalization_runtime';
	}

	function shield_terminal_fixture_runtime_data() :array {
		$data = get_option( shield_terminal_fixture_runtime_key(), [] );
		return is_array( $data ) ? $data : [];
	}

	function shield_terminal_fixture_runtime_store( array $data ) :void {
		update_option( shield_terminal_fixture_runtime_key(), $data, false );
	}

	function shield_terminal_fixture_runtime_path() :string {
		$path = wp_parse_url( (string)( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		return is_string( $path ) ? $path : '';
	}

	function shield_terminal_fixture_runtime_is_target() :bool {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || PHP_SAPI === 'cli' ) {
			return false;
		}
		$data = shield_terminal_fixture_runtime_data();
		$paths = is_array( $data['target_paths'] ?? null ) ? $data['target_paths'] : [];
		return ( $data['token'] ?? '' ) !== ''
			&& in_array( shield_terminal_fixture_runtime_path(), $paths, true );
	}

	function shield_terminal_fixture_runtime_lifecycle() :void {
		if ( ! shield_terminal_fixture_runtime_is_target() ) {
			return;
		}
		$data = shield_terminal_fixture_runtime_data();
		$sequence = is_array( $data['lifecycle'] ?? null ) ? $data['lifecycle'] : [];
		$plugin = function_exists( 'shield_security_get_plugin' ) ? shield_security_get_plugin() : null;
		$controller = is_object( $plugin ) && method_exists( $plugin, 'getController' ) ? $plugin->getController() : null;
		$request = is_object( $controller ) ? ( $controller->this_req ?? null ) : null;
		$sequence[] = [
			'sequence'     => count( $sequence ) + 1,
			'hook'         => current_filter(),
			'path'         => shield_terminal_fixture_runtime_path(),
			'ip'           => is_object( $request ) ? (string)( $request->ip ?? '' ) : '',
			'ip_is_public' => is_object( $request ) && (bool)( $request->ip_is_public ?? false ),
			'bypasses'     => ! is_object( $request ) || (bool)( $request->request_bypasses_all_restrictions ?? true ),
		];
		$data['lifecycle'] = $sequence;
		shield_terminal_fixture_runtime_store( $data );
	}

	function shield_terminal_fixture_runtime_mail( $preempt, array $atts ) {
		$data = shield_terminal_fixture_runtime_data();
		if ( ! shield_terminal_fixture_runtime_is_target()
			 || ( $data['mail_path'] ?? '' ) !== shield_terminal_fixture_runtime_path()
		) {
			return $preempt;
		}
		$attempts = is_array( $data['mail_attempts'] ?? null ) ? $data['mail_attempts'] : [];
		$attempts[] = [
			'to'      => $atts['to'] ?? [],
			'subject' => (string)( $atts['subject'] ?? '' ),
		];
		$data['mail_attempts'] = $attempts;
		shield_terminal_fixture_runtime_store( $data );
		return true;
	}

	function shield_terminal_fixture_runtime_event( $event, array $meta = [], array $def = [] ) :void {
		if ( ! shield_terminal_fixture_runtime_is_target()
				|| ! in_array( $event, [ 'fw_email_success', 'fw_email_fail' ], true ) ) {
			return;
		}
		$data = shield_terminal_fixture_runtime_data();
		$events = is_array( $data['alert_results'] ?? null ) ? $data['alert_results'] : [];
		$events[] = [
			'event' => (string)$event,
			'to'    => (string)( $meta['audit_params']['to'] ?? '' ),
			'level' => (string)( $def['level'] ?? '' ),
		];
		$data['alert_results'] = $events;
		shield_terminal_fixture_runtime_store( $data );
	}

	function shield_terminal_fixture_runtime_install() :void {
		if ( ! function_exists( 'shield_security_get_plugin' ) ) {
			return;
		}
		$plugin = shield_security_get_plugin();
		$controller = is_object( $plugin ) && method_exists( $plugin, 'getController' ) ? $plugin->getController() : null;
		if ( ! is_object( $controller ) || ! method_exists( $controller, 'prefix' ) ) {
			return;
		}
		add_action( $controller->prefix( 'pre_plugin_shutdown' ), 'shield_terminal_fixture_runtime_lifecycle', PHP_INT_MAX );
		add_action( $controller->prefix( 'plugin_shutdown' ), 'shield_terminal_fixture_runtime_lifecycle', PHP_INT_MAX );
		add_filter( 'pre_wp_mail', 'shield_terminal_fixture_runtime_mail', 10, 2 );
		add_action( 'shield/event', 'shield_terminal_fixture_runtime_event', PHP_INT_MAX, 3 );
	}
	add_action( 'plugins_loaded', 'shield_terminal_fixture_runtime_install', PHP_INT_MAX );
}
