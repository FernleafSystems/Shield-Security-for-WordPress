<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

trait ResolvesIpIdentity {

	/**
	 * @var array<string, array{0:string,1:string}|false>
	 */
	private array $ipIdentityCache = [];

	protected function resolveIpIdentity( string $ip, ?string $userAgent = null ) :?array {
		$cacheKey = $this->getIpIdentityCacheKey( $ip, $userAgent );
		if ( !isset( $this->ipIdentityCache[ $cacheKey ] ) ) {
			try {
				$this->ipIdentityCache[ $cacheKey ] = $this->createIpIdentifier( $ip, $userAgent )->run();
			}
			catch ( \Exception $e ) {
				$this->ipIdentityCache[ $cacheKey ] = false;
			}
		}

		$result = $this->ipIdentityCache[ $cacheKey ];
		return $result === false ? null : $result;
	}

	protected function createIpIdentifier( string $ip, ?string $userAgent = null ) :IpID {
		$userAgent = $this->normalizeIpIdentityUserAgent( $userAgent );
		return new IpID( $ip, $userAgent === '' ? null : $userAgent );
	}

	private function getIpIdentityCacheKey( string $ip, ?string $userAgent = null ) :string {
		return \md5( $ip."\0".$this->normalizeIpIdentityUserAgent( $userAgent ) );
	}

	private function normalizeIpIdentityUserAgent( ?string $userAgent ) :string {
		return \trim( (string)$userAgent );
	}
}
