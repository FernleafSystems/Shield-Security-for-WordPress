<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class CallUserFuncArray extends Base {

	public const SLUG = 'call_user_func_array';

	public function execResponse() :bool {
		\call_user_func_array( $this->params[ 'callback' ], $this->params[ 'args' ] );
		return true;
	}

	public function getParamsDef() :array {
		return [
			'callback' => [
				'type'  => EnumParameters::TYPE_CALLBACK,
				'label' => __( 'Callback', 'wp-simple-firewall' ),
			],
			'args'     => [
				'type'    => EnumParameters::TYPE_ARRAY,
				'default' => [],
				'label'   => __( 'Callback Arguments', 'wp-simple-firewall' ),
			],
		];
	}
}