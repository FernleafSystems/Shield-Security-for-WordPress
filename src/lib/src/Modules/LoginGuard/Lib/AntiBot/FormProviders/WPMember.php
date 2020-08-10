<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

/**
 * Class WPMember
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders
 * https://wordpress.org/plugins/wp-members/
 */
class WPMember extends BaseFormProvider {

	protected function register() {
		add_action( 'wpmem_pre_register_data', [ $this, 'checkRegister' ], 5, 0 );
	}

	protected function lostpassword() {
		add_action( 'wpmem_pwdreset_args', [ $this, 'checkLostPassword' ], 5, 1 );
	}

	/**
	 * Again, nowhere to add custom validation so we hack it a little and clear
	 * the argument for user and email and this triggers a failure.
	 * @param array $args
	 * @return array
	 */
	public function checkLostPassword( array $args ) {
		try {
			$this->setActionToAudit( 'wpmembers-lostpassword' )
				 ->checkProviders();
		}
		catch ( \Exception $oE ) {
			$args[ 'user' ] = null;
			$args[ 'email' ] = null;
		}
		return $args;
	}

	/**
	 * Offers no direct validation filter, so we jump in right before the DB insert.
	 */
	public function checkRegister() {
		try {
			$this->setActionToAudit( 'wpmembers-register' )
				 ->checkProviders();
		}
		catch ( \Exception $oE ) {
			global $wpmem_themsg;
			$wpmem_themsg = $oE->getMessage();
		}
	}
}