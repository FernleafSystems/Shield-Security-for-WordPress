<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

/**
 * https://wordpress.org/plugins/wp-members/
 */
class WPMembers extends Base {

	protected function register() {
		add_action( 'wpmem_pre_register_data', [ $this, 'checkRegister_WM' ], 5, 0 );
	}

	protected function lostpassword() {
		add_action( 'wpmem_pwdreset_args', [ $this, 'checkLostPassword_WM' ], 5 );
	}

	/**
	 * Again, nowhere to add custom validation so we hack it a little and clear
	 * the argument for user and email and this triggers a failure.
	 * @param array $args
	 * @return array
	 */
	public function checkLostPassword_WM( array $args ) {
		if ( $this->setAuditAction( 'lostpassword' )->isBotBlockRequired() ) {
			$args[ 'user' ] = null;
			$args[ 'email' ] = null;
		}
		return $args;
	}

	/**
	 * Offers no direct validation filter, so we jump in right before the DB insert.
	 */
	public function checkRegister_WM() {
		if ( $this->setAuditAction( 'register' )->isBotBlockRequired() ) {
			global $wpmem_themsg;
			$wpmem_themsg = $this->getErrorMessage();
		}
	}
}