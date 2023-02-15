<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Legacy;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

class RecaptchaJs extends BaseRender {

	use Traits\AuthNotRequired;

	public const SLUG = 'legacy_recaptcha_js';
	public const TEMPLATE = '/snippets/anti_bot/google_recaptcha_js.twig';

	protected function getRequiredDataKeys() :array {
		return [
			'sitekey',
			'size',
			'theme',
			'invis',
		];
	}
}