<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use Symfony\Component\Filesystem\Path;

trait TempPathJoinTrait {

	protected function tempPath( string ...$parts ) :string {
		/** @var string $tempDir */
		$tempDir = $this->tempDir;
		return Path::join( $tempDir, ...$parts );
	}
}
