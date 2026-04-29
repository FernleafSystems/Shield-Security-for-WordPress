<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;

class ActionsQueueAssetFileStatusDetail extends BaseRender {

	public const SLUG = 'actions_queue_asset_file_status_detail';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/scan_results_access_detail.twig';

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

		return $this->buildScansResultsViewBuilder()->buildActionsQueueSubjectTablePane(
			$subjectType,
			$subjectId,
			$resultsDisplayOptions
		);
	}

	protected function buildScansResultsViewBuilder() :ScansResultsViewBuilder {
		return new ScansResultsViewBuilder();
	}
}
