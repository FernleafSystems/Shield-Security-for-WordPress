<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam;

use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Find;

class SpamController extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseBotDetectionController {

	protected function canRun() :bool {
		return parent::canRun() && self::con()->caps->canThirdPartyScanSpam();
	}

	public function getSelectedProvidersOptKey() :string {
		return 'form_spam_providers';
	}

	/**
	 * @inheritDoc
	 */
	public function enumProviders() :array {
		return [
			Find::ARFORMS_LITE     => Handlers\ArformsLite::class,
			Find::CALDERA_FORMS    => Handlers\CalderaForms::class,
			Find::CONTACT_FORM_7   => Handlers\ContactForm7::class,
			Find::ELEMENTOR_PRO    => Handlers\ElementorPro::class,
			Find::FLUENT_FORMS     => Handlers\FluentForms::class,
			Find::FORMIDABLE_FORMS => Handlers\FormidableForms::class,
			Find::FORMINATOR       => Handlers\Forminator::class,
			Find::GRAVITY_FORMS    => Handlers\GravityForms::class,
			Find::GROUNDHOGG       => Handlers\Groundhogg::class,
			Find::HAPPY_FORMS      => Handlers\HappyForms::class,
			Find::KALI_FORMS       => Handlers\KaliForms::class,
			Find::NINJA_FORMS      => Handlers\NinjaForms::class,
			Find::SUPER_FORMS      => Handlers\SuperForms::class,
			Find::SUPPORT_CANDY    => Handlers\SupportCandy::class,
			Find::WEFORMS          => Handlers\WeForms::class,
			Find::WP_FORMS         => Handlers\WPForms::class,
			Find::WP_FORO          => Handlers\WpForo::class,
		];
	}
}