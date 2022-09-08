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
		add_filter( $this->getCon()->prefix( 'is_plugin_admin' ), [ $this, 'adjustUserAdminPermissions' ], 0 );
		add_action( 'admin_init', function () {
			$this->enqueueJS();
		} );
		add_action( 'init', [ $this, 'setupRestrictions' ] );
	}

	/**
	 * Restrictions should only be applied after INIT
	 */
	public function setupRestrictions() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			foreach ( $this->enumRestrictionZones() as $zone ) {
				( new $zone() )->setMod( $this->getMod() )->execute();
			}
			if ( !$this->getCon()->isThisPluginModuleRequest() ) {
				add_action( 'admin_footer', [ $this, 'printPinLoginForm' ] );
			}

			add_action( 'pre_uninstall_plugin', function ( $pluginFile ) {
				// This can only protect against rogue, programmatic uninstalls, not when Shield is inactive.
				if ( $pluginFile === $this->getCon()->base_file ) {
					$this->blockRemoval();
				}
			} );
			add_action( $this->getCon()->prefix( 'pre_deactivate_plugin' ), [ $this, 'blockRemoval' ] );
		}
	}

	public function blockRemoval() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			if ( !Services::WpUsers()->isUserAdmin() ) {
				$this->getCon()->fireEvent( 'attempt_deactivation' );
			}
			Services::WpGeneral()->wpDie(
				sprintf(
					'<p>%s</p><p>%s</p>',
					__( "Sorry, this plugin is protected against unauthorised attempts to disable it.", 'wp-simple-firewall' ),
					sprintf( '<a href="%s">%s</a>',
						$this->getMod()->getUrl_AdminPage(),
						sprintf( __( "Please authenticate with the %s Security Admin system and try again.", 'wp-simple-firewall' ),
							$this->getCon()->getHumanName() )
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

	public function isEnabledSecAdmin() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $this->getMod()->isModOptEnabled() && $opts->hasSecurityPIN() && $this->getSecAdminTimeout() > 0;
	}

	private function enqueueJS() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/secadmin';

			add_filter( 'shield/custom_localisations', function ( array $localz ) {
				/** @var ModCon $mod */
				$mod = $this->getMod();
				/** @var Options $opts */
				$opts = $this->getOptions();

				$isSecAdmin = $this->getCon()->this_req->is_security_admin;
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
							'restrict_options' => !$isSecAdmin && $opts->isRestrictWpOptions(),
							'run_checks'       => $this->getCon()->getIsPage_PluginAdmin() && $isSecAdmin
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
		$session = $this->getMod()->getSessionWP();
		if ( $session->valid ) {
			$secAdminAt = $session->shield[ 'secadmin_at' ] ?? 0;
			if ( !$this->isRegisteredSecAdminUser() && $secAdminAt > 0 ) {
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
			   ( $this->getCon()->this_req->is_security_admin || $this->verifyPinRequest() );
	}

	public function renderPinLoginForm() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $mod->renderTemplate( '/components/security_admin/login_box.twig', [
			'flags'   => [
				'restrict_options' => $opts->isRestrictWpOptions()
			],
			'strings' => [
				'access_message' => __( 'Enter your Security Admin PIN', 'wp-simple-firewall' ),
			],
			'ajax'    => [
				'sec_admin_login' => $mod->getAjaxActionData( 'sec_admin_login', true ),
			]
		] );
	}

	public function printPinLoginForm() {
		add_thickbox();
		echo $this->renderPinLoginForm();
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