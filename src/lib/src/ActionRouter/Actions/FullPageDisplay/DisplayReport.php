<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops as ReportDB;

class DisplayReport extends FullPageDisplayStatic {

	public const SLUG = 'display_full_page_report';

	/**
	 * @throws ActionException
	 */
	protected function retrieveContent() :string {
		/** @var ReportDB\Select $select */
		$select = self::con()->db_con->reports->getQuerySelector();
		/** @var ?|ReportDB\Record $report */
		$report = $select->filterByReportID( $this->action_data[ 'report_unique_id' ] )->first();
		if ( empty( $report ) ) {
			throw new ActionException( 'Report could not be found.' );
		}
		return \gzinflate( $report->content );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'report_unique_id'
		];
	}
}