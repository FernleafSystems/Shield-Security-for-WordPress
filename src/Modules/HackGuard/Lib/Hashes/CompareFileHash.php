<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;

class CompareFileHash {

	public function isEqual( string $path, string $expectedHash ) :bool {
		if ( !Services::WpFs()->isFile( $path ) ) {
			throw new \InvalidArgumentException( 'File does not exist on disk to compare' );
		}

		$algorithm = $this->algorithmForHash( $expectedHash );
		$fileHash = $this->hashFile( $algorithm, $path );
		if ( !\is_string( $fileHash ) ) {
			return false;
		}
		if ( \hash_equals( $fileHash, $expectedHash ) ) {
			return true;
		}

		$content = $this->readFile( $path );
		if ( !\is_string( $content ) ) {
			return false;
		}

		$lineEndings = new ConvertLineEndings();
		foreach ( [
			$content,
			$lineEndings->dosToLinux( $content ),
			$lineEndings->linuxToDos( $content ),
		] as $candidate ) {
			if ( \hash_equals( \hash( $algorithm, $candidate ), $expectedHash ) ) {
				return true;
			}
		}
		return false;
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
