<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs\Ops as AuditDB;
use FernleafSystems\Wordpress\Services\Services;

class PageReportFormCreateNew extends BaseRender {

	public const SLUG = 'render_report_form_create_new';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/page_create_new.twig';

	protected function getRenderData() :array {
		$req = Services::Request();
		$dbh = $this->con()->getModule_AuditTrail()->getDbH_Logs();

		/** @var AuditDB\Record $firstAudit */
		$firstAudit = $dbh->getQuerySelector()
						  ->setOrderBy( 'created_at', 'ASC', true )
						  ->first();
		$lastAudit = $dbh->getQuerySelector()
						 ->setOrderBy( 'created_at', 'DESC', true )
						 ->first();

		$c = $req->carbon( true );
		return [
			'ajax'    => [
				'report_create_slug' => CreateNewAdHoc::SLUG,
			],
			'flags'   => [
				'can_run_report' => !empty( $lastAudit ) && $lastAudit->id !== $firstAudit->id,
			],
			'strings' => [
				'build_report' => __( 'Build Report', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'available_zones' => \array_filter( \array_map(
					function ( $auditor ) {
						try {
							return $auditor->getReporter()->getZoneName();
						}
						catch ( \Exception $e ) {
							return null;
						}
					},
					$this->con()->getModule_AuditTrail()->getAuditCon()->getAuditors()
				) ),
				'earliest_date'   => empty( $firstAudit ) ? $req->ts() :
					$c->setTimestamp( $firstAudit->created_at )->toIso8601String(),
				'latest_date'     => empty( $lastAudit ) ? $req->ts() :
					$c->setTimestamp( $lastAudit->created_at )->toIso8601String()
			],
		];
	}
}