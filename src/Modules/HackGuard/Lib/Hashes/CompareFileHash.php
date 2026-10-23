<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;

class CompareFileHash {

	/**
	 * @param list<string> $expectedHashes
	 * @param string       $relativePath Path relative to the asset root, which selects any AssetFileRules rule.
	 */
	public function isAnyEqual( string $path, array $expectedHashes, string $relativePath = '' ) :bool {
		if ( empty( $expectedHashes ) ) {
			return false;
		}
		if ( !Services::WpFs()->isFile( $path ) ) {
			throw new \InvalidArgumentException( 'File does not exist on disk to compare' );
		}

		$byAlgorithm = [];
		foreach ( $expectedHashes as $expected ) {
			$byAlgorithm[ $this->algorithmForHash( $expected ) ][] = $expected;
		}
		$candidates = null;
		foreach ( $byAlgorithm as $algorithm => $hashes ) {
			$rawHash = $this->hashFile( $algorithm, $path );
			if ( !\is_string( $rawHash ) ) {
				continue;
			}
			foreach ( $hashes as $expected ) {
				if ( \hash_equals( $rawHash, $expected ) ) {
					return true;
				}
			}

			if ( $candidates === null ) {
				$content = $this->readFile( $path );
				$candidates = \is_string( $content ) ? $this->alternativeContents( $content, $relativePath ) : [];
			}
			foreach ( $candidates as $candidate ) {
				$hash = \hash( $algorithm, $candidate );
				foreach ( $hashes as $expected ) {
					if ( \hash_equals( $hash, $expected ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * @param string $relativePath Path relative to the asset root, which selects any AssetFileRules rule.
	 */
	public function isEqual( string $path, string $expectedHash, string $relativePath = '' ) :bool {
		return $this->isAnyEqual( $path, [ $expectedHash ], $relativePath );
	}

	/**
	 * The content's other line-ending variants, then its file rule applied to the content and to each variant.
	 * @return list<string>
	 */
	private function alternativeContents( string $content, string $relativePath ) :array {
		$lineEndings = new ConvertLineEndings();
		$variants = \array_values( \array_unique( [
			$content,
			$lineEndings->dosToLinux( $content ),
			$lineEndings->linuxToDos( $content ),
		] ) );
		$alternatives = \array_slice( $variants, 1 );
		if ( $relativePath !== '' ) {
			$rules = new AssetFileRules();
			foreach ( $variants as $variant ) {
				$ruled = $rules->apply( $relativePath, $variant );
				if ( $ruled !== null ) {
					$alternatives[] = $ruled;
				}
			}
		}
		return \array_values( \array_unique( $alternatives ) );
	}

	private function algorithmForHash( string $hash ) :string {
		switch ( \strlen( $hash ) ) {
			case 32:
				return 'md5';
			case 40:
				return 'sha1';
			case 64:
				return 'sha256';
			default:
				throw new \InvalidArgumentException( 'Unsupported file hash length.' );
		}
	}

	/**
	 * @return string|false
	 */
	protected function hashFile( string $algorithm, string $path ) {
		return @\hash_file( $algorithm, $path );
	}

	/**
	 * @return string|false|null
	 */
	protected function readFile( string $path ) {
		return Services::WpFs()->getFileContent( $path );
	}
}
