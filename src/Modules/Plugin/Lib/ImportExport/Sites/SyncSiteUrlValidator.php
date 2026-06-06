<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

use FernleafSystems\Wordpress\Services\Services;

class SyncSiteUrlValidator {

	/**
	 * @var callable|null
	 */
	private $hostResolver;

	public function __construct( ?callable $hostResolver = null ) {
		$this->hostResolver = $hostResolver;
	}

	public function validate( string $url, bool $rejectSelf = true ) :string {
		$url = $this->canonicalize( $url );
		if ( $url === '' ) {
			throw new \InvalidArgumentException( __( 'Please provide valid HTTP or HTTPS site URLs only.', 'wp-simple-firewall' ) );
		}

		$parts = $this->parse( $url );
		$scheme = \strtolower( (string)( $parts[ 'scheme' ] ?? '' ) );
		$host = $this->normaliseHost( (string)( $parts[ 'host' ] ?? '' ) );

		if ( !\in_array( $scheme, [ 'http', 'https' ], true ) || $host === '' ) {
			throw new \InvalidArgumentException( __( 'Please provide valid HTTP or HTTPS site URLs only.', 'wp-simple-firewall' ) );
		}
		if ( !empty( $parts[ 'user' ] ) || !empty( $parts[ 'pass' ] ) ) {
			throw new \InvalidArgumentException( __( 'Site URLs cannot include credentials.', 'wp-simple-firewall' ) );
		}
		if ( !$this->isValidHostShape( $host ) ) {
			throw new \InvalidArgumentException( __( 'Please provide public site URLs only.', 'wp-simple-firewall' ) );
		}
		if ( $this->isUnsafeLiteralIp( $host ) ) {
			throw new \InvalidArgumentException( __( 'Private, loopback, and reserved IP addresses are not allowed.', 'wp-simple-firewall' ) );
		}
		if ( $rejectSelf && $this->isSelfUrl( $url ) ) {
			throw new \InvalidArgumentException( __( 'This site cannot invite itself.', 'wp-simple-firewall' ) );
		}

		return $url;
	}

	public function validatePublicOutbound( string $url, bool $rejectSelf = true ) :string {
		$url = $this->validate( $url, $rejectSelf );

		if ( \wp_http_validate_url( $url ) === false ) {
			throw new \InvalidArgumentException( __( 'Please provide public site URLs only.', 'wp-simple-firewall' ) );
		}

		$parts = $this->parse( $url );
		$host = $this->normaliseHost( (string)( $parts[ 'host' ] ?? '' ) );
		$ips = $this->resolveHostIps( $host );
		if ( empty( $ips ) ) {
			throw new \InvalidArgumentException( __( 'Site URLs must resolve to public IP addresses.', 'wp-simple-firewall' ) );
		}

		foreach ( $ips as $ip ) {
			if ( $this->isUnsafeLiteralIp( $ip ) ) {
				throw new \InvalidArgumentException( __( 'Site URLs must resolve to public IP addresses only.', 'wp-simple-firewall' ) );
			}
		}

		return $url;
	}

	public function canonicalize( string $url ) :string {
		$validated = Services::Data()->validateSimpleHttpUrl( \trim( $url ) );
		return $validated === false ? '' : (string)$validated;
	}

	private function parse( string $url ) :array {
		$parts = \wp_parse_url( $url );
		return \is_array( $parts ) ? $parts : [];
	}

	private function normaliseHost( string $host ) :string {
		return \strtolower( \trim( $host, " \t\n\r\0\x0B[]" ) );
	}

	private function isValidHostShape( string $host ) :bool {
		if ( $host === ''
			 || \str_contains( $host, ' ' )
			 || \str_contains( $host, '/' )
			 || \str_contains( $host, '\\' )
			 || \str_starts_with( $host, '.' )
			 || \str_ends_with( $host, '.' )
			 || \preg_match( '#[\x00-\x1f\x7f]#', $host ) ) {
			return false;
		}

		if ( \filter_var( $host, \FILTER_VALIDATE_IP ) !== false ) {
			return true;
		}

		if ( !\str_contains( $host, '.' ) ) {
			return false;
		}

		foreach ( [ 'localhost', '.localhost', '.local', '.test', '.invalid' ] as $suffix ) {
			if ( $host === \ltrim( $suffix, '.' ) || \str_ends_with( $host, $suffix ) ) {
				return false;
			}
		}

		foreach ( \explode( '.', $host ) as $label ) {
			if ( $label === ''
				 || \strlen( $label ) > 63
				 || \preg_match( '#^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$#i', $label ) !== 1 ) {
				return false;
			}
		}

		return true;
	}

