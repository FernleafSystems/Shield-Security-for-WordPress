<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalSiteProbe;

class RecordingLocalSiteProbe extends LocalSiteProbe {

	/** @var bool[] */
	private array $httpReadyResponses;

	/** @var bool[] */
	private array $waitResponses;

	/** @var bool[] */
	private array $portOpenResponses;

	/**
	 * @param bool[] $httpReadyResponses
	 * @param bool[] $waitResponses
	 * @param bool[] $portOpenResponses
	 */
	public function __construct(
		array $httpReadyResponses = [ false ],
		array $waitResponses = [ true ],
		array $portOpenResponses = [ false ]
	) {
		$this->httpReadyResponses = $httpReadyResponses;
		$this->waitResponses = $waitResponses;
		$this->portOpenResponses = $portOpenResponses;
	}

	public function isHttpReady( string $url ) :bool {
		return (bool)( \array_shift( $this->httpReadyResponses ) ?? false );
	}

	public function waitForHttpReady( string $url, int $timeoutSeconds = 60 ) :bool {
		return (bool)( \array_shift( $this->waitResponses ) ?? true );
	}

	public function isTcpPortOpen( string $host, int $port ) :bool {
		return (bool)( \array_shift( $this->portOpenResponses ) ?? false );
	}
}
