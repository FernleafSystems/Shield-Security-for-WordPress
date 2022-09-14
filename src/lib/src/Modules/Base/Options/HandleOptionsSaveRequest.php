<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options\SaveExcludedOptions;
use FernleafSystems\Wordpress\Services\Services;

class HandleOptionsSaveRequest {

	use ModConsumer;

	private $form;

	public function handleSave() :bool {
		try {
			$form = $this->getForm();
			if ( empty( $form ) ) {
				throw new \Exception( 'options form parameters were empty.' );
			}

			$this->setMod( $this->getCon()->getModule( $form[ 'working_mod' ] ) );
			$con = $this->getCon();
			if ( !$con->isPluginAdmin() ) {
				throw new \Exception( __( "You don't currently have permission to save settings.", 'wp-simple-firewall' ) );
			}
			$this->doSaveStandardOptions();
			$this->getMod()->saveModOptions( true );

			// auto-import notify: ONLY when the options are being updated with a MANUAL save.
			if ( !wp_next_scheduled( $con->prefix( 'importexport_notify' ) ) ) {
				wp_schedule_single_event( Services::Request()->ts() + 30, $con->prefix( 'importexport_notify' ) );
			}

			$success = true;
		}
		catch ( \Exception $e ) {
			$success = false;
		}
		return $success;
	}

	private function getForm() :array {
		if ( !isset( $this->form ) ) {
			$this->form = FormParams::Retrieve( FormParams::ENC_BASE64 );
		}
		return $this->form;
	}

	/**
	 * @throws \Exception
	 */
	private function doSaveStandardOptions() {
		// standard options use b64 and fail-over to lz-string
		$form = $this->getForm();

		$optsAndTypes = array_map(
			function ( $optDef ) {
				return $optDef[ 'type' ];
			},
			$this->getOptions()->getVisibleOptions()
		);
		foreach ( $optsAndTypes as $optKey => $optType ) {

			$optValue = $form[ $optKey ] ?? null;
			if ( is_null( $optValue ) ) {

				if ( in_array( $optType, [ 'text', 'email' ] ) ) { //text box, and it's null, don't update
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
					$optValue = trim( $optValue );
				}
				if ( $optType == 'integer' ) {
					$optValue = intval( $optValue );
				}
				elseif ( $optType == 'password' ) {
					$sTempValue = trim( $optValue );
					if ( empty( $sTempValue ) ) {
						continue;
					}

					$confirm = $form[ $optKey.'_confirm' ] ?? null;
					if ( $sTempValue !== $confirm ) {
						throw new \Exception( __( 'Password values do not match.', 'wp-simple-firewall' ) );
					}

					$optValue = md5( $sTempValue );
				}
				elseif ( $optType == 'array' ) { //arrays are textareas, where each is separated by newline
					$optValue = array_filter( explode( "\n", esc_textarea( $optValue ) ), 'trim' );
				}
				elseif ( $optType == 'comma_separated_lists' ) {
					$optValue = Services::Data()->extractCommaSeparatedList( $optValue );
				}
				/* elseif ( $optType == 'multiple_select' ) { } */
			}

			// Prevent overwriting of non-editable fields
			if ( !in_array( $optType, [ 'noneditable_text' ] ) ) {
				$this->getOptions()->setOpt( $optKey, $optValue );
			}
		}

		// Handle Import/Export exclusions
		if ( $this->getCon()->isPremiumActive() ) {
			( new SaveExcludedOptions() )
				->setMod( $this->getMod() )
				->save( $form );
		}
	}
}