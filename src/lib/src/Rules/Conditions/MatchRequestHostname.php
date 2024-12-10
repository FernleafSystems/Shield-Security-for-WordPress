<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumMatchTypes,
	EnumParameters
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\HostnameUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;

class MatchRequestHostname extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_hostname';

	/**
	 * @throws HostnameUnavailableException
	 * @throws \Exception
	 */
	protected function execConditionCheck() :bool {
		if ( $this->req->getHostname() === $this->req->ip ) {
			throw new HostnameUnavailableException();
		}
		return ( new PerformConditionMatch( $this->req->getHostname(), $this->p->match_hostname, $this->p->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the hostname of the requesting IP match the given hostname.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type'     => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_IP_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_hostname' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Hostname To Match', 'wp-simple-firewall' ),
			],
		];
	}
}