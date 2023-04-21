<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'ips';

	/**
	 * @var Lib\OffenseTracker
	 */
	private $offenseTracker;

	/**
	 * @var Lib\Bots\BotSignalsController
	 */
	private $botSignalsCon;

	/**
	 * @var Lib\CrowdSec\CrowdSecController
	 */
	private $crowdSecCon;

	public function getBotSignalsController() :Lib\Bots\BotSignalsController {
		return $this->botSignalsCon ?? $this->botSignalsCon = new Lib\Bots\BotSignalsController();
	}

	public function getCrowdSecCon() :Lib\CrowdSec\CrowdSecController {
		return $this->crowdSecCon ?? $this->crowdSecCon = new Lib\CrowdSec\CrowdSecController();
	}

	public function loadOffenseTracker() :Lib\OffenseTracker {
		return $this->offenseTracker ?? $this->offenseTracker = new Lib\OffenseTracker( $this->getCon() );
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

		if ( $opts->isOptChanged( 'cs_block' ) && !$opts->isEnabledCrowdSecAutoBlock() ) {
			/** @var DB\IpRules\Ops\Delete $deleter */
			$deleter = $this->getDbH_IPRules()->getQueryDeleter();
			$deleter->filterByType( $this->getDbH_IPRules()::T_CROWDSEC )->query();
		}

		if ( $opts->isOptChanged( 'transgression_limit' ) && !$opts->isEnabledAutoBlackList() ) {
			/** @var DB\IpRules\Ops\Delete $deleter */
			$deleter = $this->getDbH_IPRules()->getQueryDeleter();
			$deleter->filterByType( $this->getDbH_IPRules()::T_AUTO_BLOCK )->query();
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
						$WP->getAdminUrl( 'admin.php' ),
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
				 Services::Request()->carbon()->subWeek()->timestamp,
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

	public function runHourlyCron() {
		( new DB\IpRules\CleanIpRules() )->cleanAutoBlocks();
	}

	public function runDailyCron() {
		( new DB\IpRules\CleanIpRules() )->execute();
	}
}