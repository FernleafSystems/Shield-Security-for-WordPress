<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanResults;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\LoadRawTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Ufc;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Wcf;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\ResultsRetrieve;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\ForWordpress;
use FernleafSystems\Wordpress\Services\Services;

class SectionWordpress extends SectionBase {

	public function render() :string {
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/scans/results/section/wordpress/index.twig',
						$this->buildRenderData()
					);
	}

	protected function buildRenderData() :array {
		$wpData = $this->buildWordpressData();
		return Services::DataManipulation()
					   ->mergeArraysRecursive( $this->getCommonRenderData(), [
						   'strings' => [
							   'no_files'    => __( "Previous scans didn't detect any modified, missing or unrecognised files in the WordPress core directories.", 'wp-simple-firewall' ),
							   'files_found' => __( "Previous scans detected 1 or more modified, missing or unrecognised files in the WordPress core directories.", 'wp-simple-firewall' ),
						   ],
						   'vars'    => [
							   'count_items'     => $wpData[ 'vars' ][ 'count_items' ],
							   'wordpress'       => $wpData,
							   'datatables_init' => ( new ForWordpress() )
								   ->setMod( $this->getMod() )
								   ->build()
						   ]
					   ] );
	}

	private function buildWordpressData() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$count = $mod->getScanCon( Wcf::SCAN_SLUG )->countScanProblems() + $mod->getScanCon( Ufc::SCAN_SLUG )->countScanProblems();

		$WP = Services::WpGeneral();
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