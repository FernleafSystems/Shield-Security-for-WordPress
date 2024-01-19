<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class MatchRequestIpIdentity extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_ip_identity';

	protected function execConditionCheck() :bool {
		$this->addConditionTriggerMeta( 'ip_id', $this->req->ip_id );
		return ( new Utility\PerformConditionMatch(
			$this->req->ip_id,
			$this->p->match_ip_id,
			$this->p->match_type
		) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the current request originate from a given set of services/providers.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		$providers = $this->enumProviders();
		return [
			'match_type'  => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => [
					Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				],
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_ip_id' => [
				'type'        => Enum\EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $providers ),
				'enum_labels' => $providers,
				'label'       => __( 'IP ID To Match', 'wp-simple-firewall' ),
			],
		];
	}

	private function enumProviders() :array {
		$providers = \array_map(
			function ( array $provider ) {
				return $provider[ 'name' ] ?? 'Unknown';
			},
			Services::ServiceProviders()->getProviders_Flat()
		);
		\natcasesort( $providers );
		return \array_merge( [
			IpID::LOOPBACK    => '- Server Loopback -',
			IpID::THIS_SERVER => '- This Server -',
		], $providers );
	}
}