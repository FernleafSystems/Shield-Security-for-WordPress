<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

abstract class IpRuleAddRemoveBase extends IpRulesBase {

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