<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility\FileLockKeyApplicability;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type FileLockCandidateDisplay array{
 *   file_key:string,
 *   title:string,
 *   path:string
 * }
 */
class GetFileLockCandidateDisplays {

	use PluginControllerConsumer;

	private FileLockKeyApplicability $applicability;

	public function __construct( ?FileLockKeyApplicability $applicability = null ) {
		$this->applicability = $applicability ?? FileLockKeyApplicability::fromCurrentEnvironment();
	}

	/**
	 * @return list<FileLockCandidateDisplay>
	 */
	public function run() :array {
		$displays = [];
		foreach ( BuildFileFromFileKey::SUPPORTED_FILE_KEYS as $fileKey ) {
			$display = $this->forFileKey( $fileKey );
			if ( $display !== null ) {
				$displays[] = $display;
			}
		}

		return $displays;
	}

	/**
	 * The critical files still to lock: those not selected for locking. A selected file is locked, or is waiting
	 * for its first lock.
	 * @return list<FileLockCandidateDisplay>
	 */
	public function notSelected() :array {
		$selected = self::con()->comps->file_locker->getFilesToLock();
		return \array_values( \array_filter(
			$this->run(),
			static fn( array $display ) :bool => !\in_array( $display[ 'file_key' ], $selected, true )
		) );
	}

	/**
	 * @return FileLockCandidateDisplay|null
	 */
	public function forFileKey( string $fileKey ) :?array {
		$fileKey = \strtolower( \trim( $fileKey ) );
		if ( !\in_array( $fileKey, BuildFileFromFileKey::SUPPORTED_FILE_KEYS, true )
			 || !$this->applicability->isApplicable( $fileKey )
		) {
			return null;
		}

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
