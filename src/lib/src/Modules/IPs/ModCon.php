<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon {

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
		return isset( self::con()->comps ) ? self::con()->comps->bot_signals :
			( $this->botSignalsCon ?? $this->botSignalsCon = new Lib\Bots\BotSignalsController() );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getCrowdSecCon() :Lib\CrowdSec\CrowdSecController {
		return isset( self::con()->comps ) ? self::con()->comps->crowdsec :
			( $this->crowdSecCon ?? $this->crowdSecCon = new Lib\CrowdSec\CrowdSecController() );
	}

	public function loadOffenseTracker() :Lib\OffenseTracker {
		return isset( self::con()->comps ) ? self::con()->comps->offense_tracker :
			( $this->offenseTracker ?? $this->offenseTracker = new Lib\OffenseTracker() );
	}

	public function getAllowable404s() :array {
		$def = self::con()->cfg->configuration->def( 'bot_signals' )[ 'allowable_ext_404s' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_extensions_404s', $def ),
			function ( $ext ) {
				return !empty( $ext ) && \is_string( $ext ) && \preg_match( '#^[a-z\d]+$#i', $ext );
			}
		) );
	}

	public function getAllowableScripts() :array {
		$def = self::con()->cfg->configuration->def( 'bot_signals' )[ 'allowable_invalid_scripts' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_invalid_scripts', $def ),
			function ( $script ) {
				return !empty( $script ) && \is_string( $script ) && \strpos( $script, '.php' );
			}
		) );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_BotSignal() :DB\BotSignal\Ops\Handler {
		return self::con()->db_con->loadDbH( 'botsignal' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_IPRules() :DB\IpRules\Ops\Handler {
		return self::con()->db_con->loadDbH( 'ip_rules' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_CrowdSecSignals() :DB\CrowdSecSignals\Ops\Handler {
		return self::con()->db_con->loadDbH( 'crowdsec_signals' );
	}
}