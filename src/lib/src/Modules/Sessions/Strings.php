<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	public function getEventStrings() :array {
		return [
			'session_start'             => [
				'name'  => __( 'Session Started', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Session started for user ({{user_login}}) with session ID {{session_id}}.', 'wp-simple-firewall' ),
				],
			],
			'session_terminate'         => [
				'name'  => __( 'Session Terminated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Session terminated.', 'wp-simple-firewall' ),
				],
			],
			'session_terminate_current' => [
				'name'  => __( 'Current Session Terminated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Current session terminated for user ({{user_login}}) with session ID {{session_id}}.', 'wp-simple-firewall' ),
				],
			],
			'login_success'             => [
				'name'  => __( 'Login Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Login successful.', 'wp-simple-firewall' ),
				],
			],
		];
	}
}