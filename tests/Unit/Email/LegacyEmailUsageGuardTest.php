<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class LegacyEmailUsageGuardTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testSendEmailWithWrapUsageIsControllerOnly() :void {
		$allowedFile = $this->normalizePath( $this->getPluginFilePath( 'src/Controller/Email/EmailCon.php' ) );
		$srcRoot = $this->getPluginFilePath( 'src' );
		$offenders = [];

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $srcRoot, \FilesystemIterator::SKIP_DOTS )
		);

		/** @var \SplFileInfo $file */
		foreach ( $iterator as $file ) {
			if ( !$file->isFile() || $file->getExtension() !== 'php' ) {
				continue;
			}

			$path = $this->normalizePath( $file->getPathname() );
			$content = \file_get_contents( $file->getPathname() );
			if ( $content === false ) {
				continue;
			}

			if ( \preg_match( '#sendEmailWithWrap\s*\(#', $content ) === 1 && $path !== $allowedFile ) {
				$offenders[] = $path;
			}
		}

		\sort( $offenders );
		$this->assertSame( [], $offenders, 'sendEmailWithWrap() should only appear in EmailCon for compatibility.' );
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', \strtolower( $path ) );
	}
}
