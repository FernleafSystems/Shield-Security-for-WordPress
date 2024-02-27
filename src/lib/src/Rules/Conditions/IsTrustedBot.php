<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\TrustedServices;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum
};

class IsTrustedBot extends Base {

	use Traits\TypeBots;

	public function getDescription() :string {
		return __( 'Is the request a bot that originates from a trusted service provider.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_OR,
			'conditions' => \array_map(
				function ( string $ID ) {
					return [
						'conditions' => Conditions\MatchRequestIpIdentity::class,
						'params'     => [
							'match_type'  => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
							'match_ip_id' => $ID,
						],
					];
				},
				( new TrustedServices() )->enum()
			),
		];
	}
}