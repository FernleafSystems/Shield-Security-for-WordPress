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
			$title = \trim( $form[ 'title' ] ?? '' );
			if ( empty( $title ) ) {
				throw new \Exception( __( 'Please provide a title for the report.', 'wp-simple-firewall' ) );
			}

			$startDate = $this->carbonFromFormDate( (string)( $form[ 'start_date' ] ?? '' ) )->startOfDay();
			$endDate = $this->carbonFromFormDate( (string)( $form[ 'end_date' ] ?? '' ) )->endOfDay();
			if ( $endDate->lessThan( $startDate ) ) {
				throw new \Exception( __( 'Please ensure the end date is on or after the start date.', 'wp-simple-firewall' ) );
			}

			( new ReportGenerator() )->custom(
				$title,
				$startDate->timestamp,
				$endDate->timestamp,
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
			$msg = __( 'Failed to create custom report.', 'wp-simple-firewall' );
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => $success
		];
	}

	private function carbonFromFormDate( string $date ) :Carbon {
		if ( !\preg_match( '#^\d{4}-\d{2}-\d{2}$#', $date ) ) {
			throw new \Exception( __( 'Please provide a valid date using YYYY-MM-DD format.', 'wp-simple-firewall' ) );
		}
		$date = \array_map( 'intval', \explode( '-', $date ) );
		if ( !\checkdate( $date[ 1 ], $date[ 2 ], $date[ 0 ] ) ) {
			throw new \Exception( __( 'Please provide a valid date.', 'wp-simple-firewall' ) );
		}

		return Services::Request()
					   ->carbon( true )
					   ->setDate( $date[ 0 ], $date[ 1 ], $date[ 2 ] );
	}
}
