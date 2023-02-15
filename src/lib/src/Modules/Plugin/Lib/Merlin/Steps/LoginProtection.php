<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class LoginProtection extends Base {

	public const SLUG = 'login_protection';

	public function getName() :string {
		return __( 'Login' );
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

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$value = $form[ 'LoginProtectOption' ] ?? '';
		if ( empty( $value ) ) {
			throw new \Exception( 'Please select one of the options, or proceed to the next step.' );
		}

		$mod = $this->getCon()->getModule_LoginGuard();

		$toEnable = $value === 'Y';
		if ( $toEnable ) { // we don't disable the whole module
			$mod->setIsMainFeatureEnabled( true );
		}
		$mod->getOptions()->setOpt( 'enable_antibot_check', $toEnable ? 'Y' : 'N' );
		$mod->saveModOptions();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = $toEnable ? __( 'Bot comment SPAM will now be blocked', 'wp-simple-firewall' )
			: __( 'Bot comment SPAM will not be blocked', 'wp-simple-firewall' );
		return $resp;
	}
}