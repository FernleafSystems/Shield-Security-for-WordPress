<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\SettingChange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SettingChangeTest extends TestCase {

	public function testStoredChangeIsReadBack() :void {
		$change = SettingChange::fromOption( ( new SettingChange( 1700, false ) )->toOption() );
		$this->assertSame( 1700, $change->at );
		$this->assertFalse( $change->enabled );
	}

	public static function unusableOptionProvider() :array {
		return [
			'missing'         => [ false ],
			'older timestamp' => [ 1700 ],
			'damaged value'   => [ [ 'at' => 1700, 'enabled' => 'N' ] ],
		];
	}

	#[DataProvider( 'unusableOptionProvider' )]
	public function testUnusableOptionNeverOverridesTheSetting( mixed $stored ) :void {
		$change = SettingChange::fromOption( $stored );
		$this->assertNull( $change->enabled );
		$this->assertFalse( $change->revertedBy( true ) );
	}

	public function testOnlyASettingSwitchedBackOnIsTreatedAsReverted() :void {
		$off = new SettingChange( 1700, false );
		$this->assertTrue( $off->revertedBy( true ), 'An older copy of the options saved the setting back on.' );
		$this->assertFalse( $off->revertedBy( false ) );
		$this->assertFalse( ( new SettingChange( 1700, true ) )->revertedBy( false ), 'Off is the safe direction, and is how a lapsed licence looks.' );
	}

	public function testChangeMadeDuringARequestIsDetected() :void {
		$change = new SettingChange( 1700, true );
		$this->assertTrue( $change->madeSince( 1700 ) );
		$this->assertFalse( $change->madeSince( 1701 ) );
	}
}
