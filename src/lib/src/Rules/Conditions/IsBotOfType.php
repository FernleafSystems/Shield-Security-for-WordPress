<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class IsBotOfType extends Base {

	use Traits\TypeBots;

	public function getDescription() :string {
		return __( 'Is visiting bot of the given type.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return \in_array(
			( new IpID( $this->req->ip, $this->req->useragent ) )->run()[ 0 ],
			Services::ServiceProviders()->getProvidersOfType( $this->p->match_type )
		);
	}

	public function getParamsDef() :array {
		$botTypes = [
			'search' => __( 'Search Engine', 'wp-simple-firewall' ),
			'uptime' => __( 'Uptime Monitoring', 'wp-simple-firewall' ),
		];
		return [
			'match_type' => [
				'type'        => Enum\EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $botTypes ),
				'enum_labels' => $botTypes,
				'default'     => 'search',
				'label'       => __( 'Match Bot Type', 'wp-simple-firewall' ),
			],
		];
	}
}