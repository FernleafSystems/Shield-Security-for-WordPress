<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

/**
 * @phpstan-type PendingFileLockDisplay array{
 *   file_key:string,
 *   title:string,
 *   path:string
 * }
 */
class GetPendingFileLockDisplays {

	/**
	 * @return list<PendingFileLockDisplay>
	 */
	public function run() :array {
		$records = [];
		foreach ( $this->pendingFileKeys() as $fileKey ) {
			$display = $this->buildDisplayForFileKey( $fileKey );
			if ( $display !== null ) {
				$records[] = $display;
			}
		}

		return $records;
	}

	public function count() :int {
		return \count( $this->run() );
	}

	public function describeCount( int $pendingCount ) :string {
		return \sprintf(
			_n(
				'%s initial file lock is still being created.',
				'%s initial file locks are still being created.',
				$pendingCount,
				'wp-simple-firewall'
			),
			$pendingCount
		);
	}

	/**
	 * @return list<string>
	 */
	private function pendingFileKeys() :array {
		try {
			return \array_values( \array_map(
				static fn( $fileKey ) :string => (string)$fileKey,
				( new GetFileLocksToCreate() )->run()
			) );
		}
		catch ( \Throwable $e ) {
			return [];
		}
	}

	/**
	 * @return PendingFileLockDisplay|null
	 */
	private function buildDisplayForFileKey( string $fileKey ) :?array {
		try {
			$file = ( new BuildFileFromFileKey() )->build( $fileKey );
			$path = (string)( $file->getExistingPossiblePaths()[ 0 ] ?? '' );
			if ( $path === '' ) {
				return null;
			}

			$path = wp_normalize_path( $path );
			return [
				'file_key' => $fileKey,
				'title'    => \basename( $path ),
				'path'     => $path,
			];
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}
}
