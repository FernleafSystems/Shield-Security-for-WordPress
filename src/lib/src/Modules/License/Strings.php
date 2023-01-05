<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'lic_check_success'   => [
				'name'  => __( 'License Check Success', 'wp-simple-firewall' ),
				'audit' => [
					__( 'License check succeeded.', 'wp-simple-firewall' ),
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
					__( 'License check failed. Deactivating Pro.', 'wp-simple-firewall' ),
				],
			],
		];
	}

	/**
	 * @inheritDoc
	 * @deprecated 17.0
	 */
	protected function getAdditionalDisplayStrings() :array {
		return [];
	}
}