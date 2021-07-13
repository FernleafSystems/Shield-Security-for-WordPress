<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
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
				/** @var LoginGuard\ModCon $mod */
				$mod = $this->getMod();
				/** @var LoginGuard\Options $opts */
				$opts = $this->getOptions();

				$ts = Services::Request()->ts();
				$nonce = $mod->getAjaxActionData( 'comment_token'.Services::IP()->getRequestIp() );
				$nonce[ 'ts' ] = $ts;
				$nonce[ 'post_id' ] = Services::WpPost()->getCurrentPostId();

				$localz[] = [
					'shield/loginbot',
					'icwp_wpsf_vars_lpantibot',
					[
						'form_selectors' => implode( ',', $opts->getAntiBotFormSelectors() ),
						'uniq'           => preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
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

	/**
	 * @inheritDoc
	 */
	public function performCheck( $form ) {
		if ( $this->isFactorTested() ) {
			return;
		}

		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$req = Services::Request();

		$this->setFactorTested( true );

		$gasp = $req->post( $mod->getGaspKey() );

		$username = $form->getUserToAudit();
		$action = $form->getActionToAudit();

		$valid = false;
		$errorMsg = '';
		if ( empty( $gasp ) ) {
			$this->getCon()->fireEvent(
				'botbox_fail',
				[
					'audit' => [
						'user_login' => $username,
						'action'     => $action,
					]
				]
			);
			$errorMsg = __( "Please check that box to say you're human, and not a bot.", 'wp-simple-firewall' );
		}
		elseif ( !empty( $req->post( 'icwp_wpsf_login_email' ) ) ) {
			$this->getCon()->fireEvent(
				'honeypot_fail',
				[
					'audit' => [
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

	/**
	 * @inheritDoc
	 */
	public function buildFormInsert( $oFormProvider ) {
		return $this->getMod()->renderTemplate(
			'/snippets/anti_bot/gasp_js.twig',
			[
				'strings' => [
					'loading' => __( 'Loading', 'wp-simple-firewall' )
				]
			]
		);
	}

	protected function isFactorJsRequired() :bool {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
		return parent::isFactorJsRequired() || !empty( $opts->getAntiBotFormSelectors() );
	}
}