<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Map;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Exc\TimeLimitReachedException;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Map\Listing\AbstractFileListing;
use FernleafSystems\Wordpress\Services\Services;

class MapDir {

	private static ?string $rootDir = null;

	private AbstractFileListing $map;

	private MapProgressTracker $tracker;

	private FileFilter $filter;

	private string $dir;

	private string $hashAlgo;

	private int $stopAtTS;

	public function __construct(
		AbstractFileListing $map,
		MapProgressTracker $tracker,
		FileFilter $filter,
		string $dirToMap,
		string $hashAlgo,
		int $stopAtTS,
		?string $rootDir = null
	) {
		$this->map = $map;
		$this->tracker = $tracker;
		$this->filter = $filter;
		$this->dir = $dirToMap;
		$this->hashAlgo = $hashAlgo;
		$this->stopAtTS = $stopAtTS;

		if ( $rootDir !== null ) {
			self::$rootDir = $this->dir;
		}
	}

	/**
	 * @throws TimeLimitReachedException
	 * @throws Exc\MapDirCannotBeOpenedException
	 */
	public function run() :void {
		foreach ( $this->enumDirs() as $dir ) {
			try {
				( new MapDir( $this->map, $this->tracker, $this->filter, $dir, $this->hashAlgo, $this->stopAtTS ) )->run();
			}
			catch ( Exc\MapDirCannotBeOpenedException $e ) {
//				error_log( $e->getMessage() );
			}
		}

		$FS = Services::WpFs()->fs();
		foreach ( $this->enumFiles() as $attr ) {
			$normal = $this->normalisePath( $attr[ 'p' ] );
			if ( $FS->is_readable( $attr[ 'p' ] ) ) {
				$hash = empty( $this->hashAlgo ) ? '' : \hash_file( $this->hashAlgo, $attr[ 'p' ] );
				if ( \is_string( $hash ) ) {
					$this->map->addRaw( $normal, '', $hash, $attr[ 'm' ], $attr[ 's' ] );
				}
			}
			$this->tracker->markFileCompleted( $normal );
			if ( \time() >= $this->stopAtTS ) {
				throw new TimeLimitReachedException();
			}
		}

		$this->tracker->markDirCompleted( $this->normalisePath( $this->dir ) );

		if ( \time() >= $this->stopAtTS ) {
			throw new TimeLimitReachedException();
		}
	}

	/**
	 * @throws Exc\MapDirCannotBeOpenedException
	 */
	private function enumDirs() :array {
		$dirs = [];
		try {
			$it = new \FilesystemIterator( $this->dir );
		}
		catch ( \Exception $e ) {
			throw new Exc\MapDirCannotBeOpenedException( $e->getMessage() );
		}
		foreach ( $it as $item ) {
			/** @var \SplFileInfo $item */
			if ( $item->isDir() && !$item->isLink() ) {
				$normalisedPath = $this->normalisePath( $item->getPathname() );
				if ( !$this->tracker->isDirCompleted( $normalisedPath ) && !$this->filter->isExcluded( $normalisedPath ) ) {
					$dirs[] = $item->getPathname();
				}
			}
		}
		\natsort( $dirs );
		return $dirs;
	}

	/**
	 * @throws Exc\MapDirCannotBeOpenedException
	 */
	private function enumFiles() :array {
		$files = [];
		try {
			$it = new \FilesystemIterator( $this->dir );
		}
		catch ( \Exception $e ) {
			throw new Exc\MapDirCannotBeOpenedException( $e->getMessage() );
		}

		foreach ( $it as $item ) {
			/** @var \SplFileInfo $item */
			if ( $item->isFile() && !$item->isLink() && !empty( $item->getSize() ) ) {
				$normalisedPath = $this->normalisePath( $item->getPathname() );
				if ( !$this->tracker->isFileCompleted( $normalisedPath )
					 && $this->filter->isFileWithinTimeRange( (int)$item->getMTime() )
					 && $this->filter->isFileSizeAllowed( $item->getSize() )
					 && !$this->filter->isExcluded( $normalisedPath )
				) {
					$files[ $normalisedPath ] = [
						'p' => $item->getPathname(),
						'm' => (int)$item->getMTime(),
						's' => $item->getSize(),
					];
				}
			}
		}

		// Natural sort is required as we use this to pick-up our previous position in the file map.
		\ksort( $files, \SORT_NATURAL );
		return \array_values( $files );
	}

	private function normalisePath( string $path ) :string {
		return \ltrim( wp_normalize_path( \preg_replace( '#^'.\preg_quote( self::$rootDir, '#' ).'#', '', $path, 1 ) ), '/' );
	}
}