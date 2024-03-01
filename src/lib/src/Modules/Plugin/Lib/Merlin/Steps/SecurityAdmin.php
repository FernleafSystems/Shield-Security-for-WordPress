<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\ToggleSecAdminStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class SecurityAdmin extends Base {

	public const SLUG = 'security_admin';

	public function processStepFormSubmit( array $form ) :Response {
		$con = self::con();

		$pin = $form[ 'SecAdminPIN' ] ?? '';
		if ( empty( $pin ) ) {
			throw new \Exception( 'Please provide a Security PIN, or proceed to the next step.' );
		}
		if ( $pin !== ( $form[ 'SecAdminPINConfirm' ] ?? '' ) ) {
			throw new \Exception( 'The Security PINs provided do not match.' );
		}
		if ( !$con->isPluginAdmin() ) {
			throw new \Exception( "You don't have permission to update the Security PIN." );
		}

		$con->opts
			->optSet( 'enable_'.$con->cfg->configuration->modFromOpt( 'admin_access_key' ), 'Y' )
			->optSet( 'admin_access_key', wp_hash_password( $pin ) )
			->store();

		( new ToggleSecAdminStatus() )->turnOn();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = __( 'Security Admin is now active', 'wp-simple-firewall' );
		return $resp;
	}

	public function getName() :string {
		return __( 'Security Admin', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Protect Your Shield Plugin From Tampering", 'wp-simple-firewall' ),
			],
		];
	}

	public function skipStep() :bool {
		return self::con()->comps->sec_admin->isEnabledSecAdmin();
	}
}