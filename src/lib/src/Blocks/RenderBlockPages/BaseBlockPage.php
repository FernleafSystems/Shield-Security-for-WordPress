<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render\BasePageDisplay;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseBlockPage extends BasePageDisplay {

	use ModConsumer;

	protected function getResponseCode() :int {
		return 503;
	}

	protected function getData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			$this->getMod()->getUIHandler()->getBaseDisplayData(),
			parent::getData(),
			$this->getPageSpecificData()
		);
	}

	protected function getPageSpecificData() :array {
		return [];
	}

	protected function getTemplateBaseDir() :string {
		return '/pages/block/';
	}
}