<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility;

class Base64PayloadDecoder {

	public function decodeOptionalList( array $encodedValues ) :array {
		$decodedValues = [];
		foreach ( $encodedValues as $encoded ) {
			$decoded = $this->decode( $encoded );
			if ( $decoded !== false && $decoded !== '' ) {
				$decodedValues[] = $decoded;
			}
		}
		return $decodedValues;
	}

	public function decodeRequiredList( array $encodedValues ) :array {
		$decodedValues = [];
		foreach ( $encodedValues as $encoded ) {
			$decoded = $this->decode( $encoded );
			if ( $decoded === false || $decoded === '' ) {
				throw new \InvalidArgumentException( 'Invalid encoded WorpDrive payload.' );
			}
			$decodedValues[] = $decoded;
		}
		return $decodedValues;
	}

	private function decode( $encoded ) {
		if ( !\is_string( $encoded ) ) {
			return false;
		}
		$decoded = \base64_decode( $encoded, true );
		return $decoded !== false && \base64_encode( $decoded ) === $encoded ? $decoded : false;
	}
}
