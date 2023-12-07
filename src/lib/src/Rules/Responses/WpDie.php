<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class WpDie extends Base {

	public const SLUG = 'wp_die';

	public function execResponse() :bool {
		wp_die( $this->params[ 'message' ] );
	}

	public function getParamsDef() :array {
		return [
			'message' => [
				'type'    => 'string',
				'default' => '',
				'label'   => __( 'User Display Message', 'wp-simple-firewall' ),
			],
		];
	}
}