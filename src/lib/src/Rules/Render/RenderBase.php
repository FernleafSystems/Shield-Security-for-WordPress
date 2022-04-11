<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\RulesControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render\BaseTemplateRenderer;

abstract class RenderBase extends BaseTemplateRenderer {

	use RulesControllerConsumer;

	protected function getTemplateBaseDir() :string {
		return '/wpadmin_pages/insights/rules';
	}
}