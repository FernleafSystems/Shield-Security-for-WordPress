<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_LoginProtect_Gasp extends ICWP_WPSF_Processor_LoginProtect_Base {

	public function run() {
		parent::run();
		( new LoginGuard\Lib\AntiBot\IncludeJs() )
			->setMod( $this->getMod() )
			->run();
	}

	/**
	 * @return string
	 */
	protected function buildFormItems() {
		return $this->getGaspLoginHtml();
	}

	/**
	 * @return string
	 */
	private function getGaspLoginHtml() {
		return $this->getMod()->renderTemplate(
			'/snippets/anti_bot/gasp_js.twig',
			[
				'strings' => [
					'loading' => __( 'Loading', 'wp-simple-firewall' )
				]
			]
		);
	}

	/**
	 * @throws \Exception
	 */
	protected function performCheckWithException() {
		if ( $this->isFactorTested() ) {
			return;
		}

		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		$this->setFactorTested( true );

		$oReq = Services::Request();
		$sGaspCheckBox = $oReq->post( $oMod->getGaspKey() );
		$sHoney = $oReq->post( 'icwp_wpsf_login_email' );

		$sUsername = $this->getUserToAudit();
		$sActionAttempted = $this->getActionToAudit();

		$bValid = false;
		$sError = '';
		if ( empty( $sGaspCheckBox ) ) {
			$this->getCon()->fireEvent(
				'botbox_fail',
				[
					'audit' => [
						'user_login' => $sUsername,
						'action'     => $sActionAttempted,
					]
				]
			);
			$sError = __( "You must check that box to say you're not a bot.", 'wp-simple-firewall' );
		}
		elseif ( !empty( $sHoney ) ) {
			$this->getCon()->fireEvent(
				'honeypot_fail',
				[
					'audit' => [
						'user_login' => $sUsername,
						'action'     => $sActionAttempted,
					]
				]
			);
			$sError = __( 'You appear to be a bot.', 'wp-simple-firewall' );
		}
		else {
			$bValid = true;
		}

		if ( !$bValid ) {
			$this->processFailure();
			throw new \Exception( $sError );
		}
	}
}