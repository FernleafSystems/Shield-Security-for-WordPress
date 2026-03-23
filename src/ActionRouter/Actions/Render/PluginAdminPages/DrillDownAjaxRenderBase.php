<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

abstract class DrillDownAjaxRenderBase extends BaseRender {

	protected function exec() {
		$renderData = $this->buildRenderData();
		$payload = $this->response()->payload();
		$payload[ 'html' ] = $this->buildRenderOutput( $renderData );
		$payload[ 'header' ] = \is_array( $renderData[ 'header' ] ?? null )
			? $renderData[ 'header' ]
			: [];

		foreach ( $this->promotedRenderDataKeys() as $key ) {
			if ( \array_key_exists( $key, $renderData ) ) {
				$payload[ $key ] = $renderData[ $key ];
			}
		}

		$this->response()
			->setPayload( $payload )
			->setPayloadSuccess( true );
	}

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		return [
			0 => $this->action_data,
			10 => $this->getRenderData(),
		];
	}

	/**
	 * @return list<string>
	 */
	abstract protected function promotedRenderDataKeys() :array;
}
