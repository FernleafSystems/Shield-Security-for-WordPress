<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	IpsToMatchUnavailableException,
	RequestIpUnavailableException
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $match_type
 * @property string $match_ip
 */
class MatchRequestIpAddress extends Base {

	use Traits\RequestIP;
	use Traits\TypeRequest;

	public const SLUG = 'match_request_ip_address';

	public function getDescription() :string {
		return __( 'Does the current request originate match the given IP Address.', 'wp-simple-firewall' );
	}

	/**
	 * @throws IpsToMatchUnavailableException
	 * @throws RequestIpUnavailableException
	 * @throws \Exception
	 */
	protected function execConditionCheck() :bool {
		$ip = $this->match_ip;
		if ( !\is_string( $ip ) || !Services::IP()->isValidIpOrRange( $ip ) ) {
			throw new IpsToMatchUnavailableException();
		}
		return ( new PerformConditionMatch( $this->getRequestIP(), $ip, $this->match_type ) )->doMatch();
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