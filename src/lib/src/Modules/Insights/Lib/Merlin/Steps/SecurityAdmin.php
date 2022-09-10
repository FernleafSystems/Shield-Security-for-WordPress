<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class SecurityAdmin extends Base {

	const SLUG = 'security_admin';

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {

		$pin = $form[ 'SecAdminPIN' ] ?? '';
		if ( empty( $pin ) ) {
			throw new \Exception( 'Please provide a Security PIN, or proceed to the next step.' );
		}
		if ( $pin !== ( $form[ 'SecAdminPINConfirm' ] ?? '' ) ) {
			throw new \Exception( 'The Security PINs provided do not match.' );
		}
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( "You don't have permission to update the Security PIN." );
		}

		$mod = $this->getCon()->getModule_SecAdmin();
		$mod->setIsMainFeatureEnabled( true );
		$mod->getOptions()->setOpt( 'admin_access_key', md5( $pin ) );
		( new Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\ToggleSecAdminStatus() )
			->setMod( $mod )
			->turnOn();
		$mod->saveModOptions();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->msg = __( 'Security Admin is now active', 'wp-simple-firewall' );
		return $resp;
	}

	public function getName() :string {
		return 'Security Admin';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Protect Your Shield Plugin From Tampering", 'wp-simple-firewall' ),
			],
			'vars'    => [
			]
		];
	}

	public function skipStep() :bool {
		return $this->getCon()
					->getModule_SecAdmin()
					->getSecurityAdminController()
					->isEnabledSecAdmin();
	}
}