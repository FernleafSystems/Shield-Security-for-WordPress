<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\SpamHandlers;

class WPFormsLite extends Base {

	const SLUG = 'wpforms';

	private $workingFormID = null;

	protected function run() {
		add_filter( 'wpforms_process_before_form_data',
			function ( $formData, $formEntry ) {
				$this->workingFormID = absint( $formEntry[ 'id' ] );
				return $formData;
			},
			1000, 2
		);

		add_filter( 'wpforms_process_initial_errors', function ( $errors, $formData ) {

			if ( empty( $errors[ $this->workingFormID ] ) && $this->isSpamBot() ) {
				$errors[ $this->workingFormID ] = [
					'header' => __( 'Shield detected this as a SPAM Bot submission.' ),
				];
			}

			return $errors;
		}, 1000, 2 );
	}

	protected function isPluginInstalled() :bool {
		return defined( 'WPFORMS_VERSION' ) && function_exists( 'wpforms' );
	}
}