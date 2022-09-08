<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class CommentSpam extends Base {

	const SLUG = 'comment_spam';

	public function getName() :string {
		return 'SPAM';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Block 100% Bots Comment SPAM Without CAPTCHAs!", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'video_id' => '269193270'
			],
		];
	}
}