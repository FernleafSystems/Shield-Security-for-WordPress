<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\ForFileScanResults as InvestigationFileScanResultsTableBuilder;

class InvestigationFileStatusTableContractBuilder {

	use InvestigateRenderContracts;

	/**
	 * @return array<string,mixed>
	 */
	public function build(
		string $subjectType,
		string $subjectId,
		string $fullLogHref,
		array $scanResultsActionData = []
	) :array {
		return $this->buildFlatScanResultsTableContract(
			__( 'File Scan Status', 'wp-simple-firewall' ),
			'warning',
			InvestigationTableContract::TABLE_TYPE_FILE_SCAN_RESULTS,
			$subjectType,
			$subjectId,
			( new InvestigationFileScanResultsTableBuilder() )->setSubject( $subjectType, $subjectId )->buildRaw(),
			\array_merge( [
				'type' => $subjectType === InvestigationTableContract::SUBJECT_TYPE_CORE ? 'wordpress' : $subjectType,
				'file' => $subjectId,
			], $scanResultsActionData ),
			$fullLogHref
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function buildWithEmptyState(
		string $subjectType,
		string $subjectId,
		int $resultCount,
		string $emptyText,
		string $fullLogHref,
		string $emptyStatus = 'info',
		array $scanResultsActionData = []
	) :array {
		return $this->withEmptyStateTableContract(
			$this->build( $subjectType, $subjectId, $fullLogHref, $scanResultsActionData ),
			$resultCount,
			$emptyText,
			$emptyStatus
		);
	}
}
