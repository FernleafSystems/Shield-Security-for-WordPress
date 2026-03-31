<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;

class ActionsQueueAssetFileStatusDetail extends BaseRender {

	public const SLUG = 'actions_queue_asset_file_status_detail';
	public const TEMPLATE = '/wpadmin/components/investigate/table_container.twig';

	protected function getRequiredDataKeys() :array {
		return [
			'subject_type',
			'subject_id',
		];
	}

	protected function getRenderData() :array {
		$options = new ActionsQueueScanResultsOptions();

		return [
			'table' => ( new InvestigationFileStatusTableContractBuilder() )->build(
				(string)$this->action_data[ 'subject_type' ],
				(string)$this->action_data[ 'subject_id' ],
				self::con()->plugin_urls->actionsQueueScans(),
				$options->buildActionData(
					$options->fromActionData( $this->action_data )
				)
			),
		];
	}
}
