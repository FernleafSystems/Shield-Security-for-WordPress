<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;

class ActionsQueueAssetFileStatusDetail extends BaseRender {

	public const SLUG = 'actions_queue_asset_file_status_detail';
	public const TEMPLATE = '/wpadmin/components/scans/scan_results_table.twig';

	protected function getRequiredDataKeys() :array {
		return [
			'subject_type',
			'subject_id',
		];
	}

	protected function getRenderData() :array {
		$options = new ScanResultsDisplayOptions();
		$subjectType = \strtolower( \trim( (string)$this->action_data[ 'subject_type' ] ) );
		$subjectId = \trim( (string)$this->action_data[ 'subject_id' ] );
		$resultsDisplayOptions = $options->currentOptionsFromActionData( $this->action_data );
		$tableBuilder = $this->buildScanResultsTableBuilder();

		return [
			'table' => $subjectType === 'theme'
				? $tableBuilder->buildThemeTable( $subjectId, $resultsDisplayOptions )
				: $tableBuilder->buildPluginTable( $subjectId, $resultsDisplayOptions ),
		];
	}

	protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
		return new ActionsQueueScanResultsTableBuilder();
	}
}
