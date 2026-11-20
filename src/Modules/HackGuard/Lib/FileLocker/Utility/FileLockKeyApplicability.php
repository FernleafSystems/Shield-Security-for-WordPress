<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\BuildFileFromFileKey;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Which files File Locker can lock on this site. Every screen that offers files to lock asks this, so they all
 * offer the same files: a file can be newly locked only if this server uses that kind of file and the file is here.
 *
 * Saving settings never drops a selected file because it's missing: if it was locked, its lock record is what lets
 * the owner review or restore it. Only a file this server never uses is dropped on save. (A selected file that was
 * never locked and is missing is dropped later, when File Locker fails to create its lock.)
 */
class FileLockKeyApplicability {

	private bool $isWindows;

	/**
	 * @var list<string>
	 */
	private array $presentFileKeys;

	/**
	 * @param list<string> $presentFileKeys supported file keys whose file exists on this site
	 */
	public function __construct( bool $isWindows, array $presentFileKeys ) {
		$this->isWindows = $isWindows;
		$this->presentFileKeys = $presentFileKeys;
	}

	public static function fromCurrentEnvironment() :self {
		$builder = new BuildFileFromFileKey();
		return new self(
			Services::Data()->isWindows(),
			\array_values( \array_filter(
				BuildFileFromFileKey::SUPPORTED_FILE_KEYS,
				fn( string $fileKey ) :bool => !empty( $builder->build( $fileKey )->getExistingPossiblePaths() )
			) )
		);
	}

	/**
	 * Whether the file can be newly selected for locking here.
	 */
	public function isApplicable( string $fileKey ) :bool {
		return $this->isServerSupported( $fileKey ) && $this->isPresent( $fileKey );
	}

	/**
	 * Whether this server uses this kind of file at all, e.g. Web.config only matters to IIS.
	 */
	public function isServerSupported( string $fileKey ) :bool {
		return $fileKey !== 'root_webconfig' || $this->isWindows;
	}

	private function isPresent( string $fileKey ) :bool {
		return \in_array( $fileKey, $this->presentFileKeys, true );
	}

	/**
	 * For a saved selection: drops only files this server never uses, keeping any that have gone missing.
	 * @param array<array-key,mixed> $fileKeys
	 * @return list<mixed>
	 */
	public function removeServerUnsupportedKeys( array $fileKeys ) :array {
		return \array_values( \array_filter(
			$fileKeys,
			fn( $fileKey ) :bool => !\is_string( $fileKey ) || $this->isServerSupported( $fileKey )
		) );
	}
}
