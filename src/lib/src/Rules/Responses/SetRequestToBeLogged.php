<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class SetRequestToBeLogged extends Base {

	public function execResponse() :void {
		add_filter( 'shield/is_log_traffic', $this->p->do_log ? '__return_true' : '__return_false', $this->p->priority );
	}

	public function getParamsDef() :array {
		return [
			'do_log'        => [
				'type'    => EnumParameters::TYPE_BOOL,
				'label'   => __( 'Whether to log request', 'wp-simple-firewall' ),
				'default' => true,
			],
			'hook_priority' => [
				'type'    => EnumParameters::TYPE_INT,
				'label'   => __( 'Filter Priority', 'wp-simple-firewall' ),
				'default' => 10,
			],
		];
	}
}