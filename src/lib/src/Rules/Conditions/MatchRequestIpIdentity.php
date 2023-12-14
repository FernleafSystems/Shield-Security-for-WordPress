<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\MatchIpIdsUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestUseragentUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * @property string $match_type
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

		return ( new PerformConditionMatch( $id, $this->match_ip_id, $this->match_type ) )->doMatch();
	}

	public function getParamsDef() :array {
		$providers = $this->enumProviders();
		return [
			'match_type'  => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => [
					EnumMatchTypes::MATCH_TYPE_EQUALS,
				],
				'default'   => EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_ip_id' => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $providers ),
				'enum_labels' => $providers,
				'label'       => __( 'IP ID To Match', 'wp-simple-firewall' ),
			],
		];
	}

	private function enumProviders() :array {
		$providers = [];
		foreach ( Services::ServiceProviders()->getProviders() as $category ) {
			$providers = \array_merge( $providers, \array_keys( $category ) );
		}
		$providers = \array_unique( $providers );
		$providers = \array_combine( $providers, $providers );

		return \array_merge( [
			IpID::LOOPBACK    => '- Server Loopback -',
			IpID::THIS_SERVER => '- This Server -',
			IpID::UNKNOWN     => '- Unknown -',
		], $providers );
	}
}