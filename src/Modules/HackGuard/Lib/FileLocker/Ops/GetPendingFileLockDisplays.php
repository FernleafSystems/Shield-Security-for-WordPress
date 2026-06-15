<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

/**
 * @phpstan-import-type FileLockCandidateDisplay from GetFileLockCandidateDisplays
 * @phpstan-type PendingFileLockDisplay FileLockCandidateDisplay
 */
class GetPendingFileLockDisplays {

	private ?GetFileLockCandidateDisplays $candidateDisplays;

	public function __construct( ?GetFileLockCandidateDisplays $candidateDisplays = null ) {
		$this->candidateDisplays = $candidateDisplays;
	}

	/**
	 * @return list<PendingFileLockDisplay>
	 */
	public function run() :array {
		$records = [];
		foreach ( $this->pendingFileKeys() as $fileKey ) {
			$display = $this->candidateDisplays()->forFileKey( $fileKey );
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

	private function candidateDisplays() :GetFileLockCandidateDisplays {
		if ( $this->candidateDisplays === null ) {
			$this->candidateDisplays = new GetFileLockCandidateDisplays();
		}

		return $this->candidateDisplays;
	}
}
