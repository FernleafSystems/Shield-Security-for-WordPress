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
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		$cards = [];

		if ( $mod->isModOptEnabled() ) {
			$goodFrequency = $opts->getScanFrequency() > 1;
			$cards[ 'frequency' ] = [
				'name'    => __( 'Scan Frequency', 'wp-simple-firewall' ),
				'state'   => $goodFrequency ? 1 : 0,
				'summary' => $goodFrequency ?
					__( 'Automatic scanners run more than once per day', 'wp-simple-firewall' )
					: __( "Automatic scanners only run once per day", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_options' ),
			];

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
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		/** @var Afs $scanCon */
		$scanCon = $mod->getScanCon( Afs::SCAN_SLUG );

		$cards = [];

		$scanCore = $scanCon->isEnabled();
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => sprintf( '%s: %s', __( 'Scanner', 'wp-simple-firewall' ), $scanCon->getScanName() ),
			'state'   => $scanCore ? 1 : -2,
			'summary' => $scanCore ?
				__( 'WP Core files are scanned automatically', 'wp-simple-firewall' )
				: __( "WP Core files aren't automatically scanned!", 'wp-simple-firewall' ),
			'href'    => $mod->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
			'help'    => __( 'Automatic WordPress Core File scanner should be turned-on.', 'wp-simple-firewall' )
		];
		if ( $scanCore ) {
			if ( !$opts->isRepairFileWP() ) {
				$cards[ 'wcf_repair' ] = [
					'name'    => __( 'WP Core File Repair', 'wp-simple-firewall' ),
					'state'   => $opts->isRepairFileWP() ? 1 : -1,
					'summary' => $opts->isRepairFileWP() ?
						__( 'Core files are automatically repaired', 'wp-simple-firewall' )
						: __( "Core files aren't automatically repaired!", 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'file_repair_areas' ),
				];
			}
		}

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
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => sprintf( '%s: %s', __( 'Scanner', 'wp-simple-firewall' ), __( 'Plugins & Themes', 'wp-simple-firewall' ) ),
			'summary' => $isPTG ?
				__( 'Plugins and Themes are guarded against tampering', 'wp-simple-firewall' )
				: __( "Plugins and Themes are never scanned for tampering!", 'wp-simple-firewall' ),
			'state'   => $isPTG ? 1 : -2,
			'href'    => $mod->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
			'help'    => __( 'Automatic detection of plugin/theme modifications is recommended.', 'wp-simple-firewall' ),
		];

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
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => sprintf( '%s: %s', __( 'Scanner', 'wp-simple-firewall' ), $scanCon->getScanName() ),
			'summary' => $malEnabled ?
				sprintf( __( '%s Scanner runs automatically.' ), $scanCon->getScanName() )
				: sprintf( __( "%s Scanner isn't set to run automatically." ), $scanCon->getScanName() ),
			'state'   => $malEnabled ? 1 : -2,
			'href'    => $mod->getUrl_DirectLinkToSection( 'section_file_guard' ),
			'help'    => __( 'Automatic detection of Malware is recommended.', 'wp-simple-firewall' )
		];
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
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => sprintf( '%s: %s', __( 'Scanner', 'wp-simple-firewall' ), $scanCon->getScanName() ),
			'state'   => $isAPC ? 1 : -1,
			'summary' => $isAPC ?
				sprintf( __( '%s Scanner is enabled.' ), $scanCon->getScanName() )
				: sprintf( __( '%s Scanner is not enabled.' ), $scanCon->getScanName() ),
			'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_apc' ),
		];
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
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => __( 'Vulnerability Scan', 'wp-simple-firewall' ),
			'state'   => $enabledWpv ? 1 : -2,
			'summary' => $enabledWpv ?
				__( 'Regularly scanning for known vulnerabilities', 'wp-simple-firewall' )
				: __( "Plugins/Themes never scanned for vulnerabilities!", 'wp-simple-firewall' ),
			'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
			'help'    => __( 'Automatic detection of vulnerabilities is recommended.', 'wp-simple-firewall' )
		];

		$bWpvAutoUpdates = $scanCon->isCronAutoRepair();
		if ( $enabledWpv ) {
			$cards[ 'wpv_repair' ] = [
				'name'    => __( 'Auto Update', 'wp-simple-firewall' ),
				'summary' => $bWpvAutoUpdates ?
					__( 'Vulnerable items are automatically updated', 'wp-simple-firewall' )
					: __( "Vulnerable items aren't automatically updated!", 'wp-simple-firewall' ),
				'state'   => $bWpvAutoUpdates ? 1 : -1,
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_wpv' ),
			];
		}
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