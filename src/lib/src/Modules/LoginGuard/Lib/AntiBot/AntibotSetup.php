<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Captcha\CaptchaConfigVO;
use FernleafSystems\Wordpress\Services\Services;

class AntibotSetup extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return !Services::WpUsers()->isUserLoggedIn();
	}

	protected function run() {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();

		$providers = [];
		if ( $opts->isEnabledCooldown() && $mod->canCacheDirWrite() ) {
			$providers[] = ( new AntiBot\ProtectionProviders\CoolDown() )
				->setMod( $mod );
		}

		if ( !$opts->isEnabledAntiBot() ) {
			if ( $opts->isEnabledGaspCheck() ) {
				$providers[] = ( new AntiBot\ProtectionProviders\GaspJs() )
					->setMod( $mod );
			}

			if ( $mod->isEnabledCaptcha() ) {
				$cfg = $mod->getCaptchaCfg();
				if ( $cfg->provider === CaptchaConfigVO::PROV_GOOGLE_RECAP2 ) {
					$providers[] = ( new AntiBot\ProtectionProviders\GoogleRecaptcha() )
						->setMod( $mod );
				}
				elseif ( $cfg->provider === CaptchaConfigVO::PROV_HCAPTCHA ) {
					$providers[] = ( new AntiBot\ProtectionProviders\HCaptcha() )
						->setMod( $mod );
				}
			}
		}

		if ( !empty( $providers ) ) {

			AntiBot\FormProviders\WordPress::SetProviders( $providers );
			/** @var AntiBot\FormProviders\BaseFormProvider[] $formProviders */
			$formProviders = [
				new AntiBot\FormProviders\WordPress()
			];

			if ( $this->getMod()->getIfSupport3rdParty() ) {
				if ( @class_exists( 'BuddyPress' ) ) {
					$formProviders[] = new AntiBot\FormProviders\BuddyPress();
				}
				if ( @class_exists( 'Easy_Digital_Downloads' ) ) {
					$formProviders[] = new AntiBot\FormProviders\EasyDigitalDownloads();
				}
				if ( @class_exists( 'LearnPress' ) ) {
					$formProviders[] = new AntiBot\FormProviders\LearnPress();
				}
				if ( function_exists( 'mepr_autoloader' ) || @class_exists( 'MeprAccountCtrl' ) ) {
					$formProviders[] = new AntiBot\FormProviders\MemberPress();
				}
				if ( function_exists( 'UM' ) && @class_exists( 'UM' ) && method_exists( 'UM', 'form' ) ) {
					$formProviders[] = new AntiBot\FormProviders\UltimateMember();
				}
				if ( @class_exists( 'Paid_Member_Subscriptions' ) && function_exists( 'pms_errors' ) ) {
					$formProviders[] = new AntiBot\FormProviders\PaidMemberSubscriptions();
				}
				if ( defined( 'PROFILE_BUILDER_VERSION' ) ) {
					$formProviders[] = new AntiBot\FormProviders\ProfileBuilder();
				}
				if ( @class_exists( 'WooCommerce' ) ) {
					$formProviders[] = new AntiBot\FormProviders\WooCommerce();
				}
				if ( defined( 'WPMEM_VERSION' ) && function_exists( 'wpmem_init' ) ) {
					$formProviders[] = new AntiBot\FormProviders\WPMembers();
				}
				if ( false && @class_exists( 'UserRegistration' ) && @function_exists( 'UR' ) ) {
					$formProviders[] = new AntiBot\FormProviders\UserRegistration();
				}
			}

			foreach ( $formProviders as $form ) {
				$form->setMod( $mod )->execute();
			}
		}
	}
}
