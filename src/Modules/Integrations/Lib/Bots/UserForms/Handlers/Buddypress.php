<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

class Buddypress extends Base {

	protected function register() {
		add_action( 'bp_signup_validate', [ $this, 'checkRegister_BP' ] );
	}

	public function checkRegister_BP() {
		if ( $this->setAuditAction( 'register' )->isBotBlockRequired() ) {
			$this->fireEventBlockRegister();
			wp_die( $this->getErrorMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Translated text with the plugin name and a count, stripped of markup by getErrorMessage(); wp_die()'s JSON and XML handlers expect plain text.
		}
	}
}