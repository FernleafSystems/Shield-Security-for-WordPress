<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

class BuddyPress extends BaseFormProvider {

	protected function register() {
		add_action( 'bp_before_registration_submit_buttons', [ $this, 'printFormInsert' ], 10 );
		add_action( 'bp_signup_validate', [ $this, 'checkRegister' ], 10 );
	}

	/**
	 * @use die()
	 */
	public function checkRegister() {
		$this->checkThenDie();
	}
}