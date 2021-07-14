<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\LoadRawTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
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
						   'vars' => [
							   'wordpress' => $wpData
						   ]
					   ] );
	}

	private function buildWordpressData() :array {

		$coreFilesData = ( new LoadRawTableData() )
			->setMod( $this->getMod() )
			->loadForWordPress();

		$WP = Services::WpGeneral();
		$data = [
			'info'  => [
				'type'    => 'theme',
				'version' => $WP->getVersion(),
				'dir'     => wp_normalize_path( ABSPATH ),
			],
			'flags' => [
				'has_update'     => $WP->hasCoreUpdate(),
				'has_core_files' => !empty( $coreFilesData ),
				'is_vulnerable'  => false,
			]
		];
		$data[ 'flags' ][ 'has_issue' ] = $data[ 'flags' ][ 'has_core_files' ]
										  || $data[ 'flags' ][ 'is_vulnerable' ];
		return $data;
	}
}