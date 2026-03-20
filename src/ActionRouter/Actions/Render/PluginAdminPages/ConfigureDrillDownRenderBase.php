<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

abstract class ConfigureDrillDownRenderBase extends BaseRender {

	use BuildsConfigureLandingData;

	protected function exec() {
		$response = $this->response();
		$payload = $response->payload();
		$payload[ 'render_template' ] = $this->getRenderTemplate();
		$payload[ 'render_data' ] = $this->buildRenderData();
		$payload[ 'render_output' ] = $this->buildRenderOutput( $payload[ 'render_data' ] );
		$payload[ 'html' ] = $payload[ 'render_output' ];
		$payload[ 'header' ] = $payload[ 'render_data' ][ 'header' ];
		if ( isset( $payload[ 'render_data' ][ 'zone_selection' ] ) ) {
			$payload[ 'zone_selection' ] = $payload[ 'render_data' ][ 'zone_selection' ];
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
	protected function getSelectedConfigureZoneKey() :string {
		$zoneKey = sanitize_key( (string)( $this->action_data[ 'zone' ] ?? '' ) );
		if ( $zoneKey === '' || !isset( $this->getConfigureLandingTileLookup()[ $zoneKey ] ) ) {
			throw new ActionException( 'Invalid Configure zone key.' );
		}
		return $zoneKey;
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
