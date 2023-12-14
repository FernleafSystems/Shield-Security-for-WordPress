<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	Firewall,
	IPs,
	Lockdown,
	Plugin,
	PluginControllerConsumer,
	SecurityAdmin,
	Traffic
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;

class RuleBuilderEnumerator {

	use PluginControllerConsumer;

	/**
	 * @return BuildRuleBase[]
	 */
	public function run() :array {
		$rules = $this->direct();
		if ( self::con()->isPremiumActive() ) {
//			$rules = \array_merge( $rules, $this->custom() );
		}
		return $rules;
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
		self::con()->getModule_Plugin()->opts()->setOpt( 'custom_rules', [] );
		return \array_map(
			function ( array $raw ) {
				return new BuildRuleFromForm( ( new RuleFormBuilderVO() )->applyFromArray( $raw ) );
			},
			self::con()->getModule_Plugin()->opts()->getOpt( 'custom_rules' )
		);
	}

	private function shieldCoreRules() :array {
		$con = self::con();

		/** @var IPs\Options $ipsOpts */
		$ipsOpts = $con->getModule_IPs()->opts();
		/** @var Lockdown\Options $lockdownOpts */
		$lockdownOpts = $con->getModule_Lockdown()->opts();
		/** @var Traffic\Options $trafficOpts */
		$trafficOpts = $con->getModule_Traffic()->opts();

		return \array_merge(
			[
				Plugin\Rules\Build\RequestStatusIsAdmin::class,
				Plugin\Rules\Build\RequestStatusIsAjax::class,
				Plugin\Rules\Build\RequestStatusIsXmlRpc::class,
				Plugin\Rules\Build\RequestStatusIsWpCli::class,

				SecurityAdmin\Rules\Build\IsSecurityAdmin::class,

				$trafficOpts->isTrafficLimitEnabled() ? Traffic\Rules\Build\IsRateLimitExceeded::class : null,

				IPs\Rules\Build\IpWhitelisted::class,
				Plugin\Rules\Build\RequestIsSiteBlockdownBlocked::class,
				IPs\Rules\Build\IpBlockedShield::class,
				$ipsOpts->isEnabledCrowdSecAutoBlock() ? IPs\Rules\Build\IpBlockedCrowdsec::class : null,
				IPs\Rules\Build\BotTrack404::class,
				IPs\Rules\Build\BotTrackXmlrpc::class,
				IPs\Rules\Build\BotTrackFakeWebCrawler::class,
				IPs\Rules\Build\BotTrackInvalidScript::class,

				$lockdownOpts->isXmlrpcDisabled() ? Lockdown\Rules\Build\DisableXmlrpc::class : null,
				$lockdownOpts->isOptFileEditingDisabled() ? Lockdown\Rules\Build\DisableFileEditing::class : null,
				$lockdownOpts->isOpt( 'block_author_discovery', 'Y' ) ? Lockdown\Rules\Build\IsRequestAuthorDiscovery::class : null,
				$lockdownOpts->isOpt( 'hide_wordpress_generator_tag', 'Y' ) ? Lockdown\Rules\Build\HideGeneratorTag::class : null,
				$lockdownOpts->isOpt( 'force_ssl_admin', 'Y' ) ? Lockdown\Rules\Build\ForceSslAdmin::class : null,
			],
			\array_filter(
				[
					Firewall\Rules\Build\FirewallSqlQueries::class,
					Firewall\Rules\Build\FirewallDirTraversal::class,
					Firewall\Rules\Build\FirewallFieldTruncation::class,
					Firewall\Rules\Build\FirewallWordpressTerms::class,
					Firewall\Rules\Build\FirewallPhpCode::class,
					Firewall\Rules\Build\FirewallAggressive::class,
					Firewall\Rules\Build\FirewallExeFileUploads::class,
				],
				function ( $blockTypeClass ) {
					/** @var Firewall\Rules\Build\BuildFirewallBase $blockTypeClass */
					return self::con()
							   ->getModule_Firewall()
							   ->opts()
							   ->isOpt( 'block_'.$blockTypeClass::SCAN_CATEGORY, 'Y' );
				}
			)
		);
	}
}