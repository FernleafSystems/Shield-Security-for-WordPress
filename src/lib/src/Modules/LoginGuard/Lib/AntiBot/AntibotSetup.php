<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;
use FernleafSystems\Wordpress\Services\Services;

class AntibotSetup {

	use ExecOnce;
	use LoginGuard\ModConsumer;

	protected function canRun() :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions && !Services::WpUsers()->isUserLoggedIn();
	}

	protected function run() {
		$con = self::con();
		$opts = $this->opts();

		$providers = [];
		if ( $con->opts->optGet( 'login_limit_interval' ) > 0 && self::con()->cache_dir_handler->exists() ) {
			$providers[] = new AntiBot\ProtectionProviders\CoolDown();
		}

		if ( !$opts->isEnabledAntiBot() && $opts->isEnabledGaspCheck() ) {
			$providers[] = new AntiBot\ProtectionProviders\GaspJs();
		}

		if ( !empty( $providers ) ) {

			AntiBot\FormProviders\WordPress::SetProviders( $providers );
			/** @var AntiBot\FormProviders\BaseFormProvider[] $formProviders */
			$formProviders = [
				new AntiBot\FormProviders\WordPress()
			];

			if ( self::con()->isPremiumActive() ) {
				if ( @\class_exists( '\BuddyPress' ) ) {
					$formProviders[] = new AntiBot\FormProviders\BuddyPress();
				}
				if ( @\class_exists( '\Easy_Digital_Downloads' ) ) {
					$formProviders[] = new AntiBot\FormProviders\EasyDigitalDownloads();
				}
				if ( @\class_exists( '\LearnPress' ) ) {
					$formProviders[] = new AntiBot\FormProviders\LearnPress();
				}
				if ( \function_exists( '\mepr_autoloader' ) || @\class_exists( '\MeprAccountCtrl' ) ) {
					$formProviders[] = new AntiBot\FormProviders\MemberPress();
				}
				if ( \function_exists( '\UM' ) && @\class_exists( '\UM' ) && \method_exists( 'UM', 'form' ) ) {
					$formProviders[] = new AntiBot\FormProviders\UltimateMember();
				}
				if ( @\class_exists( 'Paid_Member_Subscriptions' ) && \function_exists( 'pms_errors' ) ) {
					$formProviders[] = new AntiBot\FormProviders\PaidMemberSubscriptions();
				}
				if ( \defined( 'PROFILE_BUILDER_VERSION' ) ) {
					$formProviders[] = new AntiBot\FormProviders\ProfileBuilder();
				}
				if ( @\class_exists( 'WooCommerce' ) ) {
					$formProviders[] = new AntiBot\FormProviders\WooCommerce();
				}
				if ( \defined( 'WPMEM_VERSION' ) && \function_exists( 'wpmem_init' ) ) {
					$formProviders[] = new AntiBot\FormProviders\WPMembers();
				}
			}

			foreach ( $formProviders as $form ) {
				$form->execute();
			}
		}
	}
}