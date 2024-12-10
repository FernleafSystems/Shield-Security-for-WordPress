<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class CommentSpam extends Base {

	public const SLUG = 'comment_spam';

	public function getName() :string {
		return 'SPAM';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Block 100% Bots Comment SPAM With silentCAPTCHA", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'video_id' => '269193270'
			],
		];
	}

	public function processStepFormSubmit( array $form ) :Response {
		$value = $form[ 'CommentsFilterOption' ] ?? '';
		$toEnable = $value === 'Y';
		self::con()->opts->optSet( 'enable_antibot_comments', $toEnable ? 'Y' : 'N' );

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = $toEnable ? __( 'Bot comment SPAM will now be blocked', 'wp-simple-firewall' )
			: __( 'Bot comment SPAM will not be blocked', 'wp-simple-firewall' );
		return $resp;
	}
}