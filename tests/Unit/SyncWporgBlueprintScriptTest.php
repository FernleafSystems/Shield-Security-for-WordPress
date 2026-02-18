<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Basic safety checks for the WordPress.org blueprint sync script.
 */
class SyncWporgBlueprintScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testSyncScriptHasValidSyntax() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$scriptPath = $this->getPluginFilePath( 'bin/sync-wporg-blueprint.php' );
		$output = [];
		$returnCode = 0;
		\exec( 'php -l '.\escapeshellarg( $scriptPath ).' 2>&1', $output, $returnCode );

		$this->assertSame( 0, $returnCode, 'bin/sync-wporg-blueprint.php should have valid PHP syntax: '.\implode( "\n", $output ) );
	}

	public function testSyncScriptDeclaresExpectedCliOptions() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$content = $this->getPluginFileContents( 'bin/sync-wporg-blueprint.php', 'sync script' );
		$this->assertStringContainsString( "'svn-root:'", $content );
		$this->assertStringContainsString( "'source::'", $content );
		$this->assertStringContainsString( "'check-only'", $content );
	}
}
