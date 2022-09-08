<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class SecurityAdmin extends Base {

	const SLUG = 'security_admin';

	public function getName() :string {
		return 'Security Admin';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Setup Security Admin Protection", 'wp-simple-firewall' ),
			],
			'vars'    => [
			]
		];
	}
}