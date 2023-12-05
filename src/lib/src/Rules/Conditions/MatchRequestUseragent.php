<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\MatchUseragentsUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestUseragentUnavailableException;

/**
 * @property string[] $match_useragents
 */
class MatchRequestUseragent extends Base {

	use Traits\UserAgent;

	public const SLUG = 'match_request_useragent';

	public function getName() :string {
		return __( 'Does the request useragent match the given useragents.', 'wp-simple-firewall' );
	}

	/**
	 * @throws RequestUseragentUnavailableException
	 * @throws MatchUseragentsUnavailableException
	 */
	protected function matchUserAgent() :bool {
		$uAgents = $this->match_useragents;
		if ( empty( $uAgents ) ) {
			throw new MatchUseragentsUnavailableException();
		}

		$match = false;
		foreach ( $uAgents as $possibleAgent ) {
			if ( \stripos( $this->getUserAgent(), $possibleAgent ) !== false ) {
				$match = true;
				$this->addConditionTriggerMeta( 'matched_useragent', $possibleAgent );
				break;
			}
		}
		return $match;
	}

	protected function execConditionCheck() :bool {
		try {
			$detected = $this->matchUserAgent();
		}
		catch ( \Exception $e ) {
			$detected = false;
		}
		return $detected;
	}
}