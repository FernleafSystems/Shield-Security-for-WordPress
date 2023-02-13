<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

abstract class BaseLoginIntentPage extends Actions\Render\FullPage\BaseFullPageRender {

	use Actions\Traits\AuthNotRequired;
}