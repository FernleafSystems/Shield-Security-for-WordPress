<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

class Strings extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Strings {

	public function getEventStrings() :array {
		return [
			'lic_check_success'   => [
				'name'  => __( 'License Check Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'License check succeeded.', 'wp-simple-firewall' ),
				],
			],
			'lic_check_fail'      => [
				'name'  => __( 'License Check Failed', 'wp-simple-firewall' ),
				'audit' => [
					__( 'License check request failed.', 'wp-simple-firewall' ),
					__( 'Failure Type: {{type}}', 'wp-simple-firewall' ),
				],
			],
			'lic_fail_email'      => [
				'name'  => __( 'License Failure Email Sent', 'wp-simple-firewall' ),
				'audit' => [
					__( 'License check failed. Sending Warning Email.', 'wp-simple-firewall' ),
				],
			],
			'lic_fail_deactivate' => [
				'name'  => __( 'License Deactivated', 'wp-simple-firewall' ),
				'audit' => [
					__( 'A valid license could not be found - Deactivating Pro.', 'wp-simple-firewall' ),
				],
			],
		];
	}
}