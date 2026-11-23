<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

/**
 * What an action produces. The payload is the whole response: every adapter builds its transport from payload()
 * alone, so anything an action wants to send goes through setPayload()/mergePayload() and the helpers below.
 *
 * @property string $action_slug
 * @property array  $action_data
 * @property array  $action_response_data the payload's storage; use payload()/setPayload()
 */
class ActionResponse extends Response {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'action_data':
			case 'action_response_data':
				$value = \is_array( $value ) ? $value : [];
				break;

			default:
				break;
		}
		return $value;
	}

	public function setPayload( array $payload = [] ) :self {
		$this->action_response_data = $payload;
		return $this;
	}

	public function mergePayload( array $payload ) :self {
		$this->action_response_data = \array_merge( $this->action_response_data, $payload );
		return $this;
	}

	public function setPayloadSuccess( bool $success ) :self {
		return $this->mergePayload( [
			'success' => $success,
		] );
	}

	public function setPayloadRedirectNextStep( string $url ) :self {
		return $this->mergePayload( [
			'next_step' => [
				'type' => 'redirect',
				'url'  => $url,
			],
		] );
	}

	public function payload() :array {
		return $this->action_response_data;
	}
}
