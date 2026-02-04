<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Map;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Exc\TimeLimitReachedException;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Map\Listing\AbstractFileListing;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility\FileNameFor;
use FernleafSystems\Wordpress\Services\Services;

class MapHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\BaseFsHandler {

	protected MapVO $mapVO;

	protected FileFilter $filter;

	private AbstractFileListing $map;

	private bool $wpCfgRemapped = false;

	/**
	 * @throws \Exception
	 */
	public function __construct( MapVO $mapVO, string $uuid, int $stopAtTS ) {
		parent::__construct( $mapVO->dir, $uuid, $stopAtTS );
		$this->mapVO = $mapVO;
	}

	/**
	 * @throws \Exception
	 */
	public function run() :array {
		$completed = false;

		$this->filter = new FileFilter(
			\array_merge(
				\array_map( '\base64_decode', $this->mapVO->exclusions[ 'contains' ] ?? [] ),
				[
					\str_replace( wp_normalize_path( ABSPATH ), '', \dirname( $this->workingDir() ) ),
				]
			),
			\array_map( '\base64_decode', $this->mapVO->exclusions[ 'regex' ] ?? [] ),
			$this->mapVO->maxFileSize,
			$this->mapVO->newerThanTS,
			$this->mapVO->olderThanTS
		);

		$map = $this->map();
		$track = $this->loadProgress();
		$mapper = new MapDir( $map, $track, $this->filter, $this->mapVO->dir, $this->mapVO->hashAlgo, $this->stopAtTS, $this->mapVO->dir );
		try {
			$map->startLargeListing();

			$mapper->run();
			// WP Config mapping is done only after completion of the full map, since we don't want it duplicated
			$this->mapForWpConfig();

			$map->finishLargeListing( true );
			$completed = true;
		}
		catch ( TimeLimitReachedException $e ) {
			$map->finishLargeListing( true );
			Services::WpFs()->putFileContent(
				$this->pathToProgress(),
				wp_json_encode( [
					'completed_dirs'        => $track->completed(),
					'most_recent_file'      => $track->getMostRecentFile(),
					'total_completed_dirs'  => $track->totalDirsComplete(),
					'total_completed_files' => $track->totalFilesComplete(),
				] )
			);
		}
		catch ( \Exception $e ) {
			$map->finishLargeListing( false );
			throw $e;
		}

		return [
			'href'                 => $completed ? $this->mapURL() : '',
			'completed_dirs'       => \count( $track->completed() ),
			'total_completed_dirs' => $track->totalDirsComplete(),
			'map_count'            => $track->totalFilesComplete(),
			'latest_file'          => $track->getMostRecentFile(),
			'wpcfg_remapped'       => (int)$this->wpCfgRemapped,
			/*
			'dirs_this_round'      => $track->getDirsThisRound(),
			'latest_file'          => $track->getMostRecentFile(),
			*/
		];
	}

	protected function map() :AbstractFileListing {
		return $this->map ??= $this->useSqlite() ?
			new Listing\SqliteFileListing( path_join( $this->workingDir(), $this->dbFile() ) )
			: new Listing\FlatFileListing( path_join( $this->workingDir(), $this->dbFile() ) );
	}

	protected function mapForWpConfig() :void {
		$possibleDirs = \array_unique( \array_map(
			fn( $path ) => trailingslashit( wp_normalize_path( $path ) ),
			[
				ABSPATH,
				$this->mapVO->dir,
			]
		) );

		$stdPathFound = null;
		foreach ( $possibleDirs as $possibleDir ) {
			$maybeStdPath = path_join( $possibleDir, 'wp-config.php' );
			if ( \file_exists( $maybeStdPath ) ) {
				$stdPathFound = $maybeStdPath;
				break;
			}
		}

		$normalAbs = wp_normalize_path( ABSPATH );
		if ( empty( $stdPathFound ) && !empty( \dirname( $normalAbs ) ) ) {
			$levelUpPath = path_join( \dirname( $normalAbs ), 'wp-config.php' );
			if ( \is_readable( $levelUpPath ) ) {
				$FS = Services::WpFs()->fs();
				$this->map()->addRaw(
					'wp-config.php',
					'',
					empty( $this->mapVO->hashAlgo ) ? '' : (string)\hash_file( $this->mapVO->hashAlgo, $levelUpPath ),
					(int)$FS->mtime( $levelUpPath ),
					(int)$FS->size( $levelUpPath )
				);
				$this->wpCfgRemapped = true;
			}
		}
	}

	protected function dbFile() :string {
		return FileNameFor::For( $this->mapType().'_map_db' );
	}

	protected function useSqlite() :bool {
		return \in_array( 'sqlite3', \get_loaded_extensions() );
	}

	protected function mapType() :string {
		return $this->mapVO->type;
	}

	protected function pathToProgress() :string {
		return path_join( $this->workingDir(), FileNameFor::For( $this->mapType().'_map_progress' ) );
	}

	/**
	 * @throws \Exception
	 */
	private function loadProgress() :MapProgressTracker {
		$dirsCompleted = [];
		$mostRecentFile = null;
		$totalDirs = $totalFiles = 0;
		if ( \is_file( $this->pathToProgress() ) ) {
			$raw = Services::WpFs()->getFileContent( $this->pathToProgress() );
			if ( !empty( $raw ) ) {
				$rawProgress = \json_decode( $raw, true );
				if ( !empty( $rawProgress ) && \is_array( $rawProgress ) ) {
					[
						'completed_dirs'        => $dirsCompleted,
						'most_recent_file'      => $mostRecentFile,
						'total_completed_dirs'  => $totalDirs,
						'total_completed_files' => $totalFiles,
					] = $rawProgress;
				}
			}
		}
		return new MapProgressTracker( $dirsCompleted, $mostRecentFile, $totalDirs, $totalFiles );
	}

	private function mapURL() :string {
		return remove_query_arg(
			'ver',
			self::con()->urls->forPluginItem(
				sprintf( '%s/%s', untrailingslashit( $this->baseArchivePath() ), $this->dbFile() ) ),
		);
	}
}