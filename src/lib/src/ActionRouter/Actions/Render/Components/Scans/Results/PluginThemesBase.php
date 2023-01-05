<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Apc,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\ForPluginTheme;
use FernleafSystems\Wordpress\Services\Services;

abstract class PluginThemesBase extends Base {

	protected function getRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'ptg_name'          => __( 'Plugin/Theme Guard', 'wp-simple-firewall' ),
				'ptg_not_available' => __( 'The Plugin & Theme File Guard Scanner is only available with ShieldPRO.', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'ptg_is_restricted' => $this->getScanConAFS()->isRestrictedPluginThemeScan(),
			],
			'vars'    => [
				'datatables_init' => ( new ForPluginTheme() )
					->setMod( $this->getMod() )
					->build()
			]
		] );
	}

	protected function getVulnerabilities() :Scans\Wpv\ResultsSet {
		if ( !isset( $this->vulnerable ) ) {
			/** @var ModCon $mod */
			$mod = $this->primary_mod;
			try {
				$this->vulnerable = $mod->getScanCon( Wpv::SCAN_SLUG )->getResultsForDisplay();
			}
			catch ( \Exception $e ) {
				$this->vulnerable = new Scans\Wpv\ResultsSet();
			}
		}
		return $this->vulnerable;
	}

	protected function getAbandoned() :Scans\Apc\ResultsSet {
		if ( !isset( $this->abandoned ) ) {
			/** @var ModCon $mod */
			$mod = $this->primary_mod;
			try {
				$this->abandoned = $mod->getScanCon( Apc::SCAN_SLUG )->getResultsForDisplay();
			}
			catch ( \Exception $e ) {
				$this->abandoned = new Scans\Apc\ResultsSet();
			}
		}
		return $this->abandoned;
	}
}