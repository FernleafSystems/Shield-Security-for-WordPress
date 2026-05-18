<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class PublicUpgradePackageZipMetadataReader {

	private const REQUIRED_PLUGIN_FILE = 'wp-simple-firewall/icwp-wpsf.php';
	private const PLUGIN_JSON_FILE = 'wp-simple-firewall/plugin.json';

	public function read( string $zipPath ) :PublicUpgradePackageZipMetadata {
		if ( !\is_file( $zipPath ) ) {
			throw new \RuntimeException( 'Package zip does not exist: '.$zipPath );
		}
		if ( !\class_exists( \ZipArchive::class ) ) {
			throw new \RuntimeException( 'The PHP zip extension is required to inspect package metadata.' );
		}

		$zip = new \ZipArchive();
		$result = $zip->open( $zipPath );
		if ( $result !== true ) {
			throw new \RuntimeException( 'Unable to open package zip: '.$zipPath.' (code '.$result.')' );
		}

		try {
			if ( $zip->locateName( self::REQUIRED_PLUGIN_FILE ) === false ) {
				throw new \RuntimeException( 'Package zip is missing '.self::REQUIRED_PLUGIN_FILE.'.' );
			}

			$version = $this->versionFromPluginJson( $zip )
				?? $this->versionFromPluginHeader( (string)$zip->getFromName( self::REQUIRED_PLUGIN_FILE ) );
			if ( $version === null ) {
				throw new \RuntimeException( 'Package zip does not expose a plugin version.' );
			}

			return new PublicUpgradePackageZipMetadata(
				$zipPath,
				$version,
				self::REQUIRED_PLUGIN_FILE
			);
		}
		finally {
			$zip->close();
		}
	}

	private function versionFromPluginJson( \ZipArchive $zip ) :?string {
		if ( $zip->locateName( self::PLUGIN_JSON_FILE ) === false ) {
			return null;
		}

		$decoded = \json_decode( (string)$zip->getFromName( self::PLUGIN_JSON_FILE ), true );
		$version = \is_array( $decoded ) ? ( $decoded[ 'properties' ][ 'version' ] ?? null ) : null;
		return \is_string( $version ) && \trim( $version ) !== '' ? \trim( $version ) : null;
	}

	private function versionFromPluginHeader( string $header ) :?string {
		if ( \preg_match( '/^\s*\*?\s*Version:\s*([^\r\n]+)\s*$/mi', $header, $matches ) !== 1 ) {
			return null;
		}

		$version = \trim( (string)( $matches[ 1 ] ?? '' ) );
		return $version !== '' ? $version : null;
	}
}
