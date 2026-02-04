<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
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
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\WpIsXmlrpc::class,
				],
				[
					'conditions' => Conditions\ShieldConfigurationOption::class,
					'params'     => [
						'name'        => 'disable_xmlrpc',
						'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
						'match_value' => 'Y',
					]
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