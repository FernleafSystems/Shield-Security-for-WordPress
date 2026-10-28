<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Standing;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\TrustedServices;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IsHighReputationIP;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * Works out an IP's visitor standing from its IP rules and stored evidence. Who is making the
 * request (a logged-in user, a Security Admin) is not part of an IP's standing.
 */
class ResolveStanding {

	use IpAddressConsumer;
	use PluginControllerConsumer;

	public function resolve() :VisitorStanding {
		$status = new IpRuleStatus( $this->getIP() );

		if ( $status->isBypass() ) {
			$standing = new VisitorStanding( VisitorStanding::TRUSTED, [ VisitorStanding::REASON_BYPASS ] );
		}
		elseif ( $this->isTrustedService() ) {
			$standing = new VisitorStanding( VisitorStanding::TRUSTED, [ VisitorStanding::REASON_TRUSTED_SERVICE ] );
		}
		elseif ( $status->hasManualBlock() ) {
			$standing = new VisitorStanding( VisitorStanding::BLOCKED, [ VisitorStanding::REASON_MANUAL_BLOCK ] );
		}
		else {
			$standing = $this->fromEvidence( $status );
		}

		return $standing;
	}

	private function fromEvidence( IpRuleStatus $status ) :VisitorStanding {
		$now = Services::Request()->ts();
		$record = $status->getRuleForAutoBlock();
		$evidence = empty( $record ) ? 0 : ( new StoredEvidence( $record ) )->total( $now );
		$hostileUntil = empty( $record ) || $record->hostile_until <= $now ? 0 : $record->hostile_until;
		$limit = \max( 0, self::con()->comps->opts_lookup->getIpAutoBlockOffenseLimit() );

		$level = VisitorStanding::NORMAL;
		$reasons = [];

		if ( $limit > 0 ) {
			if ( $hostileUntil > 0 ) {
				$level = VisitorStanding::HOSTILE;
				$reasons[] = VisitorStanding::REASON_HOSTILE_HOLD;
			}
			elseif ( $evidence >= $limit*EvidenceWeights::UNITS_PER_OFFENSE ) {
				$level = VisitorStanding::HOSTILE;
				$reasons[] = VisitorStanding::REASON_OFFENSES;
			}
			elseif ( $evidence >= (int)\ceil( $limit/3 )*EvidenceWeights::UNITS_PER_OFFENSE ) {
				$level = VisitorStanding::DOUBTFUL;
				$reasons[] = VisitorStanding::REASON_OFFENSES;
			}
		}

		if ( $level === VisitorStanding::HOSTILE && $this->isHighReputation() ) {
			$level = VisitorStanding::DOUBTFUL;
			$reasons[] = VisitorStanding::REASON_HIGH_REPUTATION;
		}

		if ( self::con()->comps->opts_lookup->enabledCrowdSecAutoBlock() && $status->hasCrowdsecRule() ) {
			if ( $status->hasCrowdsecBlock() ) {
				$level = VisitorStanding::HOSTILE;
				$reasons[] = VisitorStanding::REASON_CROWDSEC;
			}
			else {
				// Passed the CrowdSec unblock: doubtful, and hostile again at half the offense limit.
				$reasons[] = VisitorStanding::REASON_CROWDSEC_UNBLOCKED;
				$level = ( $limit > 0 && $evidence*2 >= $limit*EvidenceWeights::UNITS_PER_OFFENSE )
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
