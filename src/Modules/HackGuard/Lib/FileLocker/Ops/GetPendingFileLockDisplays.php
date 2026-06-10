<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

/**
 * @phpstan-import-type FileLockCandidateDisplay from GetFileLockCandidateDisplays
 * @phpstan-type PendingFileLockDisplay FileLockCandidateDisplay
 */
class GetPendingFileLockDisplays {

	/**
	 * @return list<PendingFileLockDisplay>
	 */
	public function run() :array {
		$records = [];
		$candidateDisplays = new GetFileLockCandidateDisplays();
		foreach ( $this->pendingFileKeys() as $fileKey ) {
			$display = $candidateDisplays->forFileKey( $fileKey );
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
}
