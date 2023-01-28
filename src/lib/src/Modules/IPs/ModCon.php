<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	public const LIST_MANUAL_WHITE = 'MW';
	public const LIST_MANUAL_BLACK = 'MB';
	public const LIST_AUTO_BLACK = 'AB';

	/**
	 * @var Lib\OffenseTracker
	 */
	private $oOffenseTracker;

	/**
	 * @var Lib\BlacklistHandler
	 */
	private $oBlacklistHandler;

	/**
	 * @var Lib\Bots\BotSignalsController
	 */
	private $botSignalsCon;

	/**
	 * @var Lib\CrowdSec\CrowdSecController
	 */
	private $crowdSecCon;

	public function getBotSignalsController() :Lib\Bots\BotSignalsController {
		if ( !isset( $this->botSignalsCon ) ) {
			$this->botSignalsCon = ( new Lib\Bots\BotSignalsController() )
				->setMod( $this );
		}
		return $this->botSignalsCon;
	}

	public function getCrowdSecCon() :Lib\CrowdSec\CrowdSecController {
		if ( !isset( $this->crowdSecCon ) ) {
			$this->crowdSecCon = ( new Lib\CrowdSec\CrowdSecController() )->setMod( $this );
		}
		return $this->crowdSecCon;
	}

	public function getBlacklistHandler() :Lib\BlacklistHandler {
		if ( !isset( $this->oBlacklistHandler ) ) {
			$this->oBlacklistHandler = ( new Lib\BlacklistHandler() )->setMod( $this );
		}
		return $this->oBlacklistHandler;
	}

	public function getDbH_BotSignal() :DB\BotSignal\Ops\Handler {
		$this->getCon()->getModule_Data()->getDbH_IPs();
		return $this->getDbHandler()->loadDbH( 'botsignal' );
	}

	public function getDbH_IPRules() :DB\IpRules\Ops\Handler {
		$this->getCon()->getModule_Data()->getDbH_IPs();
		return $this->getDbHandler()->loadDbH( 'ip_rules' );
	}

	public function getDbH_CrowdSecSignals() :DB\CrowdSecSignals\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'crowdsec_signals' );
	}

	/**
	 * @deprecated 17.0
	 */
	public function getDbHandler_IPs() :Shield\Databases\IPs\Handler {
		return $this->getDbH( 'ip_lists' );
	}

	protected function enumRuleBuilders() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return [
			Rules\Build\IpWhitelisted::class,
			Rules\Build\IsPathWhitelisted::class,
			Rules\Build\IpBlockedShield::class,
			$opts->isEnabledCrowdSecAutoBlock() ? Rules\Build\IpBlockedCrowdsec::class : null,
			Rules\Build\BotTrack404::class,
			Rules\Build\BotTrackXmlrpc::class,
			Rules\Build\BotTrackFakeWebCrawler::class,
			Rules\Build\BotTrackInvalidScript::class,
		];
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return $this->getDbH_IPRules()->isReady() && parent::isReadyToExecute();
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( !defined( strtoupper( $opts->getOpt( 'auto_expire' ).'_IN_SECONDS' ) ) ) {
			$opts->resetOptToDefault( 'auto_expire' );
		}
		$this->cleanPathWhitelist();
	}

	private function cleanPathWhitelist() {
		$WP = Services::WpGeneral();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$opts->setOpt( 'request_whitelist',
			( new Shield\Modules\Base\Options\WildCardOptions() )->clean(
				$opts->getOpt( 'request_whitelist', [] ),
				array_unique( array_map(
					function ( $url ) {
						return (string)wp_parse_url( $url, PHP_URL_PATH );
					},
					[
						'/',
						$WP->getHomeUrl(),
						$WP->getWpUrl(),
					]
				) ),
				Shield\Modules\Base\Options\WildCardOptions::URL_PATH
			)
		);
	}

	public function canLinkCheese() :bool {
		$FS = Services::WpFs();
		$WP = Services::WpGeneral();
		$isSplit = trim( (string)parse_url( $WP->getHomeUrl(), PHP_URL_PATH ), '/' )
				   !== trim( (string)parse_url( $WP->getWpUrl(), PHP_URL_PATH ), '/' );
		return !$FS->exists( path_join( ABSPATH, 'robots.txt' ) )
			   && ( !$isSplit || !$FS->exists( path_join( dirname( ABSPATH ), 'robots.txt' ) ) );
	}

	public function loadOffenseTracker() :Lib\OffenseTracker {
		if ( !isset( $this->oOffenseTracker ) ) {
			$this->oOffenseTracker = new Lib\OffenseTracker( $this->getCon() );
		}
		return $this->oOffenseTracker;
	}

	public function getScriptLocalisations() :array {
		$locals = parent::getScriptLocalisations();

		$locals[] = [
			'plugin',
			'icwp_wpsf_vars_ips',
			[
				'components' => [
					'ip_analysis' => [
						'ajax' => [
							'ip_analyse_action' => ActionData::Build( Actions\IpAnalyseAction::SLUG ),
						]
					],
					'ip_rules'    => [
						'ajax'    => [
							'ip_rule_add_submit' => ActionData::Build( Actions\IpRuleAddSubmit::SLUG ),
							'ip_rule_delete'     => ActionData::Build( Actions\IpRuleDelete::SLUG ),
						],
						'strings' => [
							'are_you_sure' => __( 'Are you sure you want to delete this IP Rule?', 'wp-simple-firewall' ),
						],
					],
				],
			]
		];
		return $locals;
	}

	public function getTextOptDefault( string $key ) :string {

		switch ( $key ) {

			case 'text_loginfailed':
				$text = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'Repeated login attempts that fail will result in a complete ban of your IP Address.', 'wp-simple-firewall' )
				);
				break;

			default:
				$text = parent::getTextOptDefault( $key );
				break;
		}
		return $text;
	}

	protected function cleanupDatabases() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$this->getDbH_BotSignal()
			 ->getQueryDeleter()
			 ->addWhereOlderThan(
				 Services::Request()->carbon()->subWeeks( 1 )->timestamp,
				 'updated_at'
			 )
			 ->query();

		/** @var DB\IpRules\Ops\Delete $ipRulesDeleter */
		$ipRulesDeleter = $this->getDbH_IPRules()->getQueryDeleter();
		$ipRulesDeleter
			->filterByType( DB\IpRules\Ops\Handler::T_AUTO_BLOCK )
			->addWhereOlderThan(
				Services::Request()->carbon()->subSeconds( $opts->getAutoExpireTime() )->timestamp,
				'last_access_at'
			)
			->query();
	}

	/**
	 * @deprecated 17.0
	 */
	public function runIpMigrator() {
	}

	public function runHourlyCron() {
		( new DB\IpRules\CleanIpRules() )
			->setMod( $this )
			->cleanAutoBlocks();
	}

	public function runDailyCron() {
		( new DB\IpRules\CleanIpRules() )
			->setMod( $this )
			->execute();
	}
}