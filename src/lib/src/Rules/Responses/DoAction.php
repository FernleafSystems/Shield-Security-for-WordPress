<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class DoAction extends Base {

	public const SLUG = 'do_action';

	public function execResponse() :bool {
		do_action( $this->params[ 'hook' ] );
		return true;
	}

	public function getParamsDef() :array {
		return [
			'hook' => [
				'type'  => 'string',
				'label' => __( 'Hook Name', 'wp-simple-firewall' ),
			],
		];
	}
}