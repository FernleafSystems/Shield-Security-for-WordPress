<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Map;

class MapProgressTracker {

	private array $completedDirs;

	private int $totalDirsComplete;

	private int $totalFilesComplete;

	private ?string $mostRecentFileInDir;

	private array $dirsThisRound = [];

	public function __construct(
		array $completedDirs = [],
		?string $mostRecentFile = null,
		int $totalDirsComplete = 0,
		int $totalFilesComplete = 0
	) {
		$this->completedDirs = $completedDirs;
		$this->mostRecentFileInDir = $mostRecentFile;
		$this->totalDirsComplete = $totalDirsComplete;
		$this->totalFilesComplete = $totalFilesComplete;
	}

	public function completed() :array {
		return $this->completedDirs;
	}

	public function totalDirsComplete() :int {
		return $this->totalDirsComplete;
	}

	public function totalFilesComplete() :int {
		return $this->totalFilesComplete;
	}

	public function isDirCompleted( string $dir ) :bool {
		$dir = trailingslashit( $dir );
		$completed = isset( $this->completedDirs[ $dir ] );
		if ( !$completed ) {
			foreach ( \array_keys( $this->completedDirs ) as $previouslyCompletedDir ) {
				if ( \str_starts_with( $dir, $previouslyCompletedDir ) ) {
					$completed = true;
					break;
				}
			}
		}
		return $completed;
	}

	public function isFileCompleted( string $file ) :bool {
		return !empty( $this->mostRecentFileInDir ) && \strnatcmp( $this->mostRecentFileInDir, $file ) >= 0;
	}

	public function getDirsThisRound() :array {
		return $this->dirsThisRound;
	}

	public function getMostRecentFile() :?string {
		return $this->mostRecentFileInDir;
	}

	public function markFileCompleted( string $file ) :void {
		$this->mostRecentFileInDir = $file;
		$this->totalFilesComplete++;
	}

	public function markDirCompleted( string $dir ) :void {
		$dir = trailingslashit( $dir );

		foreach ( \array_keys( $this->completedDirs ) as $previouslyCompletedDir ) {
			if ( \str_starts_with( $previouslyCompletedDir, $dir ) ) {
				$this->completedDirs[ $previouslyCompletedDir ] = false;
			}
		}

		$this->dirsThisRound[] = $dir;
		$this->completedDirs = \array_filter( $this->completedDirs );
		$this->completedDirs[ $dir ] = true;
		$this->totalDirsComplete++;
		$this->mostRecentFileInDir = null;
	}
}