<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
	Responses
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BuildFirewallBase extends \FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core\BuildRuleCoreShieldBase {

	use PluginControllerConsumer;

	public const SCAN_CATEGORY = '';

	protected function getName() :string {
		return '';
	}

	protected function getCommonAuditParamsMapping() :array {
		return \array_merge( parent::getCommonAuditParamsMapping(), [
			'term'  => 'match_pattern',
			'param' => 'match_request_param',
			'value' => 'match_request_value',
			'scan'  => 'match_category',
			'type'  => 'match_type',
		] );
	}

	protected function getDescription() :string {
		return sprintf( __( 'Check request parameters that trigger "%s" patterns.', 'wp-simple-firewall' ), $this->getName() );
	}

	protected function getConditions() :array {
		$whitelistedPaths = self::con()->cfg->configuration->def( 'whitelisted_paths' );
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => \array_filter( [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT
				],
				[
					'conditions' => Conditions\RequestHasAnyParameters::class,
				],

				self::con()->opts->optIs( 'whitelist_admins', 'Y' ) ? [
					'conditions' => Conditions\IsUserAdminNormal::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
				] : null,

				empty( $whitelistedPaths ) ? null : [
					'logic'      => Enum\EnumLogic::LOGIC_AND,
					'conditions' => \array_map(
						function ( string $path ) {
							return [
								'conditions' => Conditions\MatchRequestPath::class,
								'logic'      => Enum\EnumLogic::LOGIC_INVERT,
								'params'     => [
									'match_type' => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS_I,
									'match_path' => $path,
								],
							];
						},
						$whitelistedPaths
					),
				],
			] )
		];
	}

	protected function getResponses() :array {
		return [
			[
				'response' => Responses\EventFire::class,
				'params'   => [
					'event'            => 'firewall_block',
					'offense_count'    => 1,
					'block'            => false,
					'audit_params'     => [
						'name' => $this->getName()
					],
					'audit_params_map' => $this->getCommonAuditParamsMapping(),
				],
			],
			[
				'response' => Responses\FirewallBlock::class,
				'params'   => [],
			],
		];
	}

	protected function getFirewallPatterns() :array {
		return self::con()->cfg->configuration->def( 'firewall_patterns' )[ static::SCAN_CATEGORY ] ?? [];
	}

	protected function getFirewallPatterns_Regex() :array {
		return $this->getFirewallPatterns()[ 'regex' ] ?? [];
	}

	protected function getFirewallPatterns_Simple() :array {
		return $this->getFirewallPatterns()[ 'simple' ] ?? [];
	}
}