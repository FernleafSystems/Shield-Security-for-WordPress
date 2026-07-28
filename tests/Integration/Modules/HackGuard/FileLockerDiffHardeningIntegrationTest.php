<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansFileLockerDiff;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\{
	Diff,
	DiffUnavailableException,
	PrepareWpDiffInput
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	TempDirLifecycleTrait,
	TestDataFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class FileLockerDiffHardeningIntegrationTest extends ShieldIntegrationTestCase {

	use TempDirLifecycleTrait;

	private array $cacheEntries = [];
	private array $optionSnapshot = [];

	public function tear_down() {
		foreach ( $this->cacheEntries as $entry ) {
			\wp_cache_delete( $entry[ 'key' ], $entry[ 'group' ] );
		}
		$this->cacheEntries = [];

		if ( static::con() !== null ) {
			static::con()->comps->file_locker->clearLocks();
		}
		if ( !empty( $this->optionSnapshot ) ) {
			$this->restoreSelectedOptions( $this->optionSnapshot );
		}
		$this->cleanupTrackedTempDirs();
		parent::tear_down();
	}

	public function test_wordpress_69_fallback_handles_invalid_utf8_and_preserves_context_policy() :void {
		$version = (string)\get_bloginfo( 'version' );
		if ( \preg_match( '/^6\.9(?:[.-]|$)/', $version ) !== 1 ) {
			$this->markTestSkipped( 'WordPress 6.9 regression requires 6.9.x; actual version: '.$version );
		}
		$this->assertSame( 1, \preg_match( '/^6\.9(?:[.-]|$)/', $version ), 'Actual WordPress version: '.$version );

		$invalid = \chr( 0xC3 );
		$original = \implode( "\n", [
			'fourth-before-marker',
			'third-before-marker',
			'second-before-marker',
			'first-before-marker',
			"alpha {$invalid} beta",
			'first-after-marker',
			'second-after-marker',
			'third-after-marker',
			'fourth-after-marker',
		] );
		$current = \str_replace( "alpha {$invalid} beta", "alpha {$invalid} changed", $original );

		$prepared = ( new PrepareWpDiffInput() )->run( $original, $current );
		$this->assertSame( 1, \preg_match( '//u', $prepared[ 'original' ] ) );
		$this->assertSame( 1, \preg_match( '//u', $prepared[ 'current' ] ) );

		try {
			$html = ( new FileLockerDiffWpHashesFailureProbe() )->run( $original, $current );
		}
		catch ( DiffUnavailableException $e ) {
			$previous = $e->getPrevious();
			$this->fail( \sprintf(
				'WordPress 6.9 malformed-byte fallback failed: %s: %s',
				$previous === null ? 'unknown' : \get_class( $previous ),
				$previous === null ? '' : $previous->getMessage()
			) );
		}

		$this->assertNotSame( '', $html );
		$this->assertHtmlContainsMarker( '\\xC3', $html, 'WordPress 6.9 FileLocker fallback' );
		$this->assertHtmlContainsMarker( 'is-split-view', $html, 'WordPress 6.9 split-view diff' );
		$this->assertHtmlContainsMarker( 'third-before-marker', $html, 'WordPress 6.9 leading context' );
		$this->assertHtmlContainsMarker( 'third-after-marker', $html, 'WordPress 6.9 trailing context' );
		$this->assertHtmlNotContainsMarker( 'fourth-before-marker', $html, 'WordPress 6.9 leading context boundary' );
		$this->assertHtmlNotContainsMarker( 'fourth-after-marker', $html, 'WordPress 6.9 trailing context boundary' );
	}

	public function test_fallback_keeps_valid_multibyte_utf8_on_unchanged_path() :void {
		$original = "alpha \xC3\xA9 beta";
		$current = "alpha \xC3\xA9 changed";
		$prepared = ( new PrepareWpDiffInput() )->run( $original, $current );

		$this->assertSame( $original, $prepared[ 'original' ] );
		$this->assertSame( $current, $prepared[ 'current' ] );

		try {
			$html = ( new FileLockerDiffWpHashesFailureProbe() )->run( $original, $current );
		}
		catch ( DiffUnavailableException $e ) {
			$previous = $e->getPrevious();
			$this->fail( \sprintf(
				'Valid UTF-8 fallback failed: %s: %s',
				$previous === null ? 'unknown' : \get_class( $previous ),
				$previous === null ? '' : $previous->getMessage()
			) );
		}

		$this->assertNotSame( '', $html );
		$this->assertHtmlNotContainsMarker( '\\xC3', $html, 'Valid UTF-8 FileLocker fallback' );
	}

	public function test_diff_failure_preserves_filelocker_modal_data_and_actions_contract() :void {
		$this->prepareFileLockerRuntime();
		$original = 'original-file-content-secret';
		$current = 'current-file-content-secret';
		$tempPath = $this->createTrackedTempFile( 'shield-filelocker-diff-', '.php', $current );
		$recordID = TestDataFactory::insertFileLockRecord( 'wpconfig', $tempPath, \time() - 60 );
		$con = $this->requireController();
		$con->comps->file_locker->clearLocks();

		$cacheKey = 'file-content-'.$recordID;
		$cacheGroup = $con->prefix( 'filelocker' );
		$this->assertTrue( \wp_cache_set( $cacheKey, $original, $cacheGroup, 5 ) );
		$this->cacheEntries[] = [
			'key'   => $cacheKey,
			'group' => $cacheGroup,
		];

		$data = ( new FileLockerDiffRenderFailureProbe( [ 'rid' => $recordID ] ) )->exposeRenderData();

		$this->assertTrue( $data[ 'success' ] );
		$this->assertTrue( $data[ 'flags' ][ 'has_diff' ] );
		$this->assertFalse( $data[ 'flags' ][ 'diff_available' ] );
		$this->assertSame( '', $data[ 'html' ][ 'diff' ] );
		$this->assertSame(
			'The file comparison could not be displayed. File details and actions are still available below.',
			$data[ 'strings' ][ 'diff_unavailable' ]
		);
		$this->assertNotSame( '', (string)$data[ 'ajax' ][ 'original' ] );
		$this->assertNotSame( '', (string)$data[ 'ajax' ][ 'current' ] );

		$encodedData = (string)\json_encode( $data );
		$this->assertStringNotContainsString( FileLockerDiffRenderFailureProbe::ERROR_SECRET, $encodedData );
		$this->assertStringNotContainsString( $original, $encodedData );
		$this->assertStringNotContainsString( $current, $encodedData );
	}

	public function test_invalid_record_keeps_controlled_action_exception() :void {
		$this->prepareFileLockerRuntime();
		$this->expectException( ActionException::class );
		( new FileLockerDiffRenderFailureProbe( [ 'rid' => 999999999 ] ) )->exposeRenderData();
	}

	private function prepareFileLockerRuntime() :void {
		$con = $this->requireController();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [ 'file_locker', 'filelocker_state', 'snapi_data' ] );
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );
		RuntimeTestState::primeShieldNetHandshake();
		$con->opts->optSet( 'file_locker', [ 'wpconfig' ] )->store();

		$handler = RuntimeTestState::requireDbHandler( 'file_locker', true );
		$handler->tableDelete( true );
		RuntimeTestState::requireDbHandler( 'file_locker', true );
		$con->comps->file_locker->clearLocks();
	}
}

class FileLockerDiffWpHashesFailureProbe extends Diff {

	protected function requestWpHashesDiff( string $original, string $current ) :?array {
		throw new \RuntimeException( 'Network must not be called by this integration test.' );
	}
}

class FileLockerDiffRenderFailureProbe extends ScansFileLockerDiff {

	public const ERROR_SECRET = 'raw-diff-type-error-secret';

	public function exposeRenderData() :array {
		return $this->getRenderData();
	}

	protected function generateDiff( string $original, string $current ) :string {
		throw new \TypeError( self::ERROR_SECRET );
	}
}
