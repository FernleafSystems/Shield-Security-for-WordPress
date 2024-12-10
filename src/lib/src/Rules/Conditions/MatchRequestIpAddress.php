<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumMatchTypes,
	EnumParameters
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\IpsToMatchUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;
use FernleafSystems\Wordpress\Services\Services;

class MatchRequestIpAddress extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_ip_address';

	/**
	 * @throws IpsToMatchUnavailableException
	 * @throws \Exception
	 */
	protected function execConditionCheck() :bool {
		$reqIP = $this->req->ip;
		if ( !\is_string( $reqIP ) || !Services::IP()->isValidIpOrRange( $reqIP ) ) {
			throw new IpsToMatchUnavailableException();
		}
		return ( new PerformConditionMatch( $reqIP, $this->p->match_ip, $this->p->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the current request originate match the given IP Address.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type' => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForIPs(),
				'default'   => EnumMatchTypes::MATCH_TYPE_IP_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_ip'   => [
				'type'  => EnumParameters::TYPE_IP_ADDRESS,
				'label' => __( 'IP Address To Match', 'wp-simple-firewall' ),
			],
		];
	}
}