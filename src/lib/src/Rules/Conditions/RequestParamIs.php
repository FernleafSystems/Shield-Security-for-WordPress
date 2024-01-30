<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

/**
 * @deprecated 18.6
 */
abstract class RequestParamIs extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_param_is';

	protected function execConditionCheck() :bool {
		return false;
	}

	public function getDescription() :string {
		return __( 'Does the value of the given request parameter match against the given patterns.', 'wp-simple-firewall' );
	}

	/**
	 * @return mixed|null
	 */
	protected function getRequestParamValue() {
		return null;
	}

	public function getParamsDef() :array {
		return [
			'match_param'    => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Match Parameter Name', 'wp-simple-firewall' ),
			],
			'match_patterns' => [
				'type'  => EnumParameters::TYPE_ARRAY,
				'label' => __( 'Match Parameter Value Pattern', 'wp-simple-firewall' ),
			],
		];
	}
}