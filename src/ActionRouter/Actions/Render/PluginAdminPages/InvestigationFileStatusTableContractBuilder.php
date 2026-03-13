<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Investigation\InvestigationTableContract,
	Actions\Render\Components,
	Actions\InvestigationTableAction,
	Actions\ScanResultsTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\ForFileScanResults as InvestigationFileScanResultsTableBuilder;

class InvestigationFileStatusTableContractBuilder {

	use PluginControllerConsumer;
	use InvestigateRenderContracts;

	/**
	 * @return array<string,mixed>
	 */
	public function build( string $subjectType, string $subjectId ) :array {
		$tableAction = ActionData::Build( InvestigationTableAction::class );

		$fileTable = $this->buildTableContainerContract(
			__( 'File Scan Status', 'wp-simple-firewall' ),
			'warning',
			InvestigationTableContract::TABLE_TYPE_FILE_SCAN_RESULTS,
			$subjectType,
			$subjectId,
			( new InvestigationFileScanResultsTableBuilder() )->setSubject( $subjectType, $subjectId )->buildRaw(),
			$tableAction,
			self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS )
		);
		$fileTable[ 'full_log_text' ] = __( 'Full Scan Results', 'wp-simple-firewall' );
		$fileTable[ 'full_log_button_class' ] = 'btn btn-primary btn-sm';
		$fileTable[ 'show_header' ] = false;
		$fileTable[ 'scan_results_action' ] = ActionData::Build( ScanResultsTableAction::class, true, [
			'type' => $subjectType === InvestigationTableContract::SUBJECT_TYPE_CORE ? 'wordpress' : $subjectType,
			'file' => $subjectId,
		] );
		$fileTable[ 'render_item_analysis' ] = ActionData::BuildAjaxRender( Components\Scans\ItemAnalysis\Container::class );
		$fileTable[ 'is_flat' ] = true;

		return $this->normalizeInvestigationTableContract( $fileTable );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildWithEmptyState(
		string $subjectType,
		string $subjectId,
		int $resultCount,
		string $emptyText,
		string $emptyStatus = 'info'
	) :array {
		return $this->withEmptyStateTableContract(
			$this->build( $subjectType, $subjectId ),
			$resultCount,
			$emptyText,
			$emptyStatus
		);
	}
}
