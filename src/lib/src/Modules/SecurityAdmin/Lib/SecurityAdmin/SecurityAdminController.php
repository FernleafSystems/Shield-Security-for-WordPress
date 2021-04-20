<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;
use FernleafSystems\Wordpress\Services\Services;

class SecurityAdminController extends ExecOnceModConsumer {

	private $validPinRequest;

	protected function canRun() :bool {
		return $this->isEnabledSecAdmin();
	}

	protected function run() {

		add_filter( $this->getCon()->prefix( 'is_plugin_admin' ), [ $this, 'adjustUserAdminPermissions' ] );
		add_action( 'admin_init', function () {
			$this->enqueueJS();
		} );
		add_action( 'init', function () {
			if ( !$this->getCon()->isPluginAdmin() ) {
				( new Restrictions\WpOptions() )
					->setMod( $this->getMod() )
					->execute();
				( new Restrictions\Plugins() )
					->setMod( $this->getMod() )
					->execute();
				( new Restrictions\Themes() )
					->setMod( $this->getMod() )
					->execute();
				( new Restrictions\Posts() )
					->setMod( $this->getMod() )
					->execute();
				( new Restrictions\Users() )
					->setMod( $this->getMod() )
					->execute();

				if ( !$this->getCon()->isThisPluginModuleRequest() ) {
					add_action( 'admin_footer', [ $this, 'printPinLoginForm' ] );
				}
			}
		} );
	}

	public function isEnabledSecAdmin() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $this->getMod()->isModOptEnabled() &&
			   $opts->hasSecurityPIN() && $this->getSecAdminTimeout() > 2;
	}

	private function enqueueJS() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/secadmin';

			add_filter( 'shield/custom_localisations', function ( array $localz ) {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				/** @var Options $opts */
				$opts = $this->getOptions();

				$isCurrentlySecAdmin = $this->isCurrentlySecAdmin();
				$localz[] = [
					'shield/secadmin',
					'shield_vars_secadmin',
					[
						'ajax'    => [
							'sec_admin_check'  => $mod->getAjaxActionData( 'sec_admin_check' ),
							'sec_admin_login'  => $mod->getAjaxActionData( 'sec_admin_login' ),
							'req_email_remove' => $mod->getAjaxActionData( 'req_email_remove' ),
						],
						'flags'   => [
							'restrict_options' => !$isCurrentlySecAdmin && $opts->getAdminAccessArea_Options(),
							'run_checks'       => $this->getCon()->getIsPage_PluginAdmin() && $isCurrentlySecAdmin
												  && !$this->isCurrentUserRegisteredSecAdmin(),
						],
						'strings' => [
							'confirm'            => __( 'Security Admin session has timed-out.', 'wp-simple-firewall' ).' '.__( 'Click OK to reload and re-authenticate.', 'wp-simple-firewall' ),
							'nearly'             => __( 'Security Admin session has nearly timed-out.', 'wp-simple-firewall' ),
							'expired'            => __( 'Security Admin session has timed-out.', 'wp-simple-firewall' ),
							'are_you_sure'       => __( 'Are you sure?', 'wp-simple-firewall' ),
							'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
							'unlock_link'        => sprintf(
								'<a href="%1$s" title="%2$s" class="thickbox">%3$s</a>',
								'#TB_inline?width=400&height=180&inlineId=WpsfAdminAccessLogin',
								__( 'Security Admin Login', 'wp-simple-firewall' ),
								__( 'Unlock', 'wp-simple-firewall' )
							),
						],
						'vars'    => [
							'time_remaining'         => $this->getSecAdminTimeRemaining(), // JS uses milliseconds
							'wp_options_to_restrict' => $opts->getOptionsToRestrict(),
						],
					]
				];
				return $localz;
			} );

			return $enqueues;
		} );
	}

	public function getSecAdminTimeout() :int {
		return (int)$this->getOptions()->getOpt( 'admin_access_timeout' )*MINUTE_IN_SECONDS;
	}

	/**
	 * Only returns greater than 0 if you have a valid Sec admin session
	 */
	public function getSecAdminTimeRemaining() :int {
		$remaining = 0;
		if ( $this->getCon()->getModule_Sessions()->getSessionCon()->hasSession() ) {

			$secAdminAt = $this->getMod()->getSession()->getSecAdminAt();
			if ( $this->isRegisteredSecAdminUser() ) {
				$remaining = 0;
			}
			elseif ( $secAdminAt > 0 ) {
				$remaining = $this->getSecAdminTimeout() - ( Services::Request()->ts() - $secAdminAt );
			}
		}
		return (int)max( 0, $remaining );
	}

	public function isCurrentUserRegisteredSecAdmin() :bool {
		return $this->isRegisteredSecAdminUser( Services::WpUsers()->getCurrentWpUser() );
	}

	/**
	 * @param \WP_User|null $user
	 * @return bool
	 */
	public function isRegisteredSecAdminUser( $user = null ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( !$user instanceof \WP_User ) {
			$user = Services::WpUsers()->getCurrentWpUser();
		}
		return $user instanceof \WP_User && in_array( $user->user_login, $opts->getSecurityAdminUsers() );
	}

	public function isCurrentlySecAdmin() :bool {
		// TODO: replace with isCurrentUserRegisteredSecAdmin()
		return $this->isRegisteredSecAdminUser( Services::WpUsers()->getCurrentWpUser() )
			   || $this->getSecAdminTimeRemaining() > 0;
	}

	public function adjustUserAdminPermissions( $isPluginAdmin = true ) :bool {
		return $isPluginAdmin &&
			   ( $this->isCurrentlySecAdmin() || $this->verifyPinRequest() );
	}

	public function printPinLoginForm() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		add_thickbox();
		echo $mod->renderTemplate( '/components/security_admin/login_box.twig', [
			'flags'   => [
				'restrict_options' => $opts->getAdminAccessArea_Options()
			],
			'strings' => [
				'access_message' => __( 'Enter your Security Admin PIN', 'wp-simple-firewall' ),
			],
			'ajax'    => [
				'sec_admin_login'     => $mod->getAjaxActionData( 'sec_admin_login', true ),
				'sec_admin_login_box' => $mod->getAjaxActionData( 'sec_admin_login_box', true )
			]
		] );
	}

	public function verifyPinRequest() :bool {
		if ( !isset( $this->validPinRequest ) ) {
			$this->validPinRequest = ( new Ops\VerifyPinRequest() )
				->setMod( $this->getMod() )
				->run();
		}
		return $this->validPinRequest;
	}
}