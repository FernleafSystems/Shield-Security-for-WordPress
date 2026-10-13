<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\SiteLoopback;

class WpLoopbackProbe implements LoopbackProbe {

	private VerifyRequest $verifyRequest;

	private SiteLoopback $loopback;

	private string $verifyUrl;

	private string $homeUrl;

	private string $stubUrl;

	public function __construct( VerifyRequest $verifyRequest, SiteLoopback $loopback, string $verifyUrl, string $homeUrl, string $stubUrl ) {
		$this->verifyRequest = $verifyRequest;
		$this->loopback = $loopback;
		$this->verifyUrl = $verifyUrl;
		$this->homeUrl = $homeUrl;
		$this->stubUrl = $stubUrl;
	}

	public function verify() :ProbeResult {
		return $this->verifyThrough( $this->verifyUrl );
	}

	public function home() :ProbeResult {
		return $this->verifyThrough( $this->homeUrl );
	}

	public function prepare() :void {
		$this->loopback->prepareForChecks();
	}

	public function smoke() :ProbeResult {
		$response = $this->loopback->get( $this->stubUrl );
		if ( \is_wp_error( $response ) ) {
			return ProbeResult::unreachable();
		}
		$status = (int)\wp_remote_retrieve_response_code( $response );
		return new ProbeResult(
			true,
			$status,
			null,
			$status === 200 && \trim( (string)\wp_remote_retrieve_body( $response ) ) === StubBuilder::SMOKE_MARKER
		);
	}

	private function verifyThrough( string $url ) :ProbeResult {
		$response = $this->loopback->get( \add_query_arg( VerifyRequest::QUERY_KEY, $this->verifyRequest->issueToken(), $url ) );
		if ( \is_wp_error( $response ) ) {
			return ProbeResult::unreachable();
		}
		$status = (int)\wp_remote_retrieve_response_code( $response );
		return new ProbeResult(
			true,
			$status,
			$status === 200 ? VerifyRequest::parseLoaded( (string)\wp_remote_retrieve_body( $response ) ) : null
		);
	}
}
