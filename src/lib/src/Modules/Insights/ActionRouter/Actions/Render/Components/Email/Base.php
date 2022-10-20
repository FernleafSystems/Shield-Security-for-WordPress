<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

abstract class Base extends Actions\Render\BaseRender {

	use Actions\Traits\AuthNotRequired;
}