<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\UI;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

abstract class BaseRender extends BaseAction {

	public const PRIMARY_MOD = 'insights';
	public const TEMPLATE = '';

	protected function exec() {
		return $this->render()->response();
	}

	/**
	 * @throws ActionException
	 */
	private function render() :self {
		$response = $this->response();
		$respData = $response->action_response_data;
		$respData[ 'render_template' ] = $this->getRenderTemplate();
		$respData[ 'render_data' ] = $this->buildRenderData();
		$respData[ 'render_output' ] = $this->buildRenderOutput( $respData[ 'render_data' ] );

		$respData[ 'html' ] = $respData[ 'render_output' ]; // TODO: This is a hack to get the data into the AJAX response

		$response->success = $respData[ 'success' ] ?? true;
		$response->action_response_data = $respData;
		return $this;
	}

	/**
	 * @throws ActionException
	 */
	protected function buildRenderOutput( array $renderData = [] ) :string {
		$con = $this->getCon();
		$template = $this->getRenderTemplate();
		if ( empty( $template ) ) {
			throw new ActionException( 'No template provided for render' );
		}

		try {
			$renderer = $con->getRenderer();

			$ext = Paths::Ext( $template );
			if ( empty( $ext ) || strtolower( $ext ) === 'twig' ) {
				$renderer->setTemplateEngineTwig();
			}
			else {
				$renderer->setTemplateEnginePhp();
			}

			$output = $renderer->setTemplate( $template )
							   ->setRenderVars( $renderData )
							   ->render();
		}
		catch ( \Exception $e ) {
			$output = sprintf( 'Exception during render for %s: "%s"', static::SLUG, $e->getMessage() );
		}
		return $output;
	}

	/**
	 * @throws ActionException
	 */
	protected function buildRenderData() :array {
		return call_user_func_array(
			[ Services::DataManipulation(), 'mergeArraysRecursive' ],
			$this->getAllRenderDataArrays()
		);
	}

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		/** @var UI $UI */
		$UI = $this->getMod()->getUIHandler();
		return [
			0  => $UI->getCommonDisplayData(),
			10 => $this->action_data,
			50 => $this->getRenderData(),
		];
	}

	/**
	 * @throws ActionException
	 */
	protected function getRenderData() :array {
		return [];
	}

	/**
	 * @throws ActionException
	 */
	protected function getRenderTemplate() :string {
		$t = static::TEMPLATE;
		if ( empty( $t ) ) {
			$t = $this->action_data[ 'render_action_template' ];
		}
		if ( empty( $t ) ) {
			throw new ActionException( sprintf( 'Render action %s has no render template provided', static::class ) );
		}
		return $t;
	}
}