<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

class IsFileContentExcluded {

	public function check( string $path ) :bool {
		$FS = Services::WpFs();
		$excluded = false;

		$path = wp_normalize_path( $path );
		$ext = \strtolower( Paths::Ext( $path ) );
		if ( $FS->isAccessibleFile( $path ) && \in_array( $ext, [ 'mo', 'ico' ] ) ) {
			$content = $FS->getFileContent( $path );
			if ( !empty( $content ) ) {
				switch ( $ext ) {
					case 'mo':
					case 'ico':
						$excluded = \strpos( $content, '<?php' ) === false;
						break;
					default:
						break;
				}
			}
		}
		return $excluded;
	}
}