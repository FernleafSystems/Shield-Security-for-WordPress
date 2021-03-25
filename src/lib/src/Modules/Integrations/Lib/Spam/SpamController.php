<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Spam;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class SpamController {

	use ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return $this->isEnabledSpamDetect();
	}

	protected function run() {
		foreach ( $this->enumProviders() as $provider ) {
			$provider->setMod( $this->getMod() )->execute();
		}
	}

	private function isEnabledSpamDetect() :bool {
		$opts = $this->getOptions();
		return ( $opts->isOpt( 'enable_spam_antibot', 'Y' ) || $opts->isOpt( 'enable_spam_human', 'Y' ) )
			   && !empty( $opts->getOpt( 'form_spam_providers' ) );
	}

	/**
	 * @return Handlers\Base[]
	 */
	private function enumProviders() :array {
		return [
			new Handlers\ContactForm7(),
			new Handlers\ElementorPro(),
			new Handlers\FormidableForms(),
			new Handlers\FluentForms(),
			new Handlers\Forminator(),
			new Handlers\GravityForms(),
			new Handlers\KaliForms(),
			new Handlers\NinjaForms(),
			new Handlers\WPForms(),
			new Handlers\WpForo(),
		];
	}
}