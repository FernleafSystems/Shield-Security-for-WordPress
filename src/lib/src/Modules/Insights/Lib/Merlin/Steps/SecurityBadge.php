<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class SecurityBadge extends Base {

	const SLUG = 'security_badge';

	public function getName() :string {
		return 'Security Badge';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Show Your Visitors That You Take Security Seriously!", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'video_id' => '552430272'
			],
		];
	}
}