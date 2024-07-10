<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\{
	Ops as RulesDB,
	RuleRecords
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;

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
			Core\RequestIsSiteBlockdownBlocked::class,

			Core\IsSecurityAdmin::class,

			Core\ShieldLogRequest::class,
			Core\ShieldExcludeLogRequest::class,
			Core\IsRateLimitExceeded::class,

			Core\IpWhitelisted::class,
			Core\HighReputationIp::class,
			Core\IpBlockedShield::class,
			Core\IpBlockedCrowdsec::class,
			Core\BotTrack404::class,
			Core\BotTrackXmlrpc::class,
			Core\BotTrackFakeWebCrawler::class,
			Core\BotTrackInvalidScript::class,

			Core\DisableXmlrpc::class,
			Core\DisableFileEditing::class,
			Core\IsRequestAuthorDiscovery::class,

			Core\Firewall::class,

			Core\LockSessionFail::class,
			Core\DestroyIdleSessions::class,
		];
	}
}