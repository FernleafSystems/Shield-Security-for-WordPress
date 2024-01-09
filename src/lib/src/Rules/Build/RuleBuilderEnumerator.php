<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	Data\DB\Rules\RuleRecords,
	Firewall,
	IPs,
	Lockdown,
	Plugin,
	PluginControllerConsumer,
	SecurityAdmin,
	Traffic
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\Ops as RulesDB;

class RuleBuilderEnumerator {

	use PluginControllerConsumer;

	/**
	 * @return BuildRuleBase[]
	 */
	public function run() :array {
		return \array_merge( $this->direct(), $this->custom() );
	}

	/**
	 * @return BuildRuleBase[]
	 */
	private function direct() :array {
		return \array_filter( \array_map(
			function ( string $class ) {
				return ( \class_exists( $class ) && \is_subclass_of( $class, BuildRuleBase::class ) ) ? new $class() : null;
			},
			\array_filter( \array_merge(
				$this->shieldCoreRules(),
				\apply_filters( 'shield/collate_rule_builders', [] )
			) )
		) );
	}

	private function custom() :array {
		return \array_map(
			function ( RulesDB\Record $record ) {
				return new BuildRuleFromForm( ( new RuleFormBuilderVO() )->applyFromArray( $record->form ) );
			},
			( new RuleRecords() )->getActiveCustom()
		);
	}

	private function shieldCoreRules() :array {
		return [
			Plugin\Rules\Build\RequestIsSiteBlockdownBlocked::class,

			SecurityAdmin\Rules\Build\IsSecurityAdmin::class,

			Traffic\Rules\Build\ShieldLogRequest::class,
			Traffic\Rules\Build\ShieldExcludeLogRequest::class,
			Traffic\Rules\Build\IsRateLimitExceeded::class,

			IPs\Rules\Build\IpWhitelisted::class,
			IPs\Rules\Build\HighReputationIp::class,
			IPs\Rules\Build\IpBlockedShield::class,
			IPs\Rules\Build\IpBlockedCrowdsec::class,
			IPs\Rules\Build\BotTrack404::class,
			IPs\Rules\Build\BotTrackXmlrpc::class,
			IPs\Rules\Build\BotTrackFakeWebCrawler::class,
			IPs\Rules\Build\BotTrackInvalidScript::class,

			Lockdown\Rules\Build\DisableXmlrpc::class,
			Lockdown\Rules\Build\DisableFileEditing::class,
			Lockdown\Rules\Build\ForceSslAdmin::class,
			Lockdown\Rules\Build\IsRequestAuthorDiscovery::class,
			Lockdown\Rules\Build\HideGeneratorTag::class,

			Firewall\Rules\Build\Firewall::class
		];
	}
}