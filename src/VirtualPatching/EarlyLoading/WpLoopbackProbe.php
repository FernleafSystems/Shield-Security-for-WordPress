<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

class WpLoopbackProbe implements LoopbackProbe {

	private VerifyRequest $verifyRequest;

	private string $verifyUrl;

	private string $homeUrl;

	private string $stubUrl;

	public function __construct( VerifyRequest $verifyRequest, string $verifyUrl, string $homeUrl, string $stubUrl ) {
		$this->verifyRequest = $verifyRequest;
		$this->verifyUrl = $verifyUrl;
		$this->homeUrl = $homeUrl;
		$this->stubUrl = $stubUrl;
	}

	public function verify() :ProbeResult {
		$url = \add_query_arg( VerifyRequest::QUERY_KEY, $this->verifyRequest->issueToken(), $this->verifyUrl );
		return $this->request( $url, fn( string $body ) => new ProbeResult( true, 0, VerifyRequest::parseLoaded( $body ) ) );
	}

	public function home() :ProbeResult {
		return $this->request( \add_query_arg( 'shield_early_probe', \bin2hex( \random_bytes( 4 ) ), $this->homeUrl ) );
	}

	public function smoke() :ProbeResult {
		return $this->request(
			$this->stubUrl,
			fn( string $body ) => new ProbeResult( true, 0, null, \trim( $body ) === StubBuilder::SMOKE_MARKER )
		);
	}

	/**
	 * Loopback requests follow WordPress's own Site Health loopback settings.
	 * @param ?callable(string):ProbeResult $readBody
	 */
	private function request( string $url, ?callable $readBody = null ) :ProbeResult {
		$response = \wp_remote_get( $url, [
			'timeout'     => 10,
			'redirection' => 0,
			'cookies'     => [],
			'headers'     => [ 'Cache-Control' => 'no-cache' ],
			'sslverify'   => \apply_filters( 'https_local_ssl_verify', false ),
		] );
		if ( \is_wp_error( $response ) ) {
			return ProbeResult::unreachable();
		}

		$status = (int)\wp_remote_retrieve_response_code( $response );
		$fromBody = $readBody === null ? null : $readBody( (string)\wp_remote_retrieve_body( $response ) );
		return new ProbeResult(
			true,
			$status,
			$status === 200 ? $fromBody->loaded ?? null : null,
			$status === 200 && ( $fromBody->smokeMarkerSeen ?? false )
		);
	}
}
