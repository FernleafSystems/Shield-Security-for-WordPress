<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class MarkRequestAsTrustedService extends Base {

	public function execResponse() :void {
		add_filter( 'shield/is_trusted_request', function () {
			return (bool)$this->p->is_trusted;
		}, $this->p->priority, 0 );
	}

	public function getParamsDef() :array {
		return [
			'is_trusted' => [
				'type'    => EnumParameters::TYPE_BOOL,
				'label'   => __( 'Whether request is trusted', 'wp-simple-firewall' ),
				'default' => true,
			],
			'priority'   => [
				'type'    => EnumParameters::TYPE_INT,
				'label'   => __( 'Filter Priority', 'wp-simple-firewall' ),
				'default' => 10,
			],
		];
	}
}