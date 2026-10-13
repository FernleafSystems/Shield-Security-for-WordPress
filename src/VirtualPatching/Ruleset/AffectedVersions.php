<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset;

/**
 * Whether an installed plugin or theme version falls within a patch's affected version ranges.
 */
class AffectedVersions {

	/**
	 * @param array[] $ranges each with firstAffectedVersion, lastAffectedVersion and optionally firstKnownFixedVersion
	 */
	public function isAffected( string $installedVersion, array $ranges ) :bool {
		if ( \trim( $installedVersion ) === '' ) {
			return false;
		}
		foreach ( $ranges as $range ) {
			$fixed = $range[ 'firstKnownFixedVersion' ] ?? null;
			if ( $this->compare( $installedVersion, (string)$range[ 'firstAffectedVersion' ] ) >= 0
				 && $this->compare( $installedVersion, (string)$range[ 'lastAffectedVersion' ] ) <= 0
				 && ( !\is_string( $fixed ) || $this->compare( $installedVersion, $fixed ) < 0 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * version_compare() treats "1.2" as lower than "1.2.0", so trailing zero segments are removed first.
	 */
	public function compare( string $a, string $b ) :int {
		return \version_compare( $this->normalise( $a ), $this->normalise( $b ) );
	}

	private function normalise( string $version ) :string {
		$version = \trim( $version );
		if ( \preg_match( '/^(\d+(?:\.\d+)*)(.*)$/', $version, $matches ) !== 1 ) {
			return $version;
		}
		$segments = \explode( '.', $matches[ 1 ] );
		while ( \count( $segments ) > 1 && (int)\end( $segments ) === 0 ) {
			\array_pop( $segments );
		}
		return \implode( '.', $segments ).$matches[ 2 ];
	}
}
