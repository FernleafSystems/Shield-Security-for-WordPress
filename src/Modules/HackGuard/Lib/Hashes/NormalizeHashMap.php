<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

class NormalizeHashMap {

	/**
	 * @param mixed $hashes
	 * @return array<string,list<string>>
	 */
	public function run( $hashes ) :array {
		if ( !\is_array( $hashes ) ) {
			return [];
		}

		$normalised = [];
		foreach ( $hashes as $path => $pathHashes ) {
			$path = $this->normalisePath( $path );
			if ( $path === null ) {
				continue;
			}

			if ( \is_string( $pathHashes ) ) {
				$pathHashes = [ $pathHashes ];
			}
			if ( !\is_array( $pathHashes ) ) {
				continue;
			}

			$validHashes = [];
			foreach ( $pathHashes as $hash ) {
				if ( \is_string( $hash ) && $this->isSupportedHash( $hash ) ) {
					$validHashes[] = $hash;
				}
			}
			if ( !empty( $validHashes ) ) {
				$normalised[ $path ] = $validHashes;
			}
		}

		return $normalised;
	}

	/**
	 * @param mixed $path
	 */
	private function normalisePath( $path ) :?string {
		if ( !\is_string( $path ) || $path === '' || \strpos( $path, "\0" ) !== false ) {
			return null;
		}

		$path = \str_replace( '\\', '/', $path );
		if ( \preg_match( '#^(?:[a-z]:)?/#i', $path ) === 1 ) {
			return null;
		}
		foreach ( \explode( '/', $path ) as $part ) {
			if ( $part === '.' || $part === '..' ) {
				return null;
			}
		}
		return $path;
	}

	private function isSupportedHash( string $hash ) :bool {
		return \preg_match( '#^(?:[a-f0-9]{32}|[a-f0-9]{40}|[a-f0-9]{64})$#D', $hash ) === 1;
	}
}
