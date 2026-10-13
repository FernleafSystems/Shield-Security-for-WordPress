<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Diagnostics;

class HttpOutcome {

	private string $body;
	private bool $hasResponse;
	private ?int $status;
	private ?string $errorCategory;

	public function __construct( string $body, bool $hasResponse, ?int $status = null, ?string $errorCategory = null ) {
		$this->body = $body;
		$this->hasResponse = $hasResponse;
		$this->status = $status;
		$this->errorCategory = $errorCategory;
	}

	public static function fromRequest( string $body, object $request ) :self {
		$response = $request->lastResponse ?? null;
		$status = $response ? (int)$response->getCode() : null;
		$error = $request->lastError ?? null;
		$errorCode = \is_object( $error ) && \is_callable( [ $error, 'get_error_code' ] )
			? (string)$error->get_error_code()
			: '';
		$errorCategory = \in_array( $errorCode, [ 'http_request_timeout', 'timeout' ], true )
			? SyncObservation::ERROR_TIMEOUT
			: null;

		return new self( $body, $response !== null, $status && $status > 0 ? $status : null, $errorCategory );
	}

	public function body() :string {
		return $this->body;
	}

	public function hasResponse() :bool {
		return $this->hasResponse;
	}

	public function status() :?int {
		return $this->status;
	}

	public function observationFields() :array {
		$fields = [];
		if ( $this->status !== null ) {
			$fields[ 'http_status' ] = $this->status;
		}
		if ( $this->errorCategory !== null ) {
			$fields[ 'error_category' ] = $this->errorCategory;
		}
		return $fields;
	}
}
