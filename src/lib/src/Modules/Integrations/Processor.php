<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\SpamHandlers\{
	ContactForm7,
	WpForo
};

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getControllerMWP()->execute();
	}

	private function launchSpamHandlers() {
		( new ContactForm7() )
			->setMod( $this->getMod() )
			->execute();
		( new WpForo() )
			->setMod( $this->getMod() )
			->execute();
	}
}