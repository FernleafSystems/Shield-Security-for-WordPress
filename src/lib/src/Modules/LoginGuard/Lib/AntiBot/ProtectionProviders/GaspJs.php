<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Services\Services;

class GaspJs extends BaseProtectionProvider {

	public function setup() {
		if ( Services::Request()->query( 'wp_service_worker', 0 ) != 1 ) {
			add_action( 'wp', [ $this, 'enqueueJS' ] );
			add_action( 'login_init', [ $this, 'enqueueJS' ] );
		}
	}

	public function enqueueJS() {
		add_filter( 'shield/custom_enqueue_assets', function ( array $assets ) {
			$assets[] = 'login_guard';
			return $assets;
		} );
	}

	public function performCheck( $formProvider ) {
		if ( $this->isFactorTested() ) {
			return;
		}
		$req = Services::Request();

		$this->setFactorTested( true );

		$gasp = $req->post( $this->mod()->getGaspKey() );

		$username = $formProvider->getUserToAudit();
		$action = $formProvider->getActionToAudit();

		$valid = false;
		$errorMsg = '';
		if ( empty( $gasp ) ) {
			self::con()->fireEvent(
				'botbox_fail',
				[
					'audit_params' => [
						'user_login' => $username,
						'action'     => $action,
					]
				]
			);
			$errorMsg = __( "Please check that box to say you're human, and not a bot.", 'wp-simple-firewall' );
		}
		elseif ( !empty( $req->post( 'icwp_wpsf_login_email' ) ) ) {
			self::con()->fireEvent(
				'honeypot_fail',
				[
					'audit_params' => [
						'user_login' => $username,
						'action'     => $action,
					]
				]
			);
			$errorMsg = __( 'You appear to be a bot.', 'wp-simple-firewall' );
		}
		else {
			$valid = true;
		}

		if ( !$valid ) {
			$this->processFailure();
			throw new \Exception( $errorMsg );
		}
	}

	public function buildFormInsert( $formProvider ) :string {
		return self::con()->action_router->render( Actions\Render\Legacy\GaspJs::SLUG );
	}

	protected function isFactorJsRequired() :bool {
		return parent::isFactorJsRequired() || !empty( $this->opts()->getAntiBotFormSelectors() );
	}
}