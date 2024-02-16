<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Ops\TableIndices;

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
		return $this->botSignalsCon ?? $this->botSignalsCon = new Lib\Bots\BotSignalsController();
	}

	public function getCrowdSecCon() :Lib\CrowdSec\CrowdSecController {
		return $this->crowdSecCon ?? $this->crowdSecCon = new Lib\CrowdSec\CrowdSecController();
	}

	public function loadOffenseTracker() :Lib\OffenseTracker {
		return $this->offenseTracker ?? $this->offenseTracker = new Lib\OffenseTracker();
	}

	public function onConfigChanged() :void {
		/** @var Options $opts */
		$opts = $this->opts();
		$dbhIPRules = self::con()->db_con->dbhIPRules();

		if ( $opts->isOptChanged( 'cs_block' ) && !$opts->isEnabledCrowdSecAutoBlock() ) {
			/** @var DB\IpRules\Ops\Delete $deleter */
			$deleter = $dbhIPRules->getQueryDeleter();
			$deleter->filterByType( $dbhIPRules::T_CROWDSEC )->query();
		}

		if ( $opts->isOptChanged( 'transgression_limit' ) && !$opts->isEnabledAutoBlackList() ) {
			/** @var DB\IpRules\Ops\Delete $deleter */
			$deleter = $dbhIPRules->getQueryDeleter();
			$deleter->filterByType( $dbhIPRules::T_AUTO_BLOCK )->query();
		}
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

	public function runHourlyCron() {
		( new DB\IpRules\CleanIpRules() )->cleanAutoBlocks();
	}

	public function runDailyCron() {
		parent::runDailyCron();
		( new TableIndices( self::con()->db_con->dbhIPRules()->getTableSchema() ) )->applyFromSchema();
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