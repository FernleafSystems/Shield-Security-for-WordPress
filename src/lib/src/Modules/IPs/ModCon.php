<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\DbTableExport;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	const LIST_MANUAL_WHITE = 'MW';
	const LIST_MANUAL_BLACK = 'MB';
	const LIST_AUTO_BLACK = 'AB';

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

	public function getBotSignalsController() :Lib\Bots\BotSignalsController {
		if ( !isset( $this->botSignalsCon ) ) {
			$this->botSignalsCon = ( new Lib\Bots\BotSignalsController() )
				->setMod( $this );
		}
		return $this->botSignalsCon;
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

	public function getDbHandler_IPs() :Shield\Databases\IPs\Handler {
		return $this->getDbH( 'ip_lists' );
	}

	protected function enumRuleBuilders() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return [
			Rules\Build\IpWhitelisted::class,
			Rules\Build\IsPathWhitelisted::class,
			$opts->isEnabledAutoBlackList() ? Rules\Build\IpBlocked::class : null,
			$opts->isEnabledTrack404() ? Rules\Build\BotTrack404::class : null,
			$opts->isEnabledTrackXmlRpc() ? Rules\Build\BotTrackXmlrpc::class : null,
			$opts->isEnabledTrackFakeWebCrawler() ? Rules\Build\BotTrackFakeWebCrawler::class : null,
			$opts->isEnabledTrackInvalidScript() ? Rules\Build\BotTrackInvalidScript::class : null,
		];
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		$oIp = Services::IP();
		return $oIp->isValidIp_PublicRange( $oIp->getRequestIp() )
			   && ( $this->getDbHandler_IPs() instanceof Shield\Databases\IPs\Handler )
			   && $this->getDbHandler_IPs()->isReady()
			   && parent::isReadyToExecute();
	}

	protected function handleFileDownload( string $downloadID ) {
		switch ( $downloadID ) {
			case 'db_ip':
				( new DbTableExport() )
					->setDbHandler( $this->getDbHandler_IPs() )
					->toCSV();
				break;
		}
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( !defined( strtoupper( $opts->getOpt( 'auto_expire' ).'_IN_SECONDS' ) ) ) {
			$opts->resetOptToDefault( 'auto_expire' );
		}

		$nLimit = $opts->getOffenseLimit();
		if ( !is_int( $nLimit ) || $nLimit < 0 ) {
			$opts->resetOptToDefault( 'transgression_limit' );
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

	public function getTextOptDefault( string $key ) :string {

		switch ( $key ) {

			case 'text_loginfailed':
				$text = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'Repeated login attempts that fail will result in a complete ban of your IP Address.', 'wp-simple-firewall' )
				);
				break;

			case 'text_remainingtrans':
				$text = sprintf( '%s: %s',
					__( 'Warning', 'wp-simple-firewall' ),
					__( 'You have %s remaining offenses(s) against this site and then your IP address will be completely blocked.', 'wp-simple-firewall' )
					.'<br/><strong>'.__( 'Seriously, stop repeating what you are doing or you will be locked out.', 'wp-simple-firewall' ).'</strong>'
					.sprintf( ' [<a href="%s" target="_blank">%s</a>]', 'https://shsec.io/shieldcantaccess', __( 'More Info', 'wp-simple-firewall' ) )
				);
				break;

			default:
				$text = parent::getTextOptDefault( $key );
				break;
		}
		return $text;
	}

	/**
	 * @deprecated 12.1
	 */
	protected function cleanupDatabases() {
		$dbhIPs = $this->getDbHandler_IPs();
		if ( $dbhIPs->isReady() ) {
			$dbhIPs->autoCleanDb();
		}
		$this->getDbH_BotSignal()
			 ->getQueryDeleter()
			 ->addWhereOlderThan(
				 Services::Request()->carbon()->subWeeks( 1 )->timestamp,
				 'updated_at'
			 )
			 ->query();
	}
}