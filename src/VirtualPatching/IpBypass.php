<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching;

/**
 * Whether the request comes from an IP address on Shield's bypass list, using the ruleset's exported settings:
 * 'bypass' => [ 'ip_source' => a $_SERVER key or null, 'ips' => exact IPs and CIDR ranges ].
 *
 * Loaded without Composer or WordPress, so it must stay self-contained. When the IP source is unknown, nothing
 * is bypassed: protection is preferred over an exemption that cannot be verified.
 */
final class IpBypass {

	public static function isBypassed( array $ruleset, array $server ) :bool {
		$bypass = $ruleset[ 'bypass' ] ?? null;
		if ( !\is_array( $bypass ) || !\is_array( $bypass[ 'ips' ] ?? null ) || empty( $bypass[ 'ips' ] ) ) {
			return false;
		}
		$ip = self::requestIp( $bypass[ 'ip_source' ] ?? null, $server );
		if ( $ip === null ) {
			return false;
		}
		foreach ( $bypass[ 'ips' ] as $range ) {
			if ( \is_string( $range ) && self::inRange( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The first valid IP address in the given $_SERVER entry, which may hold a comma-separated list.
	 */
	public static function requestIp( $source, array $server ) :?string {
		$value = \is_string( $source ) && $source !== '' ? ( $server[ $source ] ?? null ) : null;
		if ( !\is_string( $value ) ) {
			return null;
		}
		foreach ( \explode( ',', $value ) as $candidate ) {
			$candidate = \trim( $candidate );
			if ( \filter_var( $candidate, \FILTER_VALIDATE_IP ) !== false ) {
				return $candidate;
			}
		}
		return null;
	}

	private static function inRange( string $ip, string $range ) :bool {
		$parts = \explode( '/', \trim( $range ), 2 );
		$ipBinary = @\inet_pton( $ip );
		$rangeBinary = @\inet_pton( $parts[ 0 ] );
		if ( $ipBinary === false || $rangeBinary === false || \strlen( $ipBinary ) !== \strlen( $rangeBinary ) ) {
			return false;
		}
		$maxBits = \strlen( $ipBinary )*8;
		$bits = isset( $parts[ 1 ] ) ? $parts[ 1 ] : (string)$maxBits;
		if ( !\ctype_digit( $bits ) || (int)$bits > $maxBits ) {
			return false;
		}
		$bits = (int)$bits;
		$fullBytes = \intdiv( $bits, 8 );
		if ( \substr( $ipBinary, 0, $fullBytes ) !== \substr( $rangeBinary, 0, $fullBytes ) ) {
			return false;
		}
		$remainder = $bits%8;
		if ( $remainder === 0 ) {
			return true;
		}
		$mask = ( 0xFF << ( 8 - $remainder ) ) & 0xFF;
		return ( \ord( $ipBinary[ $fullBytes ] ) & $mask ) === ( \ord( $rangeBinary[ $fullBytes ] ) & $mask );
	}
}
