<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class GuidedSetupWelcome extends Base {

	public const SLUG = 'guided_setup_welcome';

	public function getName() :string {
		return 'Welcome';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Welcome to Shield Security's Guided Setup Wizard", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'video_id' => '269191603'
			],
		];
	}
}