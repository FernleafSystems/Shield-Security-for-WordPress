<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class LoginProtection extends Base {

	const SLUG = 'login_protection';

	public function getName() :string {
		return 'Login Protection';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Brute Force Login Protection", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'video_id' => '269191603'
			],
		];
	}
}