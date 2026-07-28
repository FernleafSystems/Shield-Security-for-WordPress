<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops;

final class PrepareWpDiffInput {

	/**
	 * @return array{original:string,current:string}
	 */
	public function run( string $original, string $current ) :array {
		if ( \preg_match( '//u', $original ) === 1 && \preg_match( '//u', $current ) === 1 ) {
			return [
				'original' => $original,
				'current'  => $current,
			];
		}

		return [
			'original' => $this->encodeBytes( $original ),
			'current'  => $this->encodeBytes( $current ),
		];
	}

	private function encodeBytes( string $value ) :string {
		$encoded = '';
		$length = \strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$byte = $value[ $i ];
			$ordinal = \ord( $byte );

			if ( $byte === "\t" || $byte === "\n" || $byte === "\r" ) {
				$encoded .= $byte;
			}
			elseif ( $ordinal >= 0x20 && $ordinal <= 0x7E ) {
				$encoded .= $byte === '\\' ? '\\\\' : $byte;
			}
			else {
				$encoded .= \sprintf( '\\x%02X', $ordinal );
			}
		}

		return $encoded;
	}
}
