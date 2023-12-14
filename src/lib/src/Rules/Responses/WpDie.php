<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class WpDie extends Base {

	public const SLUG = 'wp_die';

	public function execResponse() :bool {
		wp_die( $this->params[ 'message' ] );
	}

	public function getParamsDef() :array {
		return [
			'message' => [
				'type'    => EnumParameters::TYPE_STRING,
				'default' => '',
				'label'   => __( 'User Display Message', 'wp-simple-firewall' ),
			],
		];
	}
}