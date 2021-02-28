<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Spam;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SpamController {

	use ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return !Services::WpUsers()->isUserLoggedIn();
	}

	protected function run() {
		if ( $this->isEnabledSpamDetect() ) {
			( new Handlers\ContactForm7() )
				->setMod( $this->getMod() )
				->execute();
			( new Handlers\FormidableForms() )
				->setMod( $this->getMod() )
				->execute();
			( new Handlers\GravityForms() )
				->setMod( $this->getMod() )
				->execute();
			( new Handlers\NinjaForms() )
				->setMod( $this->getMod() )
				->execute();
			( new Handlers\WPForms() )
				->setMod( $this->getMod() )
				->execute();
			( new Handlers\WpForo() )
				->setMod( $this->getMod() )
				->execute();
		}
	}

	private function isEnabledSpamDetect() :bool {
		$opts = $this->getOptions();
		return ( $opts->isOpt( 'enable_spam_antibot', 'Y' ) || $opts->isOpt( 'enable_spam_human', 'Y' ) )
			   && !empty( $opts->getOpt( 'form_spam_providers' ) );
	}
}