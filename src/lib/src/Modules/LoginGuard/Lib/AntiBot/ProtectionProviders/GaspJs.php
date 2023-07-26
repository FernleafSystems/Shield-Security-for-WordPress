<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Services\Services;

class GaspJs extends BaseProtectionProvider {

	public function setup() {
		if ( Services::Request()->query( 'wp_service_worker', 0 ) != 1 ) {
			add_action( 'wp', [ $this, 'enqueueJS' ] );
			add_action( 'login_init', [ $this, 'enqueueJS' ] );
		}
	}

	public function enqueueJS() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/loginbot';

			add_filter( 'shield/custom_localisations', function ( array $localz ) {
				$mod = $this->mod();
				$opts = $this->opts();

				$localz[] = [
					'shield/loginbot',
					'icwp_wpsf_vars_lpantibot',
					[
						'form_selectors' => \implode( ',', $opts->getAntiBotFormSelectors() ),
						'uniq'           => \preg_replace( '#[^\da-zA-Z]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
						'cbname'         => $mod->getGaspKey(),
						'strings'        => [
							'label'   => $mod->getTextImAHuman(),
							'alert'   => $mod->getTextPleaseCheckBox(),
							'loading' => __( 'Loading', 'wp-simple-firewall' )
						],
						'flags'          => [
							'gasp'    => $opts->isEnabledGaspCheck(),
							'captcha' => $mod->isEnabledCaptcha(),
						]
					]
				];
				return $localz;
			} );

			return $enqueues;
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
			$this->con()->fireEvent(
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
			$this->con()->fireEvent(
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
		return $this->con()->action_router->render( Actions\Render\Legacy\GaspJs::SLUG );
	}

	protected function isFactorJsRequired() :bool {
		return parent::isFactorJsRequired() || !empty( $this->opts()->getAntiBotFormSelectors() );
	}
}