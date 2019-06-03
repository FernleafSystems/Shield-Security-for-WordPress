<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [
			'block_anonymous_api' => [
				__( 'Blocked Anonymous API Access through "%s" namespace', 'wp-simple-firewall' )
			],
		];
	}
}