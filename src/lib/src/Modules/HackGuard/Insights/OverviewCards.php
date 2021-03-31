<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	public function build() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'Hack Guard', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Threats/Intrusions Detection & Repair', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$cards[ 'mod' ] = $this->getModDisabledCard();
		}
		else {
			$bGoodFrequency = $opts->getScanFrequency() > 1;
			$cards[ 'frequency' ] = [
				'name'    => __( 'Scan Frequency', 'wp-simple-firewall' ),
				'state'   => $bGoodFrequency ? 1 : 0,
				'summary' => $bGoodFrequency ?
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
				$this->getCardsForUfc(),
				$this->getCardsForApc()
			);
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'hack_protect' => $cardSection ];
	}

	private function getCardsForWcf() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		$scanCon = $mod->getScanCon( HackGuard\Scan\Controller\Wcf::SCAN_SLUG );

		$cards = [];

		$bCore = $scanCon->isEnabled();
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => sprintf( '%s: %s', __( 'Scanner', 'wp-simple-firewall' ), $scanCon->getScanName() ),
			'state'   => $bCore ? 1 : -2,
			'summary' => $bCore ?
				__( 'WP Core files are scanned automatically', 'wp-simple-firewall' )
				: __( "WP Core files aren't automatically scanned!", 'wp-simple-firewall' ),
			'href'    => $mod->getUrl_DirectLinkToOption( 'enable_core_file_integrity_scan' ),
			'help'    => __( 'Automatic WordPress Core File scanner should be turned-on.', 'wp-simple-firewall' )
		];
		if ( $bCore ) {
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
		if ( $scanCon->getScanHasProblem() ) {
			$cards[ 'wcf_problem' ] = [
				'name'    => __( 'Core Files Changed', 'wp-simple-firewall' ),
				'summary' => __( 'WordPress core files have been modified.', 'wp-simple-firewall' ),
				'href'    => $mod->getUrlForScanResults(),
				'state'   => -2,
				'help'    => __( 'Scan WP core files and repair any files that are flagged as modified.', 'wp-simple-firewall' )
			];
		}

		return $cards;
	}

	private function getCardsForUfc() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Options $opts */
		$opts = $this->getOptions();
		$scanCon = $mod->getScanCon( HackGuard\Scan\Controller\Ufc::SCAN_SLUG );

		$cards = [];

		$bUcf = $scanCon->isEnabled();
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => __( 'Unrecognised Files', 'wp-simple-firewall' ),
			'summary' => $bUcf ?
				__( 'WP Core directories are scanned regularly for unrecognised files', 'wp-simple-firewall' )
				: __( "WP Core directories are never scanned for unrecognised files!", 'wp-simple-firewall' ),
			'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
			'help'    => __( 'Automatic scanning for non-WordPress core files is recommended.', 'wp-simple-firewall' ),
			'state'   => $bUcf ? 1 : -2,
		];
		if ( $bUcf ) {
			$cards[ 'ufc_repair' ] = [
				'name'    => __( 'Unrecognised Files Removal', 'wp-simple-firewall' ),
				'summary' => $opts->isUfsDeleteFiles() ?
					__( 'Unrecognised files are automatically removed', 'wp-simple-firewall' )
					: __( "Unrecognised files aren't automatically removed!", 'wp-simple-firewall' ),
				'state'   => $opts->isUfsDeleteFiles() ? 1 : -1,
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_ufc' ),
			];
		}
		if ( $scanCon->getScanHasProblem() ) {
			$cards[ 'ufc_problem' ] = [
				'name'    => __( 'Unrecognised Files', 'wp-simple-firewall' ),
				'summary' => __( 'Unrecognised files found in WordPress Core directory.', 'wp-simple-firewall' ),
				'help'    => __( 'Scan and remove any files that are not meant to be in the WP core directories.', 'wp-simple-firewall' ),
				'state'   => -2,
				'href'    => $mod->getUrlForScanResults(),
			];
		}

		return $cards;
	}

	private function getCardsForPtg() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$scanCon = $mod->getScanCon( HackGuard\Scan\Controller\Ptg::SCAN_SLUG );

		$cards = [];

		$bPtg = $scanCon->isEnabled();
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => sprintf( '%s: %s', __( 'Scanner', 'wp-simple-firewall' ), $scanCon->getScanName() ),
			'summary' => $bPtg ?
				__( 'Plugins and Themes are guarded against tampering', 'wp-simple-firewall' )
				: __( "Plugins and Themes are never scanned for tampering!", 'wp-simple-firewall' ),
			'state'   => $bPtg ? 1 : -2,
			'href'    => $mod->getUrl_DirectLinkToOption( 'ptg_enable' ),
			'help'    => __( 'Automatic detection of plugin/theme modifications is recommended.', 'wp-simple-firewall' ),
		];
		if ( $scanCon->getScanHasProblem() ) {
			$cards[ 'ptg_problem' ] = [
				'name'    => $scanCon->getScanName(),
				'summary' => __( 'A plugin/theme was found to have been modified.', 'wp-simple-firewall' ),
				'state'   => -2,
				'href'    => $mod->getUrlForScanResults(),
				'help'    => __( 'Reviewing modifications to your plugins/themes is recommended.', 'wp-simple-firewall' ),

			];
		}

		return $cards;
	}

	private function getCardsForMal() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$scanCon = $mod->getScanCon( HackGuard\Scan\Controller\Mal::SCAN_SLUG );

		$cards = [];

		$malEnabled = $scanCon->isEnabled();
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => sprintf( '%s: %s', __( 'Scanner', 'wp-simple-firewall' ), $scanCon->getScanName() ),
			'summary' => $malEnabled ?
				sprintf( __( '%s Scanner runs automatically.' ), $scanCon->getScanName() )
				: sprintf( __( "%s Scanner isn't set to run automatically." ), $scanCon->getScanName() ),
			'state'   => $malEnabled ? 1 : -2,
			'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_mal' ),
			'help'    => __( 'Automatic detection of Malware is recommended.', 'wp-simple-firewall' )
		];
		if ( $malEnabled && $scanCon->getScanHasProblem() ) {
			$cards[ 'mal_problem' ] = [
				'name'    => __( 'Malware Detected', 'wp-simple-firewall' ),
				'summary' => __( 'Potential Malware files have been discovered.', 'wp-simple-firewall' ),
				'state'   => -2,
				'href'    => $mod->getUrlForScanResults(),
				'help'    => __( 'Files identified as potential malware should be examined as soon as possible.', 'wp-simple-firewall' ),
			];
		}

		return $cards;
	}

	private function getCardsForApc() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$scanCon = $mod->getScanCon( HackGuard\Scan\Controller\Apc::SCAN_SLUG );

		$cards = [];

		$bApc = $scanCon->isEnabled();
		$cards[ $scanCon::SCAN_SLUG ] = [
			'name'    => sprintf( '%s: %s', __( 'Scanner', 'wp-simple-firewall' ), $scanCon->getScanName() ),
			'state'   => $bApc ? 1 : -1,
			'summary' => $bApc ?
				sprintf( __( '%s Scanner is enabled.' ), $scanCon->getScanName() )
				: sprintf( __( '%s Scanner is not enabled.' ), $scanCon->getScanName() ),
			'href'    => $mod->getUrl_DirectLinkToSection( 'section_scan_apc' ),
		];
		if ( $scanCon->getScanHasProblem() ) {
			$cards[ 'apc_problem' ] = [
				'name'    => __( 'Plugin Abandoned' ),
				'summary' => __( 'At least 1 plugin on your site is abandoned.', 'wp-simple-firewall' ),
				'state'   => -1,
				'href'    => $mod->getUrlForScanResults(),
				'help'    => __( 'Plugins that have been abandoned represent a potential risk to your site.', 'wp-simple-firewall' )
			];
		}

		return $cards;
	}

	private function getCardsForWpv() :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$scanCon = $mod->getScanCon( HackGuard\Scan\Controller\Wpv::SCAN_SLUG );

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
		if ( $scanCon->getScanHasProblem() ) {
			$cards[ 'wpv_problem' ] = [
				'name'    => __( 'Vulnerable Plugin', 'wp-simple-firewall' ),
				'summary' => __( 'Plugin with vulnerabilities found on site.', 'wp-simple-firewall' ),
				'state'   => -2,
				'href'    => $mod->getUrlForScanResults(),
				'help'    => __( 'Items with known vulnerabilities should be updated, removed, or replaced.', 'wp-simple-firewall' )
			];
		}

		return $cards;
	}
}