<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\SpamHandlers\{
	ContactForm7,
	GravityForms,
	WPForms,
	WpForo
};

class Processor extends BaseShield\Processor {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$mod->getControllerMWP()->execute();
		$this->launchSpamHandlers();
	}

	private function launchSpamHandlers() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isEnabledSpamDetect() ) {
			( new ContactForm7() )
				->setMod( $this->getMod() )
				->execute();
			( new GravityForms() )
				->setMod( $this->getMod() )
				->execute();
			( new WPForms() )
				->setMod( $this->getMod() )
				->execute();
			( new WpForo() )
				->setMod( $this->getMod() )
				->execute();
		}
	}
}