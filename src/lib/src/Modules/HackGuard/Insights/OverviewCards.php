<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs,
	Apc,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	protected function buildModCards() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$cards = [];

		if ( $mod->isModOptEnabled() ) {
			$cards = array_merge(
				$cards,
				$this->getCardsForWcf(),
				$this->getCardsForMal(),
				$this->getCardsForWpv(),
				$this->getCardsForPtg(),
				$this->getCardsForApc()
			);
		}

		return $cards;
	}

	protected function getSectionTitle() :string {
		return __( 'Hack Guard', 'wp-simple-firewall' );
	}

	protected function getSectionSubTitle() :string {
		return __( 'Threats/Intrusions Detection & Repair', 'wp-simple-firewall' );
	}

	private function getCardsForWcf() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var Afs $scanCon */
		$scanCon = $mod->getScanCon( Afs::SCAN_SLUG );

		$cards = [];

		$scanCore = $scanCon->isEnabled();
		if ( $scanCore && $scanCon->getScansController()->getScanResultsCount()->countWPFiles() ) {
			$cards[ 'wcf_problem' ] = [
				'name'    => sprintf( '%s: %s', __( 'Modified', 'wp-simple-firewall' ), __( 'WordPress Core Files', 'wp-simple-firewall' ) ),
				'summary' => __( 'WordPress core files have been modified.', 'wp-simple-firewall' ),
				'href'    => $this->getUrlForScanResults(),
				'state'   => -2,
				'help'    => __( 'Scan WP core files and repair any files that are flagged as modified.', 'wp-simple-firewall' )
			];
		}

		return $cards;
	}

	private function getCardsForPtg() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var Afs $scanCon */
		$scanCon = $mod->getScanCon( Afs::SCAN_SLUG );

		$cards = [];

		$isPTG = $scanCon->isEnabledPluginThemeScan();

		$status = $scanCon->getScansController()->getScanResultsCount();
		if ( $isPTG && ( $status->countPluginFiles() + $status->countPluginFiles() ) > 0 ) {
			$cards[ 'ptg_problem' ] = [
				'name'    => sprintf( '%s: %s', __( 'Modified', 'wp-simple-firewall' ), __( 'Plugins & Themes', 'wp-simple-firewall' ) ),
				'summary' => __( 'A plugin/theme was found to have been modified.', 'wp-simple-firewall' ),
				'state'   => -2,
				'href'    => $this->getUrlForScanResults(),
				'help'    => __( 'Reviewing modifications to your plugins/themes is recommended.', 'wp-simple-firewall' ),
			];
		}

		return $cards;
	}

	private function getCardsForMal() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var Afs $scanCon */
		$scanCon = $mod->getScanCon( Afs::SCAN_SLUG );

		$cards = [];

		$malEnabled = $scanCon->isEnabledMalwareScan();
		if ( $malEnabled && $scanCon->getScansController()->getScanResultsCount()->countMalware() ) {
			$cards[ 'mal_problem' ] = [
				'name'    => __( 'Potential Malware Detected', 'wp-simple-firewall' ),
				'summary' => __( 'Potential Malware files have been discovered.', 'wp-simple-firewall' ),
				'state'   => -2,
				'href'    => $this->getUrlForScanResults(),
				'help'    => __( 'Files identified as potential malware should be examined as soon as possible.', 'wp-simple-firewall' ),
			];
		}

		return $cards;
	}

	private function getCardsForApc() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$scanCon = $mod->getScanCon( Apc::SCAN_SLUG );

		$cards = [];

		$isAPC = $scanCon->isEnabled();
		if ( $isAPC && $scanCon->getScansController()->getScanResultsCount()->countAbandoned() > 0 ) {
			$cards[ 'apc_problem' ] = [
				'name'    => __( 'Plugin Abandoned' ),
				'summary' => __( 'At least 1 plugin on your site is abandoned.', 'wp-simple-firewall' ),
				'state'   => -1,
				'href'    => $this->getUrlForScanResults(),
				'help'    => __( 'Plugins that have been abandoned represent a potential risk to your site.', 'wp-simple-firewall' )
			];
		}

		return $cards;
	}

	private function getCardsForWpv() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$scanCon = $mod->getScanCon( Wpv::SCAN_SLUG );

		$cards = [];

		$enabledWpv = $scanCon->isEnabled();
		if ( $enabledWpv && $scanCon->getScansController()->getScanResultsCount()->countVulnerableAssets() > 0 ) {
			$cards[ 'wpv_problem' ] = [
				'name'    => __( 'Vulnerable Plugin', 'wp-simple-firewall' ),
				'summary' => __( 'Plugin with vulnerabilities found on site.', 'wp-simple-firewall' ),
				'state'   => -2,
				'href'    => $this->getUrlForScanResults(),
				'help'    => __( 'Items with known vulnerabilities should be updated, removed, or replaced.', 'wp-simple-firewall' )
			];
		}

		return $cards;
	}

	private function getUrlForScanResults() :string {
		return $this->getCon()->getModule_Insights()->getUrl_ScansResults();
	}
}