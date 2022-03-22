<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\UserAgent;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\MatchIpIdsUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestIpUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestUseragentUnavailableException;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * @property string[] $match_ip_ids
 * @property string[] $match_not_ip_ids
 */
class MatchRequestIPIdentity extends Base {

	use RequestIP;
	use UserAgent;

	const SLUG = 'match_request_ip_identity';

	protected function execConditionCheck() :bool {
		try {
			$result = $this->matchRequestIPIdentity();
		}
		catch ( \Exception $e ) {
			$result = false;
		}
		return $result;
	}

	/**
	 * @throws MatchIpIdsUnavailableException
	 * @throws RequestIpUnavailableException
	 */
	protected function matchRequestIPIdentity() :bool {
		$matchIDs = $this->match_ip_ids;
		$matchNotIDs = $this->match_not_ip_ids;
		if ( empty( $matchIDs ) && empty( $matchNotIDs ) ) {
			throw new MatchIpIdsUnavailableException();
		}

		try {
			$ua = $this->getUserAgent();
		}
		catch ( RequestUseragentUnavailableException $e ) {
			$ua = '';
		}

		$match = false;
		$id = ( new IpID( $this->getRequestIP(), $ua ) )->run()[ 0 ];

		if ( !empty( $matchIDs ) ) {
			$match = in_array( $id, $matchIDs );
		}
		elseif ( !empty( $matchNotIDs ) ) {
			$match = in_array( $id, $matchNotIDs );
		}
		return $match;
	}
}