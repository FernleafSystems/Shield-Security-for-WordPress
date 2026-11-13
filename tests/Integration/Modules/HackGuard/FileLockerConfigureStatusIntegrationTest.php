<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\FileLockerState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\FileLocker;

/**
 * File Locker's Configure status reports what the last cipher check found, and never runs a new check, which
 * can mean a slow remote request, while the page renders.
 */
class FileLockerConfigureStatusIntegrationTest extends ShieldIntegrationTestCase {

	private const CIPHER_REASON = 'the last check found no encryption cipher';

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [ 'file_locker', 'filelocker_state' ] );
		$this->requireController()->opts->optSet( 'file_locker', [ 'wpconfig' ] )->store();
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_status_does_not_check_ciphers_that_were_never_checked() :void {
		$this->storeCipherState( 0, '' );

		$this->assertStringNotContainsString( self::CIPHER_REASON, $this->explanations() );
		$this->assertSame( 0, $this->requireController()->comps->file_locker->getState()[ 'cipher_last_checked_at' ] );
	}

	public function test_status_reports_a_stale_check_that_found_no_cipher_without_checking_again() :void {
		$checkedAt = \time() - 3*\DAY_IN_SECONDS;
		$this->storeCipherState( $checkedAt, '' );

		$this->assertStringContainsString( self::CIPHER_REASON, $this->explanations() );
		$this->assertSame( $checkedAt, $this->requireController()->comps->file_locker->getState()[ 'cipher_last_checked_at' ] );
	}

	public function test_status_is_quiet_when_the_last_check_found_a_cipher() :void {
		$this->storeCipherState( \time() - 3*\DAY_IN_SECONDS, 'aes-256-cbc' );

		$this->assertStringNotContainsString( self::CIPHER_REASON, $this->explanations() );
	}

	private function storeCipherState( int $checkedAt, string $cipher ) :void {
		$state = $this->requireController()->comps->file_locker->getState();
		$state[ 'cipher_last_checked_at' ] = $checkedAt;
		$state[ 'cipher' ] = $cipher;
		$this->requireController()->opts
			->optSet( 'filelocker_state', ( new FileLockerState() )->prepareForStorage( $state ) )
			->store();
	}

	private function explanations() :string {
		return \implode( "\n", ( new FileLocker() )->configureRows()[ 0 ][ 'explanations' ] );
	}
}