	private function isUnsafeLiteralIp( string $host ) :bool {
		$ip = \filter_var( $host, \FILTER_VALIDATE_IP );
		if ( $ip === false ) {
			return false;
		}

		return \filter_var(
			$host,
			\FILTER_VALIDATE_IP,
			\FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE
		) === false || $this->isKnownReservedLiteralIp( $host );
	}

	private function resolveHostIps( string $host ) :array {
		if ( \filter_var( $host, \FILTER_VALIDATE_IP ) !== false ) {
			return [ $host ];
		}

		if ( \is_callable( $this->hostResolver ) ) {
			return $this->normaliseResolvedIps( (array)( $this->hostResolver )( $host ) );
		}

		$records = @\dns_get_record( $host, \DNS_A | \DNS_AAAA );
		$ips = [];
		if ( \is_array( $records ) ) {
			foreach ( $records as $record ) {
				foreach ( [ 'ip', 'ipv6' ] as $key ) {
					$ip = (string)( $record[ $key ] ?? '' );
					if ( $ip !== '' ) {
						$ips[] = $ip;
					}
				}
			}
		}

		if ( empty( $ips ) ) {
			$ipv4 = @\gethostbynamel( $host );
			if ( \is_array( $ipv4 ) ) {
				$ips = \array_merge( $ips, $ipv4 );
			}
		}

		return $this->normaliseResolvedIps( $ips );
	}

	private function normaliseResolvedIps( array $ips ) :array {
		return \array_values( \array_unique( \array_filter( \array_map(
			static fn( $ip ) :string => \trim( (string)$ip ),
			$ips
		), static fn( string $ip ) :bool => \filter_var( $ip, \FILTER_VALIDATE_IP ) !== false ) ) );
	}

	private function isKnownReservedLiteralIp( string $host ) :bool {
		if ( \filter_var( $host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4 ) !== false ) {
			$ip = \ip2long( $host );
			if ( $ip === false ) {
				return true;
			}
			$ip = (int)\sprintf( '%u', $ip );
			foreach ( [
				[ '0.0.0.0', 8 ],
				[ '100.64.0.0', 10 ],
				[ '127.0.0.0', 8 ],
				[ '169.254.0.0', 16 ],
				[ '192.0.0.0', 24 ],
				[ '192.0.2.0', 24 ],
				[ '198.18.0.0', 15 ],
				[ '198.51.100.0', 24 ],
				[ '203.0.113.0', 24 ],
				[ '224.0.0.0', 4 ],
				[ '240.0.0.0', 4 ],
			] as [ $range, $maskBits ] ) {
				$rangeStart = (int)\sprintf( '%u', \ip2long( $range ) );
				$mask = ( -1 << ( 32 - $maskBits ) ) & 0xFFFFFFFF;
				if ( ( $ip & $mask ) === ( $rangeStart & $mask ) ) {
					return true;
				}
			}
			return false;
		}

		$host = \strtolower( $host );
		return $host === '::1'
			   || \preg_match( '#^(fc|fd|fe8|fe9|fea|feb)[0-9a-f]*:#', $host ) === 1
			   || \str_starts_with( $host, '2001:db8:' );
	}

	private function isSelfUrl( string $url ) :bool {
		$home = $this->canonicalize( Services::WpGeneral()->getHomeUrl() );
		if ( $home === '' ) {
			return false;
		}

		$urlParts = $this->parse( $url );
		$homeParts = $this->parse( $home );
		return $this->normaliseHost( (string)( $urlParts[ 'host' ] ?? '' ) )
			   === $this->normaliseHost( (string)( $homeParts[ 'host' ] ?? '' ) );
	}
}
