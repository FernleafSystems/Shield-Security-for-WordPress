<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class SecurityBadge extends Base {

	public const SLUG = 'security_badge';

	public function getName() :string {
		return __( 'Badge', 'wp-simple-firewall' );
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

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$value = $form[ 'SecurityPluginBadge' ] ?? '';
		if ( empty( $value ) ) {
			throw new \Exception( 'Please select one of the options, or proceed to the next step.' );
		}

		$mod = $this->getCon()->getModule_Plugin();

		$toEnable = $value === 'Y';
		if ( $toEnable ) { // we don't disable the whole module
			$mod->setIsMainFeatureEnabled( true );
		}
		$mod->getOptions()->setOpt( 'display_plugin_badge', $toEnable ? 'Y' : 'N' );
		$mod->saveModOptions();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = $toEnable ? __( 'The Security Badge will be displayed to your visitors', 'wp-simple-firewall' )
			: __( "The Security Badge won't be displayed to your visitors", 'wp-simple-firewall' );
		return $resp;
	}
}