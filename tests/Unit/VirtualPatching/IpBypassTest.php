<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\IpBypass;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class IpBypassTest extends TestCase {

	#[DataProvider( 'providerBypass' )]
	public function testIsBypassed( ?string $source, array $server, array $ips, bool $expected ) :void {
		$ruleset = [ 'bypass' => [ 'ip_source' => $source, 'ips' => $ips ] ];
		$this->assertSame( $expected, IpBypass::isBypassed( $ruleset, $server ) );
	}

	public static function providerBypass() :array {
		$remote = [ 'REMOTE_ADDR' => '198.51.100.7' ];
		return [
			'exact IPv4'                => [ 'REMOTE_ADDR', $remote, [ '198.51.100.7' ], true ],
			'IPv4 range'                => [ 'REMOTE_ADDR', $remote, [ '198.51.100.0/24' ], true ],
			'IPv4 range, partial byte'  => [ 'REMOTE_ADDR', $remote, [ '198.51.100.0/29' ], true ],
			'outside IPv4 range'        => [ 'REMOTE_ADDR', $remote, [ '198.51.100.8/29' ], false ],
			'IPv6 range'                => [ 'REMOTE_ADDR', [ 'REMOTE_ADDR' => '2001:db8::5' ], [ '2001:db8::/32' ], true ],
			'IPv6 against IPv4 range'   => [ 'REMOTE_ADDR', [ 'REMOTE_ADDR' => '2001:db8::5' ], [ '198.51.100.0/24' ], false ],
			'first IP in a header list' => [ 'HTTP_X_FORWARDED_FOR', [ 'HTTP_X_FORWARDED_FOR' => 'junk, 198.51.100.7, 10.0.0.1' ], [ '198.51.100.7' ], true ],
			'unknown source'            => [ null, $remote, [ '198.51.100.7' ], false ],
			'source missing'            => [ 'HTTP_CF_CONNECTING_IP', $remote, [ '198.51.100.7' ], false ],
			'empty list'                => [ 'REMOTE_ADDR', $remote, [], false ],
			'invalid range'             => [ 'REMOTE_ADDR', $remote, [ '198.51.100.0/33', 'nonsense' ], false ],
		];
	}

	public function testNoBypassSettings() :void {
		$this->assertFalse( IpBypass::isBypassed( [], [ 'REMOTE_ADDR' => '198.51.100.7' ] ) );
	}
}
