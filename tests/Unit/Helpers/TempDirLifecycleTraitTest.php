<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class TempDirLifecycleTraitTest extends TestCase {

	public function test_creates_unique_owned_temp_dirs_and_removes_nested_contents() :void {
		$harness = $this->harness();
		$first = $harness->dir( 'shield-helper-dir-' );
		$second = $harness->dir( 'shield-helper-dir-' );

		$this->assertNotSame( $first, $second );
		$this->assertDirectoryExists( $first );
		$this->assertDirectoryExists( $second );

		$nested = Path::join( $first, 'nested' );
		\mkdir( $nested );
		\file_put_contents( Path::join( $nested, 'payload.txt' ), 'payload' );

		$harness->cleanup();

		$this->assertDirectoryDoesNotExist( $first );
		$this->assertDirectoryDoesNotExist( $second );
	}

	public function test_creates_tracked_files_and_later_created_paths() :void {
		$harness = $this->harness();
		$file = $harness->file( 'shield-helper-file-', '.txt', 'content' );
		$laterPath = $harness->path( 'shield-helper-later-', '.tmp' );
		\file_put_contents( $laterPath, 'later' );

		$this->assertSame( 'content', \file_get_contents( $file ) );
		$this->assertFileExists( $laterPath );

		$harness->cleanup();

		$this->assertFileDoesNotExist( $file );
		$this->assertFileDoesNotExist( $laterPath );
	}

	public function test_supports_explicit_parent_directory() :void {
		$harness = $this->harness();
		$parent = $harness->dir( 'shield-helper-parent-' );
		$child = $harness->dir( 'shield-helper-child-', $parent );

		$this->assertStringStartsWith( \str_replace( '\\', '/', $parent ).'/', \str_replace( '\\', '/', $child ) );
		$this->assertDirectoryExists( $child );

		$harness->cleanup();

		$this->assertDirectoryDoesNotExist( $parent );
	}

	public function test_rejects_missing_explicit_parent_directory() :void {
		$harness = $this->harness();
		$missingParent = Path::join(
			\sys_get_temp_dir(),
			'shield-helper-missing-parent-'.\bin2hex( \random_bytes( 4 ) )
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Temporary parent directory does not exist' );

		$harness->dir( 'shield-helper-child-', $missingParent );
	}

	public function test_refuses_to_clean_dangerous_roots_even_if_tracked() :void {
		$harness = $this->harness();
		$harness->forceTrackedDir( \sys_get_temp_dir() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Refusing to clean unowned temporary path' );

		$harness->cleanup();
	}

	public function test_rejects_prefix_and_suffix_path_separators() :void {
		$harness = $this->harness();

		$this->expectException( \InvalidArgumentException::class );
		$harness->path( 'bad/prefix-' );
	}

	private function harness() :object {
		return new class() {
			use TempDirLifecycleTrait;

			public function dir( string $prefix = 'shield-test-', ?string $parentDir = null ) :string {
				return $this->createTrackedTempDir( $prefix, $parentDir );
			}

			public function path( string $prefix = 'shield-test-', string $suffix = '', ?string $parentDir = null ) :string {
				return $this->createTrackedTempPath( $prefix, $suffix, $parentDir );
			}

			public function file(
				string $prefix = 'shield-test-',
				string $suffix = '',
				string $contents = '',
				?string $parentDir = null
			) :string {
				return $this->createTrackedTempFile( $prefix, $suffix, $contents, $parentDir );
			}

			public function cleanup() :void {
				$this->cleanupTrackedTempDirs();
			}

			public function forceTrackedDir( string $path ) :void {
				$this->trackedTempDirs[] = $path;
			}
		};
	}
}
