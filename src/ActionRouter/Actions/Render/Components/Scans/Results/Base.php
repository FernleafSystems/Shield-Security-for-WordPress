<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\BaseScans {

	protected function getDefaults() :array {
		return \array_merge( parent::getDefaults(), [
			'display_context' => 'scan_results',
		] );
	}

	protected function isActionsQueueDisplayContext() :bool {
		return $this->action_data[ 'display_context' ] === 'actions_queue';
	}

	/**
	 * @return array{
	 *   include_ignored:bool,
	 *   include_repaired:bool,
	 *   include_deleted:bool,
	 *   ignored_only:bool
	 * }|null
	 */
	protected function getActionsQueueExplicitResultsDisplayOptions() :?array {
		return ( new \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScanResultsDisplayOptions() )
			->explicitOptionsFromActionData( $this->action_data );
	}

	protected function getRenderData() :array {
		$common = CommonDisplayStrings::pick( [ 'author_label', 'version_label', 'name_label' ] );
		return [
			'strings' => [
				'mode_panel_close' => __( 'Close', 'wp-simple-firewall' ),
				'author'           => $common[ 'author_label' ],
				'version'          => $common[ 'version_label' ],
				'name'             => $common[ 'name_label' ],
				'abandoned'        => __( 'Abandoned', 'wp-simple-firewall' ),
				'vulnerable'       => __( 'Vulnerable', 'wp-simple-firewall' ),
				'installed_at'     => __( 'Installed', 'wp-simple-firewall' ),
				'child_theme'      => __( 'Linked To Child Theme', 'wp-simple-firewall' ),
				'parent_theme'     => __( 'Linked To Parent Theme', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'upgrade' => Services::WpGeneral()->getAdminUrl_Updates()
			],
		];
	}
}
