<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

abstract class BaseTemplateRenderer {

	use PluginControllerConsumer;

	/**
	 * @var array
	 */
	private $aux = [];

	public function render() :string {
		try {
			$output = $this->getCon()
						   ->getRenderer()
						   ->setTemplateEngineTwig()
						   ->setTemplate( $this->getTemplate() )
						   ->setRenderVars( $this->getData() )
						   ->render();
		}
		catch ( \Exception $e ) {
			$output = $e->getMessage();
		}
		return $output;
	}

	public function getAuxData() :array {
		return $this->aux;
	}

	protected function getData() :array {
		return [];
	}

	protected function getTemplate() :string {
		return rtrim( $this->getTemplateBaseDir(), '/' ).'/'.ltrim( $this->getTemplateStub(), '/' );
	}

	abstract protected function getTemplateBaseDir() :string;

	abstract protected function getTemplateStub() :string;

	public function setAuxData( array $aux ) {
		$this->aux = $aux;
		return $this;
	}
}