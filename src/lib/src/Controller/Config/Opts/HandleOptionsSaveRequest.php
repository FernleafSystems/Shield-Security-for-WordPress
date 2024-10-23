<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

class HandleOptionsSaveRequest {

	use PluginControllerConsumer;

	private $form;

	public function handleSave() :bool {
		try {
			if ( !self::con()->isPluginAdmin() ) {
				throw new \Exception( __( "You don't currently have permission to save settings.", 'wp-simple-firewall' ) );
			}

			$form = $this->getForm();
			if ( empty( $form ) ) {
				throw new \Exception( 'options form parameters were empty.' );
			}
			if ( empty( $form[ 'all_opts_keys' ] ) ) {
				throw new \Exception( 'all_opts_keys form element not provided.' );
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
		return $this->form ?? $this->form = FormParams::Retrieve();
	}

	/**
	 * @throws \Exception
	 */
	private function storeOptions() {
		// standard options use b64 and fail-over to lz-string
		$form = $this->getForm();

		$optsCon = self::con()->opts;

		foreach ( \explode( ',', $form[ 'all_opts_keys' ] ?? [] ) as $optKey ) {

			if ( !$optsCon->optExists( $optKey ) || $optsCon->optDef( $optKey )[ 'section' ] === 'section_hidden' ) {
				continue;
			}

			$optType = $optsCon->optType( $optKey );
			if ( $optType === 'noneditable_text' ) {
				continue;
			}

			$optValue = $form[ $optKey ] ?? null;
			if ( \is_null( $optValue ) ) {

				if ( \in_array( $optType, [ 'text', 'email' ] ) ) { //text box, and it's null, don't update
					continue;
				}
				elseif ( $optType == 'checkbox' ) { //if it was a checkbox, and it's null, it means 'N'
					$optValue = 'N';
				}
				elseif ( $optType == 'integer' ) { //if it was a integer, and it's null, it means '0'
					$optValue = 0;
				}
				elseif ( $optType == 'multiple_select' ) {
					$optValue = [];
				}
			}
			elseif ( $optType == 'password' ) {
				$tempValue = \trim( $optValue );
				if ( empty( $tempValue ) ) {
					continue;
				}

				$confirm = $form[ $optKey.'_confirm' ] ?? null;
				if ( $tempValue !== $confirm ) {
					throw new \Exception( __( 'Password values do not match.', 'wp-simple-firewall' ) );
				}

				$optValue = \hash( 'md5', $tempValue );
			}
			elseif ( $optType == 'array' ) { //arrays are textareas, where each is separated by newline
				$optValue = \array_filter( \explode( "\n", esc_textarea( $optValue ) ), '\trim' );
			}

			$optsCon->optSet( $optKey, $optValue );
		}

		$optsCon->store();
	}
}