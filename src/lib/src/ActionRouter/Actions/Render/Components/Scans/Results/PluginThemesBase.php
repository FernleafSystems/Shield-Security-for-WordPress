<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\ForPluginTheme;
use FernleafSystems\Wordpress\Services\Services;

abstract class PluginThemesBase extends Base {

	protected function getRenderData() :array {
		$con = $this->getCon();
		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'ptg_name'          => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
				'ptg_not_available' => __( 'The Plugin & Theme File Guard Scanner is only available with ShieldPRO.', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'ptg_is_restricted' => $con->getModule_HackGuard()
										   ->getScansCon()
										   ->AFS()
										   ->isRestrictedPluginThemeScan(),
			],
			'vars'    => [
				'datatables_init' => ( new ForPluginTheme() )->build()
			]
		] );
	}

	protected function getVulnerabilities() :Scans\Wpv\ResultsSet {
		try {
			$vulnerable = $this->getCon()
							   ->getModule_HackGuard()
							   ->getScansCon()
							   ->WPV()
							   ->getResultsForDisplay();
		}
		catch ( \Exception $e ) {
			$vulnerable = new Scans\Wpv\ResultsSet();
		}
		return $vulnerable;
	}

	protected function getAbandoned() :Scans\Apc\ResultsSet {
		try {
			$abandoned = $this->getCon()
							  ->getModule_HackGuard()
							  ->getScansCon()
							  ->APC()
							  ->getResultsForDisplay();
		}
		catch ( \Exception $e ) {
			$abandoned = new Scans\Apc\ResultsSet();
		}
		return $abandoned;
	}
}