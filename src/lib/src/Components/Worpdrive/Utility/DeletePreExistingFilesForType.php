<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility;

use FernleafSystems\Wordpress\Services\Services;

class DeletePreExistingFilesForType {

	public function delete( string $workingDir, string $type ) :void {
		\array_map(
			fn( string $path ) => Services::WpFs()->deleteFile( $path ),
			( new LocateFilesForType() )->find( $workingDir, $type )
		);
	}
}