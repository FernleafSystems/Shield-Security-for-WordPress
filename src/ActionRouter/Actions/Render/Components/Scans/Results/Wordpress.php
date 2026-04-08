<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueScanResultsOptions,
	ScanResultsTableContractBuilder,
	ScansResultsViewBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Services\Services;

class Wordpress extends Base {

	public const SLUG = 'scanresults_wordpress';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig';
	private ?array $actionsQueuePaneCache = null;

	protected function getRenderTemplate() :string {
		return $this->isActionsQueueDisplayContext()
			? '/wpadmin/components/scans/scan_results_table.twig'
			: parent::getRenderTemplate();
	}

	protected function getRenderData() :array {
		if ( $this->isActionsQueueDisplayContext() ) {
			$emptyText = __( "Previous scans didn't detect any modified, missing, or unrecognised files in the WordPress core directories.", 'wp-simple-firewall' );
			$queueScanResultsOptions = new \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueScanResultsOptions();
			$resultsDisplayOptions = $this->getActionsQueueExplicitResultsDisplayOptions();
			$scanResultsActionData = $queueScanResultsOptions->buildDisplayContextActionData();
			if ( $resultsDisplayOptions !== null ) {
				$scanResultsActionData = $queueScanResultsOptions->buildExplicitActionData( $resultsDisplayOptions );
			}
			$table = ( new ScanResultsTableContractBuilder() )->buildFileStatusWithEmptyState(
				'core',
				'core',
				$this->getActionsQueueCount( $resultsDisplayOptions ),
				$emptyText,
				self::con()->plugin_urls->actionsQueueScans(),
				'info',
				$scanResultsActionData
			);

			return [
				'table' => $table,
			];
		}

		$pane = $this->getActionsQueuePane();

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

	private function getActionsQueuePane() :array {
		if ( $this->actionsQueuePaneCache === null ) {
			$this->actionsQueuePaneCache = ( new ScansResultsViewBuilder() )
				->buildRailPaneData( 'wordpress' );
		}

		return $this->actionsQueuePaneCache;
	}

	private function getActionsQueueCount( ?array $resultsDisplayOptions ) :int {
		$loader = $this->buildActionsQueueLoader( $resultsDisplayOptions );
		return $loader->countAll();
	}

	private function buildActionsQueueLoader( ?array $resultsDisplayOptions ) :LoadFileScanResultsTableData {
		$loader = new LoadFileScanResultsTableData();
		$loader->custom_record_retriever_wheres = [
			\sprintf( "%s.`meta_key`='is_in_core'", RetrieveBase::ABBR_RESULTITEMMETA ),
			\sprintf( "%s.`meta_value`=1", RetrieveBase::ABBR_RESULTITEMMETA ),
		];
		$loader->results_display_options = $resultsDisplayOptions
			?? ( new ActionsQueueScanResultsOptions() )->storedOptions();
		return $loader;
	}
}
