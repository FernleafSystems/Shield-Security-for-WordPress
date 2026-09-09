<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\{
	EmailDeliveryVerification,
	EmailDeliveryVerificationMailer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

class HandleOptionsSaveRequest {

	use PluginControllerConsumer;

	private array $form;

	public function handleSave() :bool {
		try {
			if ( !self::con()->isPluginAdmin() ) {
				throw new \Exception( __( "You don't currently have permission to save settings.", 'wp-simple-firewall' ) );
			}

			$form = $this->getForm();
			if ( empty( $form ) ) {
				throw new \Exception( __( 'Options form parameters were empty.', 'wp-simple-firewall' ) );
			}
			if ( empty( $form[ 'all_opts_keys' ] ) ) {
				throw new \Exception( __( 'all_opts_keys form element not provided.', 'wp-simple-firewall' ) );
			}

			$this->storeOptions();

			do_action( 'shield/after_form_submit_options_save', $form );

			$success = true;
		}
		catch ( \Exception $e ) {
			$success = false;
		}
		return $success;
	}

	private function getForm() :array {
		return $this->form ??= FormParams::Retrieve();
	}

	/**
	 * @throws \Exception
	 */
	private function storeOptions() :void {
		$submittedKeys = $this->applyFormValues();
		$this->sendEmailVerificationIfRequired( $submittedKeys );

		self::con()->opts->store();
	}

	/**
	 * @return string[]
	 * @throws \Exception
	 */
	private function applyFormValues() :array {
		$optsCon = self::con()->opts;
		$resolved = ( new ResolveSubmittedOptionValues() )->resolve( $this->getForm() );

		foreach ( $resolved[ 'values' ] as $optKey => $optValue ) {
			$optsCon->optSet( $optKey, $optValue );
		}

		return $resolved[ 'submitted_keys' ];
	}

	/**
	 * @param string[] $submittedKeys
	 */
	private function sendEmailVerificationIfRequired( array $submittedKeys ) :void {
		if ( !\in_array( 'enable_email_authentication', $submittedKeys, true ) ) {
			return;
		}

		$verification = new EmailDeliveryVerification();
		if ( $verification->needsVerificationSend() && ( new EmailDeliveryVerificationMailer() )->send() ) {
			$verification->markSent();
		}
	}
}
