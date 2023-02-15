<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ByPassIpBlock;

class Base extends BaseRender {

	use AuthNotRequired;
	use ByPassIpBlock;
}