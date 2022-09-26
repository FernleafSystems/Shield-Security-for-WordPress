<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $template
 */
abstract class BaseRender extends BaseAction {

	const TEMPLATE = 'insights';

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {

			case 'template':
				if ( empty( $value ) ) {
					$value = static::TEMPLATE;
				}
				break;

			default:
				break;
		}

		return $value;
	}

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		return $this->render()->response();
	}

	/**
	 * @return $this
	 * @throws ActionException
	 */
	protected function render() {
		$response = $this->response();
		$respData = $response->action_response_data;
		$respData[ 'render_template' ] = $this->template;
		$respData[ 'render_data' ] = $this->buildRenderData();
		$respData[ 'render_output' ] = $this->getMod()
											->getRenderer()
											->setMod( $this->primary_mod )
											->setTemplate( $respData[ 'render_template' ] )
											->setRenderData( $respData[ 'render_data' ] )
											->render();
		$response->action_response_data = $respData;
		return $this;
	}

	/**
	 * @throws ActionException
	 */
	protected function buildRenderData() :array {
		return Services::DataManipulation()->mergeArraysRecursive(
			$this->getMod()->getUIHandler()->getBaseDisplayData(),
			$this->getRenderData()
		);
	}

	/**
	 * @throws ActionException
	 */
	protected function getRenderData() :array {
		return [];
	}
}