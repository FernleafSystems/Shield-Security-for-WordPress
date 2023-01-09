<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;

class ScanEnabledMal extends ScanEnabledBase {

	public const SLUG = 'scan_enabled_mal';
	public const WEIGHT = 30;

	protected function isProtected() :bool {
		$mod = $this->getCon()->getModule_HackGuard();
		try {
			/** @var Afs $afsCon */
			$afsCon = $this->getCon()
						   ->getModule_HackGuard()
						   ->getScanCon( Afs::SCAN_SLUG );
			return $mod->isModOptEnabled() && $afsCon->isEnabledMalwareScan();
		}
		catch ( \Exception $e ) {
			return false;
		}
	}

	public function title() :string {
		return __( 'PHP Malware Scanner', 'wp-simple-firewall' );
	}

	public function href() :string {
		return $this->getCon()->getModule_HackGuard()->isModOptEnabled() ?
			$this->link( 'enable_core_file_integrity_scan' ) : $this->link( 'enable_hack_protect' );
	}

	public function descProtected() :string {
		return __( 'PHP malware scanner is enabled.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "PHP malware scanner isn't enabled.", 'wp-simple-firewall' );
	}
}