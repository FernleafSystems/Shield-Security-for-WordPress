<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;
use FernleafSystems\Wordpress\Services\Services;

class AntibotSetup {

	use ModConsumer;

	public function __construct() {
		add_action( 'init', [ $this, 'onWpInit' ] );
	}

	public function onWpInit() {
		if ( !Services::WpUsers()->isUserLoggedIn() && $this->getMod()->getIfSupport3rdParty() ) {
			$this->run();
		}
	}

	private function run() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();

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
			if ( @class_exists( 'LearnPress' ) ) {
				$aFormProviders[] = new AntiBot\FormProviders\BuddyPress();
			}
			if ( defined( 'MEPR_LIB_PATH' ) || defined( 'MEPR_PLUGIN_NAME' ) ) {
				$aFormProviders[] = new AntiBot\FormProviders\MemberPress();
			}
			if ( function_exists( 'UM' ) && @class_exists( 'UM' ) && method_exists( 'UM', 'form' ) ) {
				$aFormProviders[] = new AntiBot\FormProviders\UltimateMember();
			}
			if ( @class_exists( 'LearnPress' ) ) {
				$aFormProviders[] = new AntiBot\FormProviders\LearnPress();
			}
			if ( @class_exists( 'Paid_Member_Subscriptions' ) && function_exists( 'pms_errors' ) ) {
				$aFormProviders[] = new AntiBot\FormProviders\PaidMemberSubscriptions();
			}
			if ( defined( 'PROFILE_BUILDER_VERSION' ) ) {
				$aFormProviders[] = new AntiBot\FormProviders\ProfileBuilder();
			}

			foreach ( $aFormProviders as $oForm ) {
				$oForm->setMod( $oMod )->run();
			}
		}
	}
}
