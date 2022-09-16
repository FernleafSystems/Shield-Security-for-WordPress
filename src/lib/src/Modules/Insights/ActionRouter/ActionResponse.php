<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

/**
 * @property string                                               $action_slug
 * @property array                                                $action_data
 * @property array                                                $action_response_data
 *
 * AJAX Actions:
 * @property array                                                $ajax_data
 *
 * Render Actions:
 * @property array{template: string, data: array, output: string} $render_data
 */
class ActionResponse extends Response {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'action_response_data':
			case 'ajax_data':
			case 'render_data':
				$value = is_array( $value ) ? $value : [];
				break;

			default:
				break;
		}
		return $value;
	}
}