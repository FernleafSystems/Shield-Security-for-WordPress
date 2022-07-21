<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

class BaseAddRemove extends Base {

	/**
	 * @return array[]
	 */
	protected function getCommonIpCmdArgs() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'ip',
				'optional'    => false,
				'description' => 'The IP address.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'list',
				'optional'    => false,
				'options'     => [
					'bypass',
					'block',
					'white',
					'black',
				],
				'description' => 'The IP list to update.',
			],
		];
	}
}