<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

class BuddyPress extends BaseFormProvider {

	protected function register() {
		add_action( 'bp_before_registration_submit_buttons', [ $this, 'printFormInsert' ] );
		add_action( 'bp_signup_validate', [ $this, 'checkRegister' ] );
	}

	/**
	 * @uses \die()
	 */
	public function checkRegister() {
		$this->checkThenDie();
	}
}