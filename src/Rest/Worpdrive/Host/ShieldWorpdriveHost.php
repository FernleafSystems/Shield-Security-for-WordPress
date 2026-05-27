<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\Worpdrive\Host;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\PasswordGenerator;
use FernleafSystems\WorpdriveClient\Host\{
	WorpdriveDatabase,
	WorpdriveFilesystem,
	WorpdriveHost,
	WorpdriveWordPress
};

class ShieldWorpdriveHost implements WorpdriveHost {

	use PluginControllerConsumer;

	private ?WorpdriveFilesystem $filesystem = null;

	private ?WorpdriveDatabase $database = null;

	private ?WorpdriveWordPress $wordpress = null;

	public function rootDir() :string {
		return self::con()->getRootDir();
	}

	public function baseArchivePath( string $uuid ) :string {
		return \sprintf( '%s/archive-%s/', 'tmp', $uuid );
	}

	public function pluginVersion() :string {
		return self::con()->cfg->version();
	}

	public function pluginUrlForItem( string $relativePath ) :string {
		return remove_query_arg( 'ver', self::con()->urls->forPluginItem( $relativePath ) );
	}

	public function cacheDir() :string {
		return self::con()->cache_dir_handler->dir();
	}

	public function uniqueId( int $length ) :string {
		return PasswordGenerator::Uniqid( $length );
	}

	public function filesystem() :WorpdriveFilesystem {
		return $this->filesystem ??= new ShieldWorpdriveFilesystem();
	}

	public function database() :WorpdriveDatabase {
		return $this->database ??= new ShieldWorpdriveDatabase();
	}

	public function wordpress() :WorpdriveWordPress {
		return $this->wordpress ??= new ShieldWorpdriveWordPress();
	}
}
