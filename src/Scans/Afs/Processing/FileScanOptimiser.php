<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Plugin\Files as PluginFiles;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Theme\Files as ThemeFiles;

class FileScanOptimiser {

	use PluginControllerConsumer;

	private const CACHE_DIR = 'afs-file-optimiser';
	private const KNOWN_VALID = 'known-valid';
	private const MALWARE_CLEAN = 'malware-clean';

	public function canSkipKnownValidFile( string $path, ScanActionVO $action ) :bool {
		$skip = false;
		if ( $this->isCacheUsable() && $this->isAccessibleSupportedFile( $path, $action ) ) {
			$context = $this->detectCurrentContext( $path );
			if ( $context instanceof TrustedFileContext ) {
				$size = $this->fileSize( $path );
				$sha256 = null;
				$contextKey = $context->key();
				foreach ( $this->readRecords( $this->shardPath( self::KNOWN_VALID, $contextKey ) ) as $record ) {
					if ( $record[ 'context_key' ] === $contextKey
						 && $record[ 'size' ] === $size ) {
						$sha256 ??= $this->fileSha256( $path );
						if ( \hash_equals( $record[ 'sha256' ], $sha256 ) ) {
							$skip = true;
							break;
						}
					}
				}
			}
		}
		return $skip;
	}

	public function recordKnownValidFile( string $path, TrustedFileContext $context ) :void {
		if ( $this->isCacheUsable() && Services::WpFs()->isAccessibleFile( $path ) ) {
			$contextKey = $context->key();
			$this->appendUniqueRecord( self::KNOWN_VALID, $contextKey, [
				'ts'          => Services::Request()->ts(),
				'context_key' => $contextKey,
				'size'        => $this->fileSize( $path ),
				'sha256'      => $this->fileSha256( $path ),
			], [ 'context_key', 'size', 'sha256' ] );
		}
	}

	public function hasCleanMalwareVerdict( string $path, ScanActionVO $action ) :bool {
		$hasVerdict = false;
		if ( $this->isCacheUsable() && Services::WpFs()->isAccessibleFile( $path ) ) {
			$sha256 = $this->fileSha256( $path );
			$size = $this->fileSize( $path );
			$fingerprint = $this->patternFingerprint( $action );
			foreach ( $this->readRecords( $this->shardPath( self::MALWARE_CLEAN, $sha256 ) ) as $record ) {
				if ( $record[ 'sha256' ] === $sha256
					 && $record[ 'size' ] === $size
					 && \hash_equals( $record[ 'pattern_fingerprint' ], $fingerprint ) ) {
					$hasVerdict = true;
					break;
				}
			}
		}
		return $hasVerdict;
	}

	public function recordCleanMalwareVerdict( string $path, ScanActionVO $action ) :void {
		if ( $this->isCacheUsable() && Services::WpFs()->isAccessibleFile( $path ) ) {
			$sha256 = $this->fileSha256( $path );
			$this->appendUniqueRecord( self::MALWARE_CLEAN, $sha256, [
				'ts'                  => Services::Request()->ts(),
				'sha256'              => $sha256,
				'size'                => $this->fileSize( $path ),
				'pattern_fingerprint' => $this->patternFingerprint( $action ),
			], [ 'sha256', 'size', 'pattern_fingerprint' ] );
		}
	}

	public function cleanStaleHashesOlderThan( int $ts ) :void {
		$root = $this->cacheRoot();
		if ( $root === '' || !\is_dir( $root ) ) {
			return;
		}

		foreach ( [ self::KNOWN_VALID, self::MALWARE_CLEAN ] as $type ) {
			$dir = \path_join( $root, $type );
			if ( !\is_dir( $dir ) ) {
				continue;
			}
			foreach ( new \DirectoryIterator( $dir ) as $file ) {
				if ( $file->isFile() && $file->getExtension() === 'jsonl' ) {
					$this->rewriteFreshRecords( $file->getPathname(), $ts );
				}
			}
		}
	}

	private function detectCurrentContext( string $path ) :?TrustedFileContext {
		try {
			$scanCon = self::con()->comps->scans->AFS();
			if ( $scanCon->isEnabled() && Services::CoreFileHashes()->isCoreFile( $path ) ) {
				return new TrustedFileContext(
					'core',
					'core',
					Services::WpGeneral()->getVersion(),
					$this->relativeToAbsPath( $path )
				);
			}

			if ( $scanCon->isScanEnabledPlugins() ) {
				$pluginFiles = new PluginFiles();
				$asset = $pluginFiles->findPluginFromFile( $path );
				if ( !empty( $asset ) ) {
					return new TrustedFileContext(
						'plugin',
						(string)$asset->unique_id,
						(string)$asset->Version,
						$pluginFiles->getRelativeFilePathFromItsInstallDir( $path )
					);
				}
			}

			if ( $scanCon->isScanEnabledThemes() ) {
				$themeFiles = new ThemeFiles();
				$asset = $themeFiles->findThemeFromFile( $path );
				if ( !empty( $asset ) ) {
					return new TrustedFileContext(
						'theme',
						(string)$asset->unique_id,
						(string)$asset->Version,
						$themeFiles->getRelativeFilePathFromItsInstallDir( $path )
					);
				}
			}
		}
		catch ( \Throwable $e ) {
			return null;
		}
		return null;
	}

