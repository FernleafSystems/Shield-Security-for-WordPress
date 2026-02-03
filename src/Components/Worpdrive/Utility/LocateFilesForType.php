<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility;

use FernleafSystems\Wordpress\Services\Services;

class LocateFilesForType {

	public function find( string $workingDir, string $type ) :array {
		return \array_filter(
			Services::WpFs()->enumItemsInDir( $workingDir ),
			fn( $path ) => \str_contains( \basename( $path ), FileNameFor::For( $type ) ) && \is_file( $path )
		);
	}
}