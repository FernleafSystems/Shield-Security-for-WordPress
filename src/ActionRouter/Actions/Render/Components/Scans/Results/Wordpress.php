<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	InvestigationFileStatusTableContractBuilder,
	ScansResultsViewBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\{
	Counts,
	Retrieve\RetrieveCount
};
use FernleafSystems\Wordpress\Services\Services;

class Wordpress extends Base {

	public const SLUG = 'scanresults_wordpress';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig';

	protected function getRenderTemplate() :string {
		return $this->isActionsQueueDisplayContext()
			? '/wpadmin/components/investigate/table_container.twig'
			: parent::getRenderTemplate();
	}

	protected function getRenderData() :array {
		if ( $this->isActionsQueueDisplayContext() ) {
			$emptyText = __( "Previous scans didn't detect any modified, missing, or unrecognised files in the WordPress core directories.", 'wp-simple-firewall' );
			$count = ( new Counts( RetrieveCount::CONTEXT_RESULTS_DISPLAY ) )->countWPFiles();
			$table = ( new InvestigationFileStatusTableContractBuilder() )->buildWithEmptyState(
				InvestigationTableContract::SUBJECT_TYPE_CORE,
				InvestigationTableContract::SUBJECT_TYPE_CORE,
				$count,
				$emptyText
			);

			return [
				'table' => $table,
			];
		}

		$pane = ( new ScansResultsViewBuilder() )
			->buildRailPaneData( 'wordpress' );

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'no_issues' => __( "Previous scans didn't detect any modified, missing, or unrecognised files in the WordPress core directories.", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'count_items' => $pane[ 'count_items' ],
			],
			'tab'     => $pane,
			'content' => [],
		] );
	}
}