	private function isAccessibleSupportedFile( string $path, ScanActionVO $action ) :bool {
		$ext = \strtolower( (string)\pathinfo( $path, \PATHINFO_EXTENSION ) );
		return $ext !== ''
			   && \is_array( $action->file_exts )
			   && \in_array( $ext, $action->file_exts, true )
			   && Services::WpFs()->isAccessibleFile( $path );
	}

	private function isCacheUsable() :bool {
		return $this->cacheRoot() !== '';
	}

	private function cacheRoot() :string {
		try {
			if ( !self::con()->opts->optIs( 'optimise_scan_speed', 'Y' )
				 || !self::con()->cache_dir_handler->exists() ) {
				return '';
			}
			$dir = self::con()->cache_dir_handler->buildSubDir( self::CACHE_DIR );
		}
		catch ( \Throwable $e ) {
			$dir = '';
		}
		return $dir !== '' && \is_dir( $dir ) && \is_writable( $dir ) ? $dir : '';
	}

	private function shardPath( string $type, string $key ) :string {
		$root = $this->cacheRoot();
		if ( $root === '' || !\preg_match( '#^[a-z0-9\-]+$#i', $type ) ) {
			return '';
		}
		$dir = \path_join( $root, $type );
		if ( !\is_dir( $dir ) && !@\mkdir( $dir, 0777, true ) && !\is_dir( $dir ) ) {
			return '';
		}
		return \path_join( $dir, \substr( $key, 0, 2 ).'.jsonl' );
	}

	/**
	 * @return array<int, array{ts:int, context_key:string, size:int, sha256:string, pattern_fingerprint:string}>
	 */
	private function readRecords( string $path ) :array {
		if ( $path === '' || !\is_readable( $path ) ) {
			return [];
		}

		$records = [];
		foreach ( \file( $path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES ) ?: [] as $line ) {
			$record = \json_decode( $line, true );
			if ( \is_array( $record ) ) {
				$normalised = $this->normaliseRecord( $record );
				if ( \is_array( $normalised ) ) {
					$records[] = $normalised;
				}
			}
		}
		return $records;
	}

	/**
	 * @return array{ts:int, context_key:string, size:int, sha256:string, pattern_fingerprint:string}|null
	 */
	private function normaliseRecord( array $record ) :?array {
		$ts = $record[ 'ts' ] ?? null;
		$size = $record[ 'size' ] ?? null;
		$sha256 = $record[ 'sha256' ] ?? null;
		if ( !\is_numeric( $ts ) || !\is_numeric( $size ) || !\is_string( $sha256 )
			 || !\preg_match( '#^[a-f0-9]{64}$#', $sha256 ) ) {
			return null;
		}
		return [
			'ts'                  => (int)$ts,
			'context_key'         => \is_string( $record[ 'context_key' ] ?? null ) ? $record[ 'context_key' ] : '',
			'size'                => (int)$size,
			'sha256'              => $sha256,
			'pattern_fingerprint' => \is_string( $record[ 'pattern_fingerprint' ] ?? null ) ? $record[ 'pattern_fingerprint' ] : '',
		];
	}

	private function appendUniqueRecord( string $type, string $shardKey, array $record, array $uniqueKeys ) :void {
		$path = $this->shardPath( $type, $shardKey );
		if ( $path === '' ) {
			return;
		}

		foreach ( $this->readRecords( $path ) as $existing ) {
			$matches = true;
			foreach ( $uniqueKeys as $key ) {
				if ( ( $existing[ $key ] ?? null ) !== ( $record[ $key ] ?? null ) ) {
					$matches = false;
					break;
				}
			}
			if ( $matches ) {
				return;
			}
		}

		\file_put_contents(
			$path,
			(string)\wp_json_encode( $record )."\n",
			\FILE_APPEND | \LOCK_EX
		);
	}

	private function rewriteFreshRecords( string $path, int $ts ) :void {
		$records = \array_filter(
			$this->readRecords( $path ),
			fn( array $record ) :bool => $record[ 'ts' ] > $ts
		);
		$tmp = $path.'.tmp';
		$lines = \array_map(
			static fn( array $record ) :string => (string)\wp_json_encode( $record ),
			$records
		);
		if ( \file_put_contents( $tmp, \implode( "\n", $lines ).( empty( $lines ) ? '' : "\n" ), \LOCK_EX ) !== false ) {
			@\rename( $tmp, $path );
		}
		else {
			@\unlink( $tmp );
		}
	}

	private function fileSize( string $path ) :int {
		$size = @\filesize( $path );
		return \is_int( $size ) ? $size : -1;
	}

	private function fileSha256( string $path ) :string {
		$hash = @\hash_file( 'sha256', $path );
		return \is_string( $hash ) ? $hash : '';
	}

	private function patternFingerprint( ScanActionVO $action ) :string {
		return \hash( 'sha256', (string)\wp_json_encode( [
			'raw'       => $action->patterns_raw,
			'iraw'      => $action->patterns_iraw,
			'regex'     => $action->patterns_regex,
			'functions' => $action->patterns_functions,
			'keywords'  => $action->patterns_keywords,
		] ) );
	}

	private function relativeToAbsPath( string $path ) :string {
		return \str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $path ) );
	}
}
