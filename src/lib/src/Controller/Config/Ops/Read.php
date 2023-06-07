<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Ops;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

class Read {

	/**
	 * @throws \Exception
	 */
	public static function FromFile( string $path ) :array {
		$FS = Services::WpFs();
		foreach ( [ 'json', 'php' ] as $ext ) {
			$cfgFile = Paths::AddExt( $path, $ext );
			if ( $FS->isAccessibleFile( $cfgFile ) ) {
				$content = $FS->getFileContent( $cfgFile );
				if ( empty( $content ) ) {
					$content = $FS->getFileContentUsingInclude( $cfgFile );
				}
				return self::FromString( $content );
			}
		}
		throw new \LogicException( 'No config file present for slug: '.basename( $path ) );
	}

	/**
	 * @throws \Exception
	 */
	public static function FromString( string $def ) :array {
		$spec = [];
		$def = \trim( $def );

		if ( !empty( $def ) ) {
			$spec = \json_decode( $def, true );
		}
		if ( empty( $spec ) || !\is_array( $spec ) ) {
			throw new \Exception( 'Could not parse the definition file.' );
		}

		return $spec;
	}
}