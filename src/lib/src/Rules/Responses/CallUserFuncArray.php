<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class CallUserFuncArray extends Base {

	public const SLUG = 'call_user_func_array';

	public function execResponse() :bool {
		\call_user_func_array( $this->params[ 'callback' ], $this->params[ 'args' ] );
		return true;
	}

	public function getParamsDef() :array {
		return [
			'callback' => [
				'type'  => 'callback',
				'label' => __( 'Callback', 'wp-simple-firewall' ),
			],
			'args'     => [
				'type'    => 'array',
				'default' => [],
				'label'   => __( 'Callback Arguments', 'wp-simple-firewall' ),
			],
		];
	}
}