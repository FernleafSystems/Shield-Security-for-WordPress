<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;
use FernleafSystems\Wordpress\Services\Services;

class SecurityAdminController {

	use ExecOnce;
	use ModConsumer;

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
					add_action( 'admin_footer', [ $this, 'printAdminAccessAjaxForm' ] );
				}
			}
		} );
	}

	private function enqueueJS() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/secadmin';

			add_filter( 'shield/custom_localisations', function ( array $localz ) {
				/** @var ModCon $mod */
				$mod = $this->getMod();

				$timeRemaining = $this->getSecAdminTimeRemaining();
				error_log( (string)$timeRemaining );
				$localz[] = [
					'shield/secadmin',
					'shield_vars_secadmin',
					[
						'ajax'    => [
							'sec_admin_check'  => $mod->getAjaxActionData( 'sec_admin_check' ),
							'req_email_remove' => $mod->getAjaxActionData( 'req_email_remove' ),
						],
						'strings' => [
							'confirm'      => __( 'Security Admin session has timed-out.', 'wp-simple-firewall' ).' '.__( 'Reload now?', 'wp-simple-firewall' ),
							'nearly'       => __( 'Security Admin session has nearly timed-out.', 'wp-simple-firewall' ),
							'expired'      => __( 'Security Admin session has timed-out.', 'wp-simple-firewall' ),
							'are_you_sure' => __( 'Are you sure?', 'wp-simple-firewall' )
						],
						'flags'   => [
							'run_checks' => $this->isEnabledSecAdmin()
											&& $this->getCon()->getIsPage_PluginAdmin()
											&& $this->isCurrentSecAdminSessionValid(),
						],
						'vars'    => [
							'time_remaining' => $timeRemaining, // JS uses milliseconds
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
		return $user instanceof \WP_User
			   && in_array( $user->user_login, $opts->getSecurityAdminUsers() );
	}

	public function isEnabledSecAdmin() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $this->getMod()->isModOptEnabled() &&
			   $opts->hasSecurityPIN() && $this->getSecAdminTimeout() > 0;
	}

	public function isCurrentSecAdminSessionValid() :bool {
		return $this->getSecAdminTimeRemaining() > 0;
	}

	public function adjustUserAdminPermissions( $isPluginAdmin = true ) :bool {
		return $isPluginAdmin &&
			   ( $this->isRegisteredSecAdminUser() || $this->isCurrentSecAdminSessionValid() || $this->verifyPinRequest() );
	}

	public function printAdminAccessAjaxForm() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		add_thickbox();
		echo $this->getMod()->renderTemplate( 'snippets/admin_access_login_box.php', [
			'flags'       => [
				'restrict_options' => $opts->getAdminAccessArea_Options()
			],
			'strings'     => [
				'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
				'unlock_link'        => sprintf(
					'<a href="%1$s" title="%2$s" class="thickbox">%3$s</a>',
					'#TB_inline?width=400&height=180&inlineId=WpsfAdminAccessLogin',
					__( 'Security Admin Login', 'wp-simple-firewall' ),
					__( 'Unlock', 'wp-simple-firewall' )
				),
			],
			'js_snippets' => [
				'options_to_restrict' => "'".implode( "','", $opts->getOptionsToRestrict() )."'",
			],
			'ajax'        => [
				'sec_admin_login_box' => $this->getMod()->getAjaxActionData( 'sec_admin_login_box', true )
			]
		] );
	}

	public function verifyPinRequest() :bool {
		return ( new Ops\VerifyPinRequest() )
			->setMod( $this->getMod() )
			->run();
	}
}