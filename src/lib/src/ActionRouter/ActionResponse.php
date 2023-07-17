<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

/**
 * @property string $action_slug
 * @property array  $action_data
 * @property array  $action_response_data
 *
 * @property array  $next_step
 *
 * AJAX Actions:
 * @property array  $ajax_data
 */
class ActionResponse extends Response {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'action_data':
			case 'action_response_data':
			case 'ajax_data':
			case 'render_data':
			case 'next_step':
				$value = \is_array( $value ) ? $value : [];
				break;

			default:
				break;
		}
		return $value;
	}
}