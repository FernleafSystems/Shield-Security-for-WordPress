<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\MatchIpIdsUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestUseragentUnavailableException;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * @property string[] $match_ip_ids
 */
class MatchRequestIpIdentity extends Base {

	use Traits\RequestIP;
	use Traits\UserAgent;

	public const SLUG = 'match_request_ip_identity';

	public function getDescription() :string {
		return __( "Does the current request originate from a given set of services/providers.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$matchIDs = $this->match_ip_ids;
		if ( empty( $matchIDs ) ) {
			throw new MatchIpIdsUnavailableException();
		}

		try {
			$ua = $this->getUserAgent();
		}
		catch ( RequestUseragentUnavailableException $e ) {
			$ua = '';
		}

		$id = ( new IpID( $this->getRequestIP(), $ua ) )->run()[ 0 ];
		$this->addConditionTriggerMeta( 'ip_id', $id );

		return \in_array( $id, $matchIDs );
	}
}