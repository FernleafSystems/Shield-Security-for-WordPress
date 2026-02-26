<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

/**
 * Lightweight transport wrapper to separate action execution from channel formatting.
 */
class RoutedResponse {

	private ActionResponse $actionResponse;

	private array $transportPayload;

	private int $statusCode;

	public function __construct( ActionResponse $actionResponse, array $transportPayload = [], int $statusCode = 200 ) {
		$this->actionResponse = $actionResponse;
		$this->transportPayload = $transportPayload;
		$this->statusCode = $statusCode;
	}

	public function actionResponse() :ActionResponse {
		return $this->actionResponse;
	}

	public function payload() :array {
		return $this->transportPayload;
	}

	public function statusCode() :int {
		return $this->statusCode;
	}
}
