<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class WPForms extends Base {

	private $workingFormID = null;

	protected function run() {
		add_filter( 'wpforms_process_before_form_data',
			function ( $formData, $formEntry ) {
				$this->workingFormID = \absint( $formEntry[ 'id' ] );
				return $formData;
			},
			1000, 2
		);

		add_filter( 'wpforms_process_initial_errors', function ( $errors, $formData ) {

			if ( empty( $errors[ $this->workingFormID ] ) && $this->isBotBlockRequired() ) {
				$errors[ $this->workingFormID ] = [
					'header' => $this->getCommonSpamMessage(),
				];
			}

			return $errors;
		}, 1000, 2 );
	}
}