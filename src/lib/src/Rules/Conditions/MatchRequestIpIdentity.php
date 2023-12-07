<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\MatchIpIdsUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestUseragentUnavailableException;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * @property string $match_ip_id
 */
class MatchRequestIpIdentity extends Base {

	use Traits\RequestIP;
	use Traits\UserAgent;
	use Traits\TypeRequest;

	public const SLUG = 'match_request_ip_identity';

	public function getDescription() :string {
		return __( "Does the current request originate from a given set of services/providers.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_ip_id ) ) {
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

		return $id === $this->match_ip_id;
	}

	public function getParamsDef() :array {
		return [
			'match_ip_id' => [
				'type'  => 'string',
				'label' => __( 'IP ID To Match', 'wp-simple-firewall' ),
			],
		];
	}
}