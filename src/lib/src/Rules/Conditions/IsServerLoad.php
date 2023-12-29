<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;

/**
 * @property string $match_type
 * @property string $load
 * @property string $range
 */
class IsServerLoad extends Base {

	use Traits\TypeRequest;

	protected function execConditionCheck() :bool {
		$load = \function_exists( '\sys_getloadavg' ) ? \sys_getloadavg() : null;
		return \is_array( $load ) && isset( $load[ (int)$this->range ] )
			   && ( new PerformConditionMatch( $load[ (int)$this->range ], $this->load, $this->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Is a given theme installed & active.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		$ranges = [
			'1'  => __( '1-minute', 'wp-simple-firewall' ),
			'5'  => __( '5-minute', 'wp-simple-firewall' ),
			'15' => __( '15-minute', 'wp-simple-firewall' ),
		];
		return [
			'match_type' => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => [
					EnumMatchTypes::MATCH_TYPE_LESS_THAN,
					EnumMatchTypes::MATCH_TYPE_GREATER_THAN,
				],
				'default'   => EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'load'       => [
				'type'    => EnumParameters::TYPE_INT,
				'default' => 1,
				'label'   => __( 'Load', 'wp-simple-firewall' ),
			],
			'range'      => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $ranges ),
				'enum_labels' => $ranges,
				'default'     => \key( $ranges ),
				'label'       => __( 'Time Range', 'wp-simple-firewall' ),
			],
		];
	}
}