<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController;

class SpamController extends BaseBotDetectionController {

	public function getSelectedProvidersOptKey() :string {
		return 'form_spam_providers';
	}

	public function enumProviders() :array {
		return [
			'calderaforms'    => Handlers\CalderaForms::class,
			'contactform7'    => Handlers\ContactForm7::class,
			'elementorpro'    => Handlers\ElementorPro::class,
			'fluentforms'     => Handlers\FluentForms::class,
			'formidableforms' => Handlers\FormidableForms::class,
			'forminator'      => Handlers\Forminator::class,
			'gravityforms'    => Handlers\GravityForms::class,
			'groundhogg'      => Handlers\Groundhogg::class,
			'happyforms'      => Handlers\HappyForms::class,
			'kaliforms'       => Handlers\KaliForms::class,
			'ninjaforms'      => Handlers\NinjaForms::class,
			'superforms'      => Handlers\SuperForms::class,
			'supportcandy'    => Handlers\SupportCandy::class,
			'wpforms'         => Handlers\WPForms::class,
			'wpforo'          => Handlers\WpForo::class,
		];
	}
}