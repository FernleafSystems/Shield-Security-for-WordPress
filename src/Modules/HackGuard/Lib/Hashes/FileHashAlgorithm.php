<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

class FileHashAlgorithm {

	private const DIGEST_LENGTHS = [
		'md5'    => 32,
		'sha1'   => 40,
		'sha256' => 64,
	];

	/** @param mixed $algorithm */
	public static function validate( $algorithm ) :string {
		if ( !\is_string( $algorithm ) || !\array_key_exists( $algorithm, self::DIGEST_LENGTHS ) ) {
			throw new \InvalidArgumentException( 'Unsupported snapshot hash algorithm.' );
		}
		return $algorithm;
	}

	public static function digestLength( string $algorithm ) :int {
		return self::DIGEST_LENGTHS[ self::validate( $algorithm ) ];
	}
}
