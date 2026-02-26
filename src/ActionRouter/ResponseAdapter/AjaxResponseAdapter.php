<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\RoutedResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ResponseEnvelopeNormalizer;

class AjaxResponseAdapter extends BaseAdapter {

	public function adapt( ActionResponse $response ) :RoutedResponse {
		$payload = $response->payload();
		$responseData = ResponseEnvelopeNormalizer::forAjaxAdapter(
			\array_merge( $response->getRawData(), $payload ),
			(string)( $response->message ?? '' ),
			(string)( $response->error ?? '' )
		);
		$responseData = \array_diff_key(
			$responseData,
			\array_flip( [ 'action_response_data', 'action_data' ] )
		);
		$responseData[ 'success' ] = (bool)( $payload[ 'success' ] ?? false );

		/**
		 * Special case where the AJAX triggers a render of the security restricted page.
		 * This approach is a bit of a hack. Is there a better way to standardise moving render data to ajax?
		 */
		if ( $response->action_slug === PageSecurityAdminRestricted::SLUG ) {
			$responseData = [
				'html' => $payload[ 'render_output' ] ?? ''
			];
		}

		$response->setPayload( $responseData );
		return new RoutedResponse( $response, $response->payload() );
	}
}
