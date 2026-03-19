<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

abstract class ActionsQueueDrillDownRenderBase extends BaseRender {

	use BuildsActionsQueueLandingData;

	protected function exec() {
		$response = $this->response();
		$payload = $response->payload();
		$payload[ 'render_template' ] = $this->getRenderTemplate();
		$payload[ 'render_data' ] = $this->buildRenderData();
		$payload[ 'render_output' ] = $this->buildRenderOutput( $payload[ 'render_data' ] );
		$payload[ 'html' ] = $payload[ 'render_output' ];
		$payload[ 'header' ] = $payload[ 'render_data' ][ 'header' ];
		if ( isset( $payload[ 'render_data' ][ 'bucket_selection' ] ) ) {
			$payload[ 'bucket_selection' ] = $payload[ 'render_data' ][ 'bucket_selection' ];
		}
		if ( isset( $payload[ 'render_data' ][ 'group_selection' ] ) ) {
			$payload[ 'group_selection' ] = $payload[ 'render_data' ][ 'group_selection' ];
		}
		if ( isset( $payload[ 'render_data' ][ 'selected_group' ] ) ) {
			$payload[ 'selected_group' ] = $payload[ 'render_data' ][ 'selected_group' ];
		}
		if ( isset( $payload[ 'render_data' ][ 'landing_refresh' ] ) ) {
			$payload[ 'landing_refresh' ] = $payload[ 'render_data' ][ 'landing_refresh' ];
		}

		$response
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
}
