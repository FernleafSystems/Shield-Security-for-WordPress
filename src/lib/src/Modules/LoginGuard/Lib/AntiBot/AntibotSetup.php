<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;

class AntibotSetup {

	use ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$aProtectionProviders = [];
		if ( $oMod->isEnabledGaspCheck() ) {
			$aProtectionProviders[] = ( new AntiBot\ProtectionProviders\GaspJs() )
				->setMod( $oMod );
		}
		if ( $oMod->isGoogleRecaptchaEnabled() ) {
			$aProtectionProviders[] = ( new AntiBot\ProtectionProviders\GoogleRecaptcha() )
				->setMod( $oMod );
		}
		if ( !empty( $aProtectionProviders ) ) {
			AntiBot\FormProviders\WordPress::SetProviders( $aProtectionProviders );
			( new AntiBot\FormProviders\WordPress() )
				->setMod( $oMod )
				->run();

			/** @var AntiBot\FormProviders\BaseFormProvider[] $aFormProviders */
			$aFormProviders = [
				new AntiBot\FormProviders\WordPress()
			];
			if ( @class_exists( 'Easy_Digital_Downloads' ) ) {
				$aFormProviders[] = new AntiBot\FormProviders\EasyDigitalDownloads();
			}
			if ( @class_exists( 'WooCommerce' ) ) {
				$aFormProviders[] = new AntiBot\FormProviders\WooCommerce();
			}

			foreach ( $aFormProviders as $oForm ) {
				$oForm->setMod( $oMod )->run();
			}
		}
	}
}
