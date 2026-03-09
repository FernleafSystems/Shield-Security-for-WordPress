<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Services\Services;

class Plugins extends PluginThemesBase {

	public const SLUG = 'scanresults_plugins';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/section/assets/plugins_index.twig';

	protected function getRenderData() :array {
		$rows = $this->buildAffectedAssetRows( 'plugin' );

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'no_files'    => __( "Scans didn't detect any modified or unrecognised files in plugin directories.", 'wp-simple-firewall' ),
				'files_found' => __( 'These plugins have file-integrity findings that need review.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'count_items' => $rows[ 'count_items' ],
				'plugins'     => $rows[ 'items' ],
			]
		] );
	}
}
