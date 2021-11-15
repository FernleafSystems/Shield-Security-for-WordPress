<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;

class Upgrade extends Base\Upgrade {

	protected function upgrade_1300() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		( new Scan\Utilities\ConvertLegacyResults() )
			->setMod( $mod )
			->execute();

		// Ensure AFS scan is selected by default upon upgrade
		$uiTrack = $mod->getUiTrack();
		$selected = $uiTrack->selected_scans;
		$selected[] = Afs::SCAN_SLUG;
		$uiTrack->selected_scans = $selected;
		$mod->setUiTrack( $uiTrack );
	}
}