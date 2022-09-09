<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\ToggleSecAdminStatus;

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

	public function processStepFormSubmit( array $form ) :bool {
		$value = $form[ 'CommentsFilterOption' ] ?? '';
		if ( empty( $value ) ) {
			throw new \Exception( 'No option setting provided.' );
		}

		$mod = $this->getCon()->getModule_Comments();

		$toEnable = $value === 'Y';
		if ( $toEnable ) { // we don't disable the whole module
			$mod->setIsMainFeatureEnabled( true );
		}
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Options $optsComm */
		$optsComm = $mod->getOptions();
		$optsComm->setEnabledAntiBot( $toEnable );

		$mod->saveModOptions();
		return true;
	}
}