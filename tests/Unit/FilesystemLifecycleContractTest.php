<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use PHPUnit\Framework\TestCase;

class FilesystemLifecycleContractTest extends TestCase {

	public function test_unit_filesystem_fixtures_use_tracked_lifecycle_helpers() :void {
		$violations = [];

		foreach ( $this->unitPhpFiles() as $path ) {
			$source = (string)\file_get_contents( $path );
			foreach ( \preg_split( '/\R/', $source ) ?: [] as $index => $line ) {
				if ( $this->lineUsesDirectSharedTempFixturePattern( $line ) ) {
					$violations[] = \sprintf(
						'%s:%d %s',
						$this->relativePath( $path ),
						$index + 1,
						\trim( $line )
					);
				}
			}
		}

		$this->assertSame(
			[],
			$violations,
			"Unit tests must create temporary filesystem fixtures through TempDirLifecycleTrait.\n"
			.\implode( "\n", $violations )
		);
	}

	/**
	 * @return string[]
	 */
	private function unitPhpFiles() :array {
		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( __DIR__, \FilesystemIterator::SKIP_DOTS )
		);

		/** @var \SplFileInfo $file */
		foreach ( $iterator as $file ) {
			$path = $file->getPathname();
			if ( !$file->isFile() || $file->getExtension() !== 'php' || $path === __FILE__ ) {
				continue;
			}
			$files[] = $path;
		}

		\sort( $files );
		return $files;
	}

	private function lineUsesDirectSharedTempFixturePattern( string $line ) :bool {
		$line = (string)\preg_replace( '/\s+/', '', $line );

		return \preg_match( '#\\\\?tempnam\(\\\\?sys_get_temp_dir\(\),#', $line ) === 1
			   || \preg_match( '#\\\\?sys_get_temp_dir\(\).*\\\\?uniqid\(#', $line ) === 1
			   || \preg_match( '#\\\\?uniqid\(.*\\\\?sys_get_temp_dir\(\)#', $line ) === 1
			   || \preg_match( '#Path::join\(\\\\?sys_get_temp_dir\(\),.*\\\\?uniqid\(#', $line ) === 1;
	}

	private function relativePath( string $path ) :string {
		return \str_replace(
			'\\',
			'/',
			\ltrim( \str_replace( \dirname( __DIR__, 2 ), '', $path ), '\\/' )
		);
	}
}
