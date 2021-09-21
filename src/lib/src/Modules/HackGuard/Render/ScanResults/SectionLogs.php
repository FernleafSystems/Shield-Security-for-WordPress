<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Render\ScanResults;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	Lib\ScanTables\LoadRawTableData,
	ModCon,
	Scan\Controller\Mal
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\ForMalware;
use FernleafSystems\Wordpress\Services\Services;

class SectionLogs extends SectionBase {

	public function render() :string {
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/scans/results/section/logs/index.twig',
						$this->buildRenderData()
					);
	}

	protected function buildRenderData() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$data = $this->buildMalwareData();
		return Services::DataManipulation()
					   ->mergeArraysRecursive( $this->getCommonRenderData(), [
						   'strings' => [
							   'no_files'       => __( "Previous scans didn't detect any files suspected of being malware.", 'wp-simple-firewall' ),
							   'files_found'    => __( "Previous scans detected 1 or more files suspected of being malware.", 'wp-simple-firewall' ),
							   'mal_restricted' => __( 'The Malware File Scanner is only available with ShieldPRO.', 'wp-simple-firewall' ),
						   ],
						   'flags'   => [
							   'mal_is_restricted' => $mod->getScanCon( Mal::SCAN_SLUG )->isRestricted(),
						   ],
						   'vars'    => [
							   'count_items'     => $data[ 'vars' ][ 'count_items' ],
							   'malware'         => $data,
							   'datatables_init' => ( new ForMalware() )
								   ->setMod( $this->getMod() )
								   ->build()
						   ]
					   ] );
	}

	private function buildMalwareData() :array {

		$filesData = ( new LoadRawTableData() )
			->setMod( $this->getMod() )
			->loadForMalware();

		$data = [
			'flags' => [
				'has_malware' => !empty( $filesData ),
			],
			'vars'  => [
				'count_items' => count( $filesData )
			]
		];
		$data[ 'flags' ][ 'has_issue' ] = $data[ 'flags' ][ 'has_malware' ];
		return $data;
	}
}