<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class ScanResultsDisplayFormSubmit extends BaseAction {

	public const SLUG = 'scan_results_display_form_submit';

	protected function exec() {
		$con = self::con();
		$form = $this->action_data[ 'form_data' ];

		try {
			if ( empty( $form ) || !\is_array( $form ) ) {
				throw new \Exception( 'No data. Please retry' );
			}

			$new = \array_keys( \array_filter( $form, function ( $setting ) {
				return $setting === 'Y';
			} ) );
			\natsort( $new );
			$con->opts->optSet( 'scan_results_table_display', $new );

			$msg = __( 'Display Options Updated', 'wp-simple-firewall' );
			$success = true;
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'page_reload' => $con->opts->optChanged( 'scan_results_table_display' ),
			'message'     => $msg,
		];
	}
}