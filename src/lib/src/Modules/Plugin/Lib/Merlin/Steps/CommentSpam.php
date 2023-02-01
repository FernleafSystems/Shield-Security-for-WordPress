<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class CommentSpam extends Base {

	public const SLUG = 'comment_spam';

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

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$value = $form[ 'CommentsFilterOption' ] ?? '';
		if ( empty( $value ) ) {
			throw new \Exception( 'Please select one of the options, or proceed to the next step.' );
		}

		$mod = $this->getCon()->getModule_Comments();

		$toEnable = $value === 'Y';
		if ( $toEnable ) { // we don't disable the whole module
			$mod->setIsMainFeatureEnabled( true );
		}
		/** @var Shield\Modules\CommentsFilter\Options $opts */
		$opts = $mod->getOptions();
		$opts->setEnabledAntiBot( $toEnable );
		$mod->saveModOptions();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = $toEnable ? __( 'Bot comment SPAM will now be blocked', 'wp-simple-firewall' )
			: __( 'Bot comment SPAM will not be blocked', 'wp-simple-firewall' );
		return $resp;
	}
}