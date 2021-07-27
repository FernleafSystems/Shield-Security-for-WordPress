<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController;

class SpamController extends BaseBotDetectionController {

	protected function isEnabled() :bool {
		return !empty( $this->getOptions()->getOpt( 'form_spam_providers' ) );
	}

	/**
	 * @return Handlers\Base[]
	 */
	public function enumProviders() :array {
		return [
			new Handlers\ContactForm7(),
			new Handlers\ElementorPro(),
			new Handlers\FormidableForms(),
			new Handlers\FluentForms(),
			new Handlers\Forminator(),
			new Handlers\Groundhogg(),
			new Handlers\GravityForms(),
			new Handlers\KaliForms(),
			new Handlers\NinjaForms(),
			new Handlers\SuperForms(),
			new Handlers\SupportCandy(),
			new Handlers\WPForms(),
			new Handlers\WpForo(),
		];
	}
}