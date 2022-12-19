<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

abstract class Base extends Actions\Render\BaseRender {

	use Actions\Traits\AuthNotRequired;
}