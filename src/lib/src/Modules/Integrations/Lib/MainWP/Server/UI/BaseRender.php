<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

abstract class BaseRender {

	use ModConsumer;

	public function render() :string {
		$con = $this->getCon();

		try {
			$output = $con->getRenderer()
						  ->setTemplateEngineTwig()
						  ->setTemplate( Paths::AddExt( sprintf( '/integration/mainwp/%s', $this->getTemplateSlug() ), 'twig' ) )
						  ->setRenderVars( $this->getData() )
						  ->render();
		}
		catch ( \Exception $e ) {
			$output = $e->getMessage();
		}
		return $output;
	}

	protected function getData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( $this->getBaseData(), $this->getPageSpecificData() );
	}

	protected function getBaseData() :array {
		return [
			'content' => [
			],
			'vars'    => [
			]
		];
	}

	protected function getPageSpecificData() :array {
		return [];
	}

	abstract protected function getTemplateSlug() :string;
}