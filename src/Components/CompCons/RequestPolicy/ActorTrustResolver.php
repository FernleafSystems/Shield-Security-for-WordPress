<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ActorTrustResolver {

	use PluginControllerConsumer;

	public function resolve( string $ip = '' ) :ActorTrust {
		$req = self::con()->this_req;
		$ip = empty( $ip ) ? (string)$req->ip : $ip;
		$botScore = 0;

		try {
			$botScore = (int)\array_sum( ( new CalculateVisitorBotScores() )
				->setIP( $ip )
				->scores() );
		}
		catch ( \Exception $e ) {
		}
		$botProbability = (int)\max( 0, \min( 100, $botScore ) );

		return new ActorTrust( [
			'is_logged_in'           => Services::WpUsers()->isUserLoggedIn(),
			'is_security_admin'     => (bool)$req->is_security_admin,
			'is_high_reputation_ip' => $botScore > $this->highReputationMinimum(),
			'is_trusted_service'    => (bool)$req->is_trusted_request,
			'bot_probability'       => $botProbability,
		] );
	}

	private function highReputationMinimum() :int {
		try {
			$minimum = (int)self::con()->comps->opts_lookup->getIpHighReputationMinimum();
		}
		catch ( \Exception $e ) {
			$minimum = 80;
		}
		return \max( 0, $minimum );
	}
}
