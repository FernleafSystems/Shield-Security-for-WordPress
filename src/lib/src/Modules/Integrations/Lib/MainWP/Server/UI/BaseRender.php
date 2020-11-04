<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\UI;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class BaseRender {

	use ModConsumer;

	public function render() :string {
		$con = $this->getCon();

		try {
			$output = $con->getRenderer()
						  ->setTemplateEngineTwig()
						  ->setTemplate( sprintf( '/integration/mainwp/%s.twig', $this->getTemplateSlug() ) )
						  ->setRenderVars( $this->getData() )
						  ->render();
		}
		catch ( \Exception $e ) {
			$output = $e->getMessage();
		}
		return $output;
	}

	protected function getData() :array {
		return [
			'content' => [
			],
			'vars'    => [
			]
		];
	}

	abstract protected function getTemplateSlug() :string;
}