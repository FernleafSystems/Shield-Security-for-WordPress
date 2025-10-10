<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\RoutedResponse;

class AjaxResponseAdapter extends BaseAdapter {

	public function adapt( ActionResponse $response ) :RoutedResponse {
		$responseData = \array_diff_key(
			\array_merge(
				[
					'success'    => false,
					'message'    => $response->message ?? '',
					'error'      => $response->error ?? '',
					'html'       => '-',
					'page_title' => '-',
					'page_url'   => '-',
					'show_toast' => true,
				],
				$response->getRawData(),
				$response->payload()
			),
			\array_flip( [
				'action_response_data',
			] )
		);

		/**
		 * Special case where the AJAX triggers a render of the security restricted page.
		 * This approach is a bit of a hack. Is there a better way to standardise moving render data to ajax?
		 */
		if ( $response->action_slug === PageSecurityAdminRestricted::SLUG ) {
			$responseData = [
				'html' => $response->payload()[ 'render_output' ] ?? ''
			];
		}

		$response->setPayload( $responseData );
		return new RoutedResponse( $response, $response->payload() );
	}
}