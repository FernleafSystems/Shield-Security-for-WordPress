<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility\CleanOutOldGuardFiles;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	/**
	 * Repairs the state where the PTGuard was recreating multiple directories for the ptguard files.
	 * Here we delete everything except the first valid PTGuard dir we find.
	 *
	 * Going forward from 16.1.14, we don't attempt to migrate. We should never have been repeatedly trying to migrate
	 * in the first place - it should have been an upgrade process.
	 */
	protected function upgrade_16114() {
		( new CleanOutOldGuardFiles() )->execute();
	}
}