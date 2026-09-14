<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\LoginRequestValues;

/**
 * @phpstan-import-type LoginIntentRenderData from LoginRequestValues
 */
abstract class Base extends BaseRender {

	use AuthNotRequired;

	/**
	 * @return LoginIntentRenderData
	 */
	protected function loginIntentRenderData() :array {
		/** @var LoginIntentRenderData $data */
		$data = $this->action_data;
		return $data;
	}
}
