<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseHandler {

	use PluginControllerConsumer;

	protected string $uuid;

	protected int $stopAtTS;

	private ?string $pathWorkingDir = null;

	public function __construct( string $uuid, int $stopAtTS ) {
		$this->uuid = $uuid;
		$this->stopAtTS = $stopAtTS;
	}

	/**
	 * @throws \Exception
	 */
	abstract public function run() :array;

	protected function workingDir() :string {
		if ( empty( $this->pathWorkingDir ) ) {
			$this->pathWorkingDir = trailingslashit( wp_normalize_path(
				path_join( self::con()->getRootDir(), $this->baseArchivePath() )
			) );
			Services::WpFs()->mkdir( $this->pathWorkingDir );
		}
		return $this->pathWorkingDir;
	}

	protected function baseArchivePath() :string {
		return sprintf( '%s/archive-%s/', 'tmp', $this->uuid );
	}
}