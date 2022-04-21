<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render\BaseTemplateRenderer;
use FernleafSystems\Wordpress\Services\Services;

class Renderer extends BaseTemplateRenderer {

	use ModConsumer;

	public function getRenderData() :array {
		$data = parent::getRenderData();

		if ( empty( $data[ 'unique_render_id' ] ) ) {
			$data[ 'unique_render_id' ] = 'noticeid-'.uniqid();
		}

		$data[ 'strings' ] = Services::DataManipulation()->mergeArraysRecursive(
			$this->getMod()->getStrings()->getDisplayStrings(),
			$data[ 'strings' ] ?? []
		);

		return $data;
	}
}