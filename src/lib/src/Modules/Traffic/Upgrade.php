<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

class Upgrade extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Upgrade {

	protected function upgrade_1200() {
		( new Lib\Ops\ConvertLegacy() )
			->setMod( $this->getMod() )
			->run();

		// Add "This Server" as a default exclusion.
		/** @var Options $opts */
		$opts = $this->getOptions();
		$excl = $opts->getReqTypeExclusions();
		$excl[] = 'server';
		$opts->setOpt( 'type_exclusions', $excl );
	}
}