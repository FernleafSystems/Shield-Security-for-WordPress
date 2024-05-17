<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageSecurityAdminRestricted;

class AjaxResponseAdapter extends BaseAdapter {

	public function adapt( ActionResponse $response ) {
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
				\is_array( $response->action_response_data ) ? $response->action_response_data : []
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
				'html' => $response->action_response_data[ 'render_output' ]
			];
		}

		$response->action_response_data = $responseData;
	}
}