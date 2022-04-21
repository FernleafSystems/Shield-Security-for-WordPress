<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;
use FernleafSystems\Wordpress\Services\Utilities\Render;

class BaseTemplateRenderer {

	use PluginControllerConsumer;

	/**
	 * @var array
	 */
	private $aux = [];

	/**
	 * @var array
	 */
	private $renderData = [];

	/**
	 * @var string
	 */
	private $renderTemplate = '';

	public function render() :string {
		try {
			$renderer = $this->getRenderer();

			$template = $this->getTemplate();
			if ( empty( $template ) ) {
				throw new \Exception( 'No template provided for render' );
			}

			$ext = Paths::Ext( $template );
			if ( empty( $ext ) || strtolower( $ext ) === 'twig' ) {
				$renderer->setTemplateEngineTwig();
			}
			else {
				$renderer->setTemplateEnginePhp();
			}

			$output = $renderer->setTemplate( $template )
							   ->setRenderVars( $this->getRenderData() )
							   ->render();
		}
		catch ( \Exception $e ) {
			$output = $e->getMessage();
		}
		return $output;
	}

	protected function getRenderer() :Render {
		return $this->getCon()->getRenderer();
	}

	public function getAuxData() :array {
		return $this->aux;
	}

	protected function getRenderData() :array {
		return $this->renderData;
	}

	protected function getTemplate() :string {
		return empty( $this->renderTemplate ) ?
			rtrim( $this->getTemplateBaseDir(), '/' ).'/'.ltrim( $this->getTemplateStub(), '/' )
			: $this->renderTemplate;
	}

	protected function getTemplateBaseDir() :string {
		return '';
	}

	protected function getTemplateStub() :string {
		return '';
	}

	public function setAuxData( array $aux ) {
		$this->aux = $aux;
		return $this;
	}

	public function setRenderData( array $data ) {
		$this->renderData = $data;
		return $this;
	}

	public function setTemplate( string $template ) {
		$this->renderTemplate = $template;
		return $this;
	}
}