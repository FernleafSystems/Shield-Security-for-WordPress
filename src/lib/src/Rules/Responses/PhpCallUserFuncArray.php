<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class PhpCallUserFuncArray extends Base {

	public const SLUG = 'php_call_user_func_array';

	public function execResponse() :void {
		\call_user_func_array( $this->p->callback, $this->p->args );
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