<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\TrustedServices;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IsHighReputationIP;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * Works out an IP's visitor standing from its IP rules and stored evidence. Who is making the
 * request (a logged-in user, a Security Admin) is not part of an IP's standing.
 */
class ResolveStanding {

	use IpAddressConsumer;
	use PluginControllerConsumer;

	/**
	 * The owner's decisions (bypass list, manual block) come before trust that Shield works out itself.
	 */
	public function resolve( int $now ) :VisitorStanding {
		$status = new IpRuleStatus( $this->getIP() );

		if ( $status->isBypass() ) {
			$standing = new VisitorStanding( VisitorStanding::TRUSTED, [ VisitorStanding::REASON_BYPASS ] );
		}
		elseif ( $status->hasManualBlock() ) {
			$standing = new VisitorStanding( VisitorStanding::BLOCKED, [ VisitorStanding::REASON_MANUAL_BLOCK ] );
		}
		elseif ( $this->isTrustedService() ) {
			$standing = new VisitorStanding( VisitorStanding::TRUSTED, [ VisitorStanding::REASON_TRUSTED_SERVICE ] );
		}
		else {
			$standing = $this->fromEvidence( $status, $now );
		}

		return $standing;
	}

	private function fromEvidence( IpRuleStatus $status, int $now ) :VisitorStanding {
		$opts = self::con()->comps->opts_lookup;
		$record = $status->getRuleForAutoBlock();
		$stored = empty( $record ) ? null : new StoredEvidence( $record );
		$evidence = empty( $stored ) ? 0.0 : $stored->total( $now );
		$hostileUntil = empty( $stored ) ? 0 : $stored->hostileUntil( $opts->getIpAutoBlockTTL() );
		$hostileUntil = $hostileUntil > $now ? $hostileUntil : 0;
		$limit = \max( 0, $opts->getIpAutoBlockOffenseLimit() );

		$level = VisitorStanding::NORMAL;
		$reasons = [];

		if ( $limit > 0 ) {
			if ( $hostileUntil > 0 ) {
				$level = VisitorStanding::HOSTILE;
				$reasons[] = VisitorStanding::REASON_HOSTILE_HOLD;
			}
			elseif ( $evidence >= $limit ) {
				$level = VisitorStanding::HOSTILE;
				$reasons[] = VisitorStanding::REASON_OFFENSES;
			}
			elseif ( $evidence >= \ceil( $limit/3 ) ) {
				$level = VisitorStanding::DOUBTFUL;
				$reasons[] = VisitorStanding::REASON_OFFENSES;
			}
		}

		if ( $level === VisitorStanding::HOSTILE && $this->isHighReputation() ) {
			$level = VisitorStanding::DOUBTFUL;
			$reasons[] = VisitorStanding::REASON_HIGH_REPUTATION;
		}

		if ( $opts->enabledCrowdSecAutoBlock() && $status->hasCrowdsecRule() ) {
			if ( $status->hasCrowdsecBlock() ) {
				$level = VisitorStanding::HOSTILE;
				$reasons[] = VisitorStanding::REASON_CROWDSEC;
			}
			else {
				// Passed the CrowdSec unblock: doubtful, and hostile again at half the offense limit.
				$reasons[] = VisitorStanding::REASON_CROWDSEC_UNBLOCKED;
				$level = ( $limit > 0 && $evidence*2 >= $limit )
					? VisitorStanding::HOSTILE
					: ( $level === VisitorStanding::HOSTILE ? VisitorStanding::HOSTILE : VisitorStanding::DOUBTFUL );
			}
		}

		return new VisitorStanding( $level, $reasons, $evidence, $hostileUntil );
	}

	private function isTrustedService() :bool {
		try {
			[ $ipKey, ] = ( new IpID( $this->getIP() ) )->setIgnoreUserAgent()->run();
		}
		catch ( \Exception $e ) {
			$ipKey = IpID::UNKNOWN;
		}
		return \in_array( $ipKey, ( new TrustedServices() )->enum(), true );
	}

	private function isHighReputation() :bool {
		return ( new IsHighReputationIP() )->setIP( $this->getIP() )->query();
	}
}
