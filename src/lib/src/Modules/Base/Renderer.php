<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render\BaseTemplateRenderer;

/**
 * @deprecated 16.2
 */
class Renderer extends BaseTemplateRenderer {

	use ModConsumer;

	public function getRenderData() :array {
		return parent::getRenderData();
	}
}