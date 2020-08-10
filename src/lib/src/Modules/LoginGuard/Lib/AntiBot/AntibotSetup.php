<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO;
use FernleafSystems\Wordpress\Services\Services;

class AntibotSetup {

	use ModConsumer;

	public function __construct() {
		add_action( 'init', [ $this, 'onWpInit' ], -100 );
	}

	public function onWpInit() {
		if ( !Services::WpUsers()->isUserLoggedIn() ) {
			$this->run();
		}
	}

	private function run() {
		/** @var \ICWP_WPSF_FeatureHandler_LoginProtect $oMod */
		$oMod = $this->getMod();
		/** @var LoginGuard\Options $oOpts */
		$oOpts = $this->getOptions();

		$aProtectionProviders = [];
		if ( $oOpts->isEnabledCooldown() && $oMod->canCacheDirWrite() ) {
			$aProtectionProviders[] = ( new AntiBot\ProtectionProviders\CoolDown() )
				->setMod( $oMod );
		}

		if ( $oOpts->isEnabledGaspCheck() ) {
			$aProtectionProviders[] = ( new AntiBot\ProtectionProviders\GaspJs() )
				->setMod( $oMod );
		}

		if ( $oMod->isEnabledCaptcha() ) {
			$oCfg = $oMod->getCaptchaCfg();
			if ( $oCfg->provider === CaptchaConfigVO::PROV_GOOGLE_RECAP2 ) {
				$aProtectionProviders[] = ( new AntiBot\ProtectionProviders\GoogleRecaptcha() )
					->setMod( $oMod );
			}
			elseif ( $oCfg->provider === CaptchaConfigVO::PROV_HCAPTCHA ) {
				$aProtectionProviders[] = ( new AntiBot\ProtectionProviders\HCaptcha() )
					->setMod( $oMod );
			}
		}

		if ( !empty( $aProtectionProviders ) ) {

			AntiBot\FormProviders\WordPress::SetProviders( $aProtectionProviders );
			/** @var AntiBot\FormProviders\BaseFormProvider[] $aFormProviders */
			$aFormProviders = [
				new AntiBot\FormProviders\WordPress()
			];

			if ( $this->getMod()->getIfSupport3rdParty() ) {
				if ( @class_exists( 'BuddyPress' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\BuddyPress();
				}
				if ( @class_exists( 'Easy_Digital_Downloads' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\EasyDigitalDownloads();
				}
				if ( @class_exists( 'LearnPress' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\LearnPress();
				}
				if ( function_exists( 'mepr_autoloader' ) || @class_exists( 'MeprAccountCtrl' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\MemberPress();
				}
				if ( function_exists( 'UM' ) && @class_exists( 'UM' ) && method_exists( 'UM', 'form' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\UltimateMember();
				}
				if ( @class_exists( 'Paid_Member_Subscriptions' ) && function_exists( 'pms_errors' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\PaidMemberSubscriptions();
				}
				if ( defined( 'PROFILE_BUILDER_VERSION' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\ProfileBuilder();
				}
				if ( @class_exists( 'WooCommerce' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\WooCommerce();
				}
				if ( defined( 'WPMEM_VERSION' ) && function_exists( 'wpmem_init' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\WPMember();
				}
				if ( false && @class_exists( 'UserRegistration' ) && @function_exists( 'UR' ) ) {
					$aFormProviders[] = new AntiBot\FormProviders\UserRegistration();
				}
			}

			foreach ( $aFormProviders as $oForm ) {
				$oForm->setMod( $oMod )->run();
			}
		}
	}
}
