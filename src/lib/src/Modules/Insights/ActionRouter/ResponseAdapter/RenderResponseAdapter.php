<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ResponseAdapter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionResponse;

class RenderResponseAdapter extends BaseAdapter {

	/**
	 * @inheritDoc
	 */
	public function adapt( ActionResponse $response ) {
		$response->render_data = [
			'template' => $response->action_response_data[ 'render_template' ],
			'data'     => $response->action_response_data[ 'render_data' ],
			'output'   => $response->action_response_data[ 'render_output' ],
		];
		unset( $response->action_response_data );
	}
}