<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class PackageRuntimeLogScanner {

	private const GLOBAL_FATAL_PATTERN = '/\b(fatal error|parse error|uncaught|segmentation fault)\b/i';
	private const SHIELD_MARKER_PATTERN = '#(wp-simple-firewall|icwp-wpsf\.php|wp-content/plugins/wp-simple-firewall|FernleafSystems\\\\Wordpress\\\\Plugin\\\\Shield|FernleafSystems/Wordpress/Plugin/Shield|vendor_prefixed|AptowebDeps)#i';
	private const SHIELD_ERROR_PATTERN = '/\b(warning|notice|deprecated|deprecation|error|exception|fatal|failed|failure)\b/i';

	/**
	 * @param string[] $paths
	 * @return array<int,array{file:string,line:int,reason:string,message:string}>
	 */
	public function scanFiles( array $paths ) :array {
		$findings = [];

		foreach ( $paths as $path ) {
			if ( !\is_file( $path ) ) {
				continue;
			}

			$lines = \preg_split( '/\R/', (string)\file_get_contents( $path ) ) ?: [];
			foreach ( $lines as $index => $line ) {
				$trimmed = \trim( $line );
				if ( $trimmed === '' ) {
					continue;
				}

				$reason = $this->classifyLine( $trimmed );
				if ( $reason === null ) {
					continue;
				}

				$findings[] = [
					'file'    => $path,
					'line'    => $index + 1,
					'reason'  => $reason,
					'message' => $trimmed,
				];
			}
		}

		return $findings;
	}

	/**
	 * @param array<int,array{file:string,line:int,reason:string,message:string}> $findings
	 */
	public function hasGlobalFatalFinding( array $findings ) :bool {
		foreach ( $findings as $finding ) {
			if ( ( $finding[ 'reason' ] ?? '' ) === 'global-fatal' ) {
				return true;
			}
		}
		return false;
	}

	private function classifyLine( string $line ) :?string {
		if ( \preg_match( self::GLOBAL_FATAL_PATTERN, $line ) === 1 ) {
			return 'global-fatal';
		}

		if ( \preg_match( self::SHIELD_MARKER_PATTERN, $line ) === 1
			 && \preg_match( self::SHIELD_ERROR_PATTERN, $line ) === 1 ) {
			return 'shield-scoped-error';
		}

		return null;
	}
}
