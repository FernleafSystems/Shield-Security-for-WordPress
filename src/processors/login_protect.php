<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_LoginProtect extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

		// XML-RPC Compatibility
		if ( Services::WpGeneral()->isXmlrpc() && $oMod->isXmlrpcBypass() ) {
			return;
		}

		// So we can allow access to the login pages if IP is whitelisted
		if ( $oMod->isCustomLoginPathEnabled() ) {
			$this->getSubProRename()->run();
		}

		if ( !$oMod->isVisitorWhitelisted() ) {
			if ( $oMod->isEnabledGaspCheck() ) {
				$this->getSubProGasp()->run();
			}

			if ( $oMod->isCooldownEnabled() && Services::Request()->isPost() ) {
				$this->getSubProCooldown()->run();
			}

			if ( $oMod->isGoogleRecaptchaEnabled() ) {
				$this->getSubProRecaptcha()->run();
			}

			$this->getSubProIntent()->run();
		}
	}

	public function onWpEnqueueJs() {
		/** @var ICWP_WPSF_FeatureHandler_LoginProtect $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isEnabledBotJs() ) {
			$oConn = $this->getCon();

			$sAsset = 'shield-antibot';
			$sUnique = $this->prefix( $sAsset );
			wp_register_script(
				$sUnique,
				$oConn->getPluginUrl_Js( $sAsset ),
				[ 'jquery' ],
				$oConn->getVersion(),
				true
			);
			wp_enqueue_script( $sUnique );

			wp_localize_script(
				$sUnique,
				'icwp_wpsf_vars_lpantibot',
				[
					'form_selectors' => implode( ',', $oFO->getAntiBotFormSelectors() ),
					'uniq'           => preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
					'cbname'         => $oFO->getGaspKey(),
					'strings'        => [
						'label' => $oFO->getTextImAHuman(),
						'alert' => $oFO->getTextPleaseCheckBox(),
					],
					'flags'          => [
						'gasp'  => $oFO->isEnabledGaspCheck(),
						'recap' => $oFO->isGoogleRecaptchaEnabled(),
					]
				]
			);

			if ( $oFO->isGoogleRecaptchaEnabled() ) {
				$this->setRecaptchaToEnqueue();
			}
		}
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		$sSlug = $this->getMod()->getSlug();
		$aData[ $sSlug ][ 'options' ][ 'email_can_send_verified_at' ]
			= ( $aData[ $sSlug ][ 'options' ][ 'email_can_send_verified_at' ] > 0 ) ? 1 : 0;
		return $aData;
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'cooldown'  => 'ICWP_WPSF_Processor_LoginProtect_Cooldown',
			'gasp'      => 'ICWP_WPSF_Processor_LoginProtect_Gasp',
			'intent'    => 'ICWP_WPSF_Processor_LoginProtect_Intent',
			'recaptcha' => 'ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha',
			'rename'    => 'ICWP_WPSF_Processor_LoginProtect_WpLogin',
		];
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Cooldown
	 */
	private function getSubProCooldown() {
		return $this->getSubPro( 'cooldown' );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Gasp
	 */
	private function getSubProGasp() {
		return $this->getSubPro( 'gasp' );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_Intent
	 */
	public function getSubProIntent() {
		return $this->getSubPro( 'intent' );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha
	 */
	private function getSubProRecaptcha() {
		return $this->getSubPro( 'recaptcha' );
	}

	/**
	 * @return ICWP_WPSF_Processor_LoginProtect_WpLogin
	 */
	private function getSubProRename() {
		return $this->getSubPro( 'rename' );
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 * @deprecated
	 */
	public function addNotice_email_verification_sent() {
		return;
	}
}