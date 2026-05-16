<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

trait WrittenFixtureFiles {

	private array $writtenFixtureFiles = [];

	protected function trackWrittenFixtureFile( string $path ) :string {
		$path = $this->normalizeFixturePath( $path );
		$this->writtenFixtureFiles[] = $path;
		return $path;
	}

	protected function removeWrittenFixtureFiles() :void {
		foreach ( \array_reverse( \array_unique( $this->writtenFixtureFiles ) ) as $file ) {
			if ( \is_file( $file ) ) {
				@unlink( $file );
			}
			$this->removeEmptyFixtureParents( \dirname( $file ) );
		}
		$this->writtenFixtureFiles = [];
	}

	private function removeEmptyFixtureParents( string $dir ) :void {
		$dir = \rtrim( $this->normalizeFixturePath( $dir ), '/' );
		while ( $dir !== '' && !\in_array( $dir, $this->fixtureProtectedRoots(), true ) ) {
			if ( !@rmdir( $dir ) ) {
				break;
			}
			$parent = \rtrim( $this->normalizeFixturePath( \dirname( $dir ) ), '/' );
			if ( $parent === $dir ) {
				break;
			}
			$dir = $parent;
		}
	}

	private function fixtureProtectedRoots() :array {
		return \array_values( \array_unique( \array_map(
			fn( string $path ) :string => \rtrim( $this->normalizeFixturePath( $path ), '/' ),
			[
				ABSPATH,
				ABSPATH.WPINC,
				ABSPATH.'wp-admin',
				WP_PLUGIN_DIR,
				WP_CONTENT_DIR,
				WP_CONTENT_DIR.'/themes',
				WP_CONTENT_DIR.'/uploads',
			]
		) ) );
	}

	private function normalizeFixturePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}
