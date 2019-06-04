<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [
			'check_success'         => [
				__( 'Pro License check succeeded.', 'wp-simple-firewall' )
			],
			'check_fail_email'      => [
				__( 'License check failed. Sending Warning Email.', 'wp-simple-firewall' )
			],
			'check_fail_deactivate' => [
				__( 'License check failed. Deactivating Pro.', 'wp-simple-firewall' )
			],
		];
	}
}