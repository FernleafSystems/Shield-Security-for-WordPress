<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	Render\Components\FormSecurityAdminLoginBox,
	SecurityAdminCheck,
	SecurityAdminLogin,
	SecurityAdminRequestRemoveByEmail
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class SecurityAdminController {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return !$this->con()->this_req->request_bypasses_all_restrictions && $this->isEnabledSecAdmin();
	}

	protected function run() {
		add_filter( $this->con()->prefix( 'is_plugin_admin' ), [ $this, 'adjustUserAdminPermissions' ], 0 );
		add_action( 'admin_init', function () {
			$this->enqueueJS();
		} );
		add_action( 'init', [ $this, 'setupRestrictions' ] );
	}

	/**
	 * Restrictions should only be applied after INIT
	 */
	public function setupRestrictions() {
		if ( !$this->con()->isPluginAdmin() ) {
			foreach ( $this->enumRestrictionZones() as $zone ) {
				( new $zone() )->execute();
			}
			if ( !$this->con()->isPluginAdminPageRequest() ) {
				add_action( 'admin_footer', [ $this, 'printPinLoginForm' ] );
			}

			add_action( 'pre_uninstall_plugin', function ( $pluginFile ) {
				// This can only protect against rogue, programmatic uninstalls, not when Shield is inactive.
				if ( $pluginFile === $this->con()->base_file ) {
					$this->blockRemoval();
				}
			} );
			add_action( $this->con()->prefix( 'pre_deactivate_plugin' ), [ $this, 'blockRemoval' ] );
		}
	}

	public function blockRemoval() {
		$con = $this->con();
		if ( !$con->isPluginAdmin() ) {
			if ( !Services::WpUsers()->isUserAdmin() ) {
				$con->fireEvent( 'attempt_deactivation' );
			}
			Services::WpGeneral()->wpDie(
				sprintf(
					'<p>%s</p><p>%s</p>',
					__( "Sorry, this plugin is protected against unauthorised attempts to disable it.", 'wp-simple-firewall' ),
					sprintf( '<a href="%s">%s</a>',
						$con->plugin_urls->adminHome(),
						sprintf( __( "Please authenticate with the %s Security Admin system and try again.", 'wp-simple-firewall' ),
							$con->getHumanName() )
					)
				)
			);
		}
	}

	/**
	 * @return Restrictions\Base[]
	 */
	private function enumRestrictionZones() :array {
		return [
			Restrictions\WpOptions::class,
			Restrictions\Plugins::class,
			Restrictions\Themes::class,
			Restrictions\Posts::class,
			Restrictions\Users::class,
		];
	}

	public function hasActiveSession() :bool {
		return $this->con()->this_req->is_security_admin && $this->getSecAdminTimeRemaining() > 0;
	}

	public function isEnabledSecAdmin() :bool {
		return $this->mod()->isModOptEnabled()
			   && $this->opts()->hasSecurityPIN()
			   && $this->getSecAdminTimeout() > 0;
	}

	private function enqueueJS() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/secadmin';

			add_filter( 'shield/custom_localisations', function ( array $localz ) {
				$isSecAdmin = $this->con()->this_req->is_security_admin;
				$localz[] = [
					'shield/secadmin',
					'shield_vars_secadmin',
					[
						'ajax'    => [
							'sec_admin_check'  => ActionData::Build( SecurityAdminCheck::class ),
							'sec_admin_login'  => ActionData::Build( SecurityAdminLogin::class ),
							'req_email_remove' => ActionData::Build( SecurityAdminRequestRemoveByEmail::class ),
						],
						'flags'   => [
							'restrict_options' => !$isSecAdmin && $this->opts()->isRestrictWpOptions(),
							'run_checks'       => $this->con()->getIsPage_PluginAdmin()
												  && $isSecAdmin
												  && !$this->isCurrentUserRegisteredSecAdmin(),
						],
						'strings' => [
							'confirm_disable'    => sprintf( __( "An confirmation link will be sent to '%s' - please open it in this browser window.", 'wp-simple-firewall' ),
								Obfuscate::Email( $this->mod()->getPluginReportEmail() ) ),
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
							'wp_options_to_restrict' => $this->opts()->getOptionsToRestrict(),
						],
					]
				];
				return $localz;
			} );

			return $enqueues;
		} );
	}

	public function getSecAdminTimeout() :int {
		return (int)$this->opts()->getOpt( 'admin_access_timeout' )*MINUTE_IN_SECONDS;
	}

	/**
	 * Only returns greater than 0 if you have a valid Sec admin session
	 */
	public function getSecAdminTimeRemaining() :int {
		$remaining = 0;

		$session = $this->con()->getModule_Plugin()->getSessionCon()->current();
		if ( $session->valid ) {
			$secAdminAt = $session->shield[ 'secadmin_at' ] ?? 0;
			if ( !$this->isCurrentUserRegisteredSecAdmin() && $secAdminAt > 0 ) {
				$remaining = (int)max( 0, $this->getSecAdminTimeout() - ( Services::Request()->ts() - $secAdminAt ) );
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
		if ( !$user instanceof \WP_User ) {
			$user = Services::WpUsers()->getCurrentWpUser();
		}
		return $user instanceof \WP_User && \in_array( $user->user_login, $this->opts()->getSecurityAdminUsers() );
	}

	public function isCurrentlySecAdmin() :bool {
		return $this->isCurrentUserRegisteredSecAdmin() || $this->getSecAdminTimeRemaining() > 0;
	}

	public function adjustUserAdminPermissions( $isPluginAdmin = true ) :bool {
		return $isPluginAdmin && $this->con()->this_req->is_security_admin;
	}

	public function printPinLoginForm() {
		add_thickbox();
		echo $this->con()->action_router->render( FormSecurityAdminLoginBox::SLUG );
	}
}