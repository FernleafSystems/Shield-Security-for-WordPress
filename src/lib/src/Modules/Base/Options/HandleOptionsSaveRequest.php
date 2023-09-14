<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options\SaveExcludedOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class HandleOptionsSaveRequest {

	use PluginControllerConsumer;

	private $form;

	/**
	 * @var ModCon
	 */
	private $mod;

	public function handleSave() :bool {
		try {
			$con = self::con();
			if ( !$con->isPluginAdmin() ) {
				throw new \Exception( __( "You don't currently have permission to save settings.", 'wp-simple-firewall' ) );
			}

			$form = $this->getForm();
			if ( empty( $form ) ) {
				throw new \Exception( 'options form parameters were empty.' );
			}

			$this->mod = $con->getModule( $form[ 'working_mod' ] );
			if ( empty( $this->mod ) ) {
				throw new \Exception( 'Working mod provided is invalid.' );
			}

			$this->storeOptions();

			do_action( 'shield/after_form_submit_options_save', $this->mod, $form );

			$success = true;
		}
		catch ( \Exception $e ) {
			$success = false;
		}
		return $success;
	}

	private function getForm() :array {
		return $this->form ?? $this->form = FormParams::Retrieve( FormParams::ENC_BASE64 );
	}

	/**
	 * @throws \Exception
	 */
	private function storeOptions() {
		// standard options use b64 and fail-over to lz-string
		$form = $this->getForm();

		$optsAndTypes = \array_map(
			function ( $optDef ) {
				return $optDef[ 'type' ];
			},
			$this->mod->opts()->getVisibleOptions()
		);
		foreach ( $optsAndTypes as $optKey => $optType ) {

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
			else { //handle any pre-processing we need to.

				if ( $optType == 'text' || $optType == 'email' ) {
					$optValue = \trim( $optValue );
				}
				if ( $optType == 'integer' ) {
					$optValue = \intval( $optValue );
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

					$optValue = \md5( $tempValue );
				}
				elseif ( $optType == 'array' ) { //arrays are textareas, where each is separated by newline
					$optValue = \array_filter( \explode( "\n", esc_textarea( $optValue ) ), '\trim' );
				}
				elseif ( $optType == 'comma_separated_lists' ) {
					$optValue = Services::Data()->extractCommaSeparatedList( $optValue );
				}
				/* elseif ( $optType == 'multiple_select' ) { } */
			}

			// Prevent overwriting of non-editable fields
			if ( !\in_array( $optType, [ 'noneditable_text' ] ) ) {
				$this->mod->opts()->setOpt( $optKey, $optValue );
			}
		}

		// Handle Import/Export exclusions
		if ( self::con()->isPremiumActive() ) {
			( new SaveExcludedOptions() )
				->setMod( $this->mod )
				->save( $form );
		}

		$this->mod->saveModOptions( true );
	}
}