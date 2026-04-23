<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;

class ActionsQueueAssetFileStatusDetail extends BaseRender {

	public const SLUG = 'actions_queue_asset_file_status_detail';
	public const TEMPLATE = '/wpadmin/components/scans/scan_results_table.twig';

	protected function getRequiredDataKeys() :array {
		return [
			'type',
			'file',
		];
	}

	protected function getRenderData() :array {
		$options = new ScanResultsDisplayOptions();

		return [
			'table' => $this->buildScanResultsTableBuilder()->buildTableForScope(
				(string)$this->action_data[ 'type' ],
				(string)$this->action_data[ 'file' ],
				$this->emptyTextForScope( (string)$this->action_data[ 'type' ] ),
				$options->currentOptionsFromActionData( $this->action_data )
			),
		];
	}

	private function emptyTextForScope( string $type ) :string {
		switch ( \strtolower( \trim( $type ) ) ) {
			case 'malware':
				return __( "Previous scans didn't detect any files suspected of being malware.", 'wp-simple-firewall' );
			case 'plugin':
				return __( "Previous scans didn't detect any modified, missing, or unrecognised files in plugin directories.", 'wp-simple-firewall' );
			case 'theme':
				return __( "Previous scans didn't detect any modified, missing, or unrecognised files in theme directories.", 'wp-simple-firewall' );
			case 'wordpress':
			default:
				return __( "Previous scans didn't detect any modified, missing, or unrecognised files in the WordPress core directories.", 'wp-simple-firewall' );
		}
	}

	protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
		return new ActionsQueueScanResultsTableBuilder();
	}
}
