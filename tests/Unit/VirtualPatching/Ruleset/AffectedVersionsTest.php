<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Ruleset;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\AffectedVersions;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class AffectedVersionsTest extends TestCase {

	private const RANGES = [
		[ 'firstAffectedVersion' => '1.2', 'lastAffectedVersion' => '1.4.2', 'firstKnownFixedVersion' => '1.4.3' ],
		[ 'firstAffectedVersion' => '2.0.0', 'lastAffectedVersion' => '2.1' ],
	];

	#[DataProvider( 'providerVersions' )]
	public function testIsAffected( string $installed, bool $expected ) :void {
		$this->assertSame( $expected, ( new AffectedVersions() )->isAffected( $installed, self::RANGES ) );
	}

	public static function providerVersions() :array {
		return [
			'below the first range'           => [ '1.1.9', false ],
			'first affected version'          => [ '1.2', true ],
			'first affected with zero suffix' => [ '1.2.0', true ],
			'inside the first range'          => [ '1.3.7', true ],
			'last affected version'           => [ '1.4.2', true ],
			'last affected with zero suffix'  => [ '1.4.2.0', true ],
			'first fixed version'             => [ '1.4.3', false ],
			'between ranges'                  => [ '1.9', false ],
			'second range start'              => [ '2', true ],
			'second range end'                => [ '2.1.0', true ],
			'after the second range'          => [ '2.1.1', false ],
			'blank version'                   => [ '', false ],
		];
	}

	public function testFixedVersionWinsInsideARange() :void {
		$ranges = [ [ 'firstAffectedVersion' => '1.0', 'lastAffectedVersion' => '1.9', 'firstKnownFixedVersion' => '1.5' ] ];
		$versions = new AffectedVersions();
		$this->assertTrue( $versions->isAffected( '1.4.9', $ranges ) );
		$this->assertFalse( $versions->isAffected( '1.5', $ranges ) );
	}
}
