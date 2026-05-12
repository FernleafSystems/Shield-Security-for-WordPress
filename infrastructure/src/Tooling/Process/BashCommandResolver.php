<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Process;

use Symfony\Component\Filesystem\Path;

class BashCommandResolver {

	public function resolve() :string {
		if ( \PHP_OS_FAMILY !== 'Windows' ) {
			return 'bash';
		}

		$override = $this->normalizeOptionalPath( (string)( \getenv( 'SHIELD_BASH_BINARY' ) ?: '' ) );
		if ( $override !== '' && \is_file( $override ) ) {
			return $override;
		}

		foreach ( [ 'ProgramW6432', 'ProgramFiles', 'ProgramFiles(x86)' ] as $envVar ) {
			$baseDir = $this->normalizeOptionalPath( (string)( \getenv( $envVar ) ?: '' ) );
			if ( $baseDir === '' ) {
				continue;
			}

			$candidate = Path::join( $baseDir, 'Git', 'bin', 'bash.exe' );
			if ( \is_file( $candidate ) ) {
				return $candidate;
			}
		}

		return 'bash';
	}

	private function normalizeOptionalPath( string $value ) :string {
		$trimmed = \trim( $value, " \t\n\r\0\x0B\"'" );
		return $trimmed === '' ? '' : Path::normalize( $trimmed );
	}
}
