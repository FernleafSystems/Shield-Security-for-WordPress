<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Constants,
	Responses
};

class DisableXmlrpc extends BuildRuleLockdownBase {

	public const SLUG = 'shield/disable_xmlrpc';

	protected function getName() :string {
		return 'Disable XMLRPC';
	}

	protected function getDescription() :string {
		return 'Disable XML-RPC if required.';
	}

	protected function getConditions() :array {
		return [
			'logic'      => Constants::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Constants::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\WpIsXmlrpc::class,
				],
			]
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\HookAddFilter::class,
				'params'   => [
					'hook'     => 'xmlrpc_enabled',
					'callback' => '__return_false',
					'priority' => 1000,
					'args'     => 0,
				]
			],
			[
				'response' => Responses\HookAddFilter::class,
				'params'   => [
					'hook'     => 'xmlrpc_methods',
					'callback' => '__return_empty_array',
					'priority' => 1000,
					'args'     => 0,
				]
			],
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event' => 'block_xml',
				],
			],
		];
	}
}