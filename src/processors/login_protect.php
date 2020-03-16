<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_LoginProtect extends Modules\BaseShield\ShieldProcessor {

	/**
	 */
	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		/** @var Modules\LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		// XML-RPC Compatibility
		if ( Services::WpGeneral()->isXmlrpc() && $oMod->isXmlrpcBypass() ) {
			return;
		}

		// So we can allow access to the login pages if IP is whitelisted
		if ( $oMod->isCustomLoginPathEnabled() ) {
			$this->getSubPro( 'rename' )->execute();
		}

		if ( !$oMod->isVisitorWhitelisted() ) {
			if ( $oMod->isEnabledGaspCheck() ) {
				$this->getSubPro( 'gasp' )->execute();
			}

			if ( $oOpts->isCooldownEnabled() ) {
				if ( Services::Request()->isPost() ) {
					$this->getSubPro( 'cooldown' )->execute();
				}
			}

			if ( $oMod->isGoogleRecaptchaEnabled() ) {
				$this->getSubPro( 'recaptcha' )->execute();
			}

			$oMod->getLoginIntentController()->run();
		}
	}

	public function onWpEnqueueJs() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

		if ( $oMod->isEnabledBotJs() ) {
			$oConn = $this->getCon();

			$sAsset = 'shield-antibot';
			$sUnique = $oMod->prefix( $sAsset );
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
					'form_selectors' => implode( ',', $oMod->getAntiBotFormSelectors() ),
					'uniq'           => preg_replace( '#[^a-zA-Z0-9]#', '', apply_filters( 'icwp_shield_lp_gasp_uniqid', uniqid() ) ),
					'cbname'         => $oMod->getGaspKey(),
					'strings'        => [
						'label' => $oMod->getTextImAHuman(),
						'alert' => $oMod->getTextPleaseCheckBox(),
					],
					'flags'          => [
						'gasp'  => $oMod->isEnabledGaspCheck(),
						'recap' => $oMod->isGoogleRecaptchaEnabled(),
					]
				]
			);

			if ( $oMod->isGoogleRecaptchaEnabled() ) {
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
			'recaptcha' => 'ICWP_WPSF_Processor_LoginProtect_GoogleRecaptcha',
			'rename'    => 'ICWP_WPSF_Processor_LoginProtect_WpLogin',
		];
	}
}