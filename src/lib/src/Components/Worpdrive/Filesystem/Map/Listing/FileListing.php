<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Map\Listing;

interface FileListing {

	public function addRaw( string $path, string $hash, string $hashAlt = '', ?int $mtime = null, ?int $size = null ) :void;
}