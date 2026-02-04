<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class NotBotHandler {

	use ExecOnce;
	use PluginControllerConsumer;

	public const LIFETIME = 600;
	public const COOKIE_SLUG = 'notbot';
	public const SIGNAL_NOTBOT = 'notbot';
	public const SIGNAL_ALTCHA = 'altcha';

	protected function canRun() :bool {
		$con = self::con();
		return (bool)apply_filters( 'shield/can_run_antibot', $con->comps->opts_lookup->enabledAntiBotEngine() && $con->db_con->bot_signals->isReady() );
	}

	protected function run() {
		if ( \defined( 'LOGGED_IN_COOKIE' ) && Services::Request()->cookie( LOGGED_IN_COOKIE ) ) {
			add_action( 'init', [ $this, 'sendNotBotFlagCookie' ] );
		}
		else {
			$this->sendNotBotFlagCookie();
		}

		( new InsertNotBotJs() )->execute();
	}

	public function sendNotBotFlagCookie() {
		$cookieParts = $this->getNonRequiredSignals();
		$cookieParts[] = 'exp-'.( Services::Request()->ts() + self::LIFETIME );
		Services::Response()->cookieSet(
			self::con()->prefix( self::COOKIE_SLUG ),
			\implode( 'Z', $cookieParts ),
			apply_filters( 'shield/notbot_cookie_life', self::LIFETIME )
		);
	}

	public function getNonRequiredSignals() :array {
		return \array_diff( $this->getSignalSlugs(), $this->getRequiredSignals() );
	}

	public function getRequiredSignals() :array {
		$con = self::con();
		try {
			$BS = ( new BotSignalsRecord() )
				->setIP( $con->this_req->ip )
				->retrieve();
		}
		catch ( \Exception $e ) {
			$BS = null;
		}

		$isVisitorUnidentified = \in_array( Services::IP()->getIpDetector()->getIPIdentity(), [
			IpID::UNKNOWN,
			IpID::LOOPBACK,
			IpID::THIS_SERVER
		], true );

		return \array_keys( \array_filter( [
			self::SIGNAL_NOTBOT => !empty( $BS )
								   && $isVisitorUnidentified
								   && $con->comps->altcha->complexityLevel() !== 'none'
								   && ( Services::Request()->ts() - $BS->notbot_at > HOUR_IN_SECONDS ),
			self::SIGNAL_ALTCHA => !empty( $BS )
								   && $isVisitorUnidentified
								   && $con->comps->altcha->enabled()
								   && ( Services::Request()->ts() - $BS->altcha_at > HOUR_IN_SECONDS ),
		] ) );
	}

	protected function getSignalSlugs() :array {
		return [
			self::SIGNAL_NOTBOT,
			self::SIGNAL_ALTCHA,
		];
	}
}