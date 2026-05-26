<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops as ReportDB;

class DisplayReportAdmin extends FullPageDisplayStatic {

	use SecurityAdminNotRequired;

	public const SLUG = 'display_full_page_report_admin';

	/**
	 * @throws ActionException
	 */
	protected function retrieveContent() :string {
		$reportID = (string)( $this->action_data[ 'report_unique_id' ] ?? '' );
		if ( $reportID === '' ) {
			throw new ActionException( __( 'Report could not be found.', 'wp-simple-firewall' ) );
		}

		/** @var ReportDB\Select $select */
		$select = self::con()->db_con->reports->getQuerySelector();
		/** @var ?ReportDB\Record $report */
		$report = $select->filterByReportID( $reportID )->first();
		if ( empty( $report ) ) {
			throw new ActionException( __( 'Report could not be found.', 'wp-simple-firewall' ) );
		}
		return \gzinflate( $report->content );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'report_unique_id'
		];
	}

	protected function getMinimumUserAuthCapability() :string {
		return (string)( self::con()->cfg->properties[ 'base_permissions' ] ?? 'manage_options' );
	}
}
