<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\ForWordpress;
use FernleafSystems\Wordpress\Services\Services;

class Wordpress extends Base {

	public const SLUG = 'scanresults_wordpress';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/section/wordpress/index.twig';

	protected function getRenderData() :array {
		$wpData = $this->buildWordpressData();
		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'no_files'    => __( "Previous scans didn't detect any modified, missing, unrecognised or unidentified files in the WordPress core directories.", 'wp-simple-firewall' ),
				'files_found' => __( "Previous scans detected 1 or more modified, missing, unrecognised or unidentified files in the WordPress core directories.", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'count_items'     => $wpData[ 'vars' ][ 'count_items' ],
				'wordpress'       => $wpData,
				'datatables_init' => ( new ForWordpress() )->build()
			]
		] );
	}

	private function buildWordpressData() :array {
		$WP = Services::WpGeneral();
		$count = ( new Counts( RetrieveCount::CONTEXT_RESULTS_DISPLAY ) )->countWPFiles();
		$data = [
			'info'  => [
				'type'    => 'wordpress',
				'version' => $WP->getVersion(),
				'dir'     => wp_normalize_path( ABSPATH ),
			],
			'flags' => [
				'has_update'     => $WP->hasCoreUpdate(),
				'has_core_files' => $count > 0,
				'is_vulnerable'  => false,
			],
			'vars'  => [
				'count_items' => $count
			]
		];
		$data[ 'flags' ][ 'has_issue' ] = $data[ 'flags' ][ 'has_core_files' ]
										  || $data[ 'flags' ][ 'is_vulnerable' ];
		return $data;
	}
}