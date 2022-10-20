<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Legacy;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits;

class RecaptchaJs extends BaseRender {

	use Traits\AuthNotRequired;

	const SLUG = 'legacy_recaptcha_js';
	const TEMPLATE = '/snippets/anti_bot/google_recaptcha_js.twig';

	protected function getRequiredDataKeys() :array {
		return [
			'sitekey',
			'size',
			'theme',
			'invis',
		];
	}
}