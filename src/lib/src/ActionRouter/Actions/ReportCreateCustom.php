<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Exceptions\ReportDataEmptyException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportGenerator;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;
use FernleafSystems\Wordpress\Services\Services;

class ReportCreateCustom extends BaseAction {

	public const SLUG = 'report_create_custom';

	protected function exec() {
		$form = FormParams::Retrieve();
		try {
			( new ReportGenerator() )->custom(
				$form[ 'title' ] ?? sprintf( 'Custom Report on %s', Services::WpGeneral()->getTimeStringForDisplay() ),
				$this->carbonFromFormDate( $form[ 'start_date' ] )->startOfDay()->timestamp,
				$this->carbonFromFormDate( $form[ 'end_date' ] )->endOfDay()->timestamp,
				[
					'areas' => [
						Constants::REPORT_AREA_CHANGES => $form[ 'changes_zones' ] ?? [],
						Constants::REPORT_AREA_STATS   => $form[ 'statistics_zones' ] ?? [],
						Constants::REPORT_AREA_SCANS   => $form[ 'scans_zones' ] ?? [],
					]
				]
			);
			$msg = __( 'Custom report created, reloading reports page.', 'wp-simple-firewall' );
			$success = true;
		}
		catch ( ReportDataEmptyException $e ) {
			$success = false;
			$msg = __( 'Failed to create custom report.' );
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => $success
		];
	}

	private function carbonFromFormDate( string $date ) :Carbon {
		$date = \explode( '-', $date );
		return Services::Request()
					   ->carbon( true )
					   ->setDate( $date[ 0 ], $date[ 1 ], $date[ 2 ] );
	}
}