<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AntibotSetup {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions;
	}

	protected function run() {
		add_action( 'init', function () {
			if ( !Services::WpUsers()->isUserLoggedIn() ) {
				$this->setup();
			}
		}, HookTimings::INIT_ANTIBOT_SETUP );
	}

	private function setup() {
		$con = self::con();

		$providers = [];
		if ( $con->opts->optGet( 'login_limit_interval' ) > 0 && $con->cache_dir_handler->exists() ) {
			$providers[] = ProtectionProviders\CoolDown::class;
		}

		if ( !empty( $providers ) ) {

			FormProviders\WordPress::SetProviders(
				\array_map( function ( string $protectionClass ) {
					return new $protectionClass();
				}, $providers )
			);

			/** @var FormProviders\BaseFormProvider[] $formProviders */
			$formProviders = [
				FormProviders\WordPress::class,
			];

			if ( self::con()->isPremiumActive() ) {
				if ( @\class_exists( '\BuddyPress' ) ) {
					$formProviders[] = FormProviders\BuddyPress::class;
				}
				if ( @\class_exists( '\Easy_Digital_Downloads' ) ) {
					$formProviders[] = FormProviders\EasyDigitalDownloads::class;
				}
				if ( @\class_exists( '\LearnPress' ) ) {
					$formProviders[] = FormProviders\LearnPress::class;
				}
				if ( \function_exists( '\mepr_autoloader' ) || @\class_exists( '\MeprAccountCtrl' ) ) {
					$formProviders[] = FormProviders\MemberPress::class;
				}
				if ( \function_exists( '\UM' ) && @\class_exists( '\UM' ) && \method_exists( 'UM', 'form' ) ) {
					$formProviders[] = FormProviders\UltimateMember::class;
				}
				if ( @\class_exists( 'Paid_Member_Subscriptions' ) && \function_exists( 'pms_errors' ) ) {
					$formProviders[] = FormProviders\PaidMemberSubscriptions::class;
				}
				if ( \defined( 'PROFILE_BUILDER_VERSION' ) ) {
					$formProviders[] = FormProviders\ProfileBuilder::class;
				}
				if ( @\class_exists( 'WooCommerce' ) ) {
					$formProviders[] = FormProviders\WooCommerce::class;
				}
				if ( \defined( 'WPMEM_VERSION' ) && \function_exists( 'wpmem_init' ) ) {
					$formProviders[] = FormProviders\WPMembers::class;
				}
			}

			\array_map( function ( string $formClass ) {
				/** @var FormProviders\BaseFormProvider $formProvider */
				$formProvider = new $formClass();
				$formProvider->execute();
			}, $formProviders );
		}
	}
}