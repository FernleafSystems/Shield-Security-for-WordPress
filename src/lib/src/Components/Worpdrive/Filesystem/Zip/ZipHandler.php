<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Zip;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\ZipCreate\Zipper;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility\FileNameFor;
use FernleafSystems\Wordpress\Services\Services;

class ZipHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\BaseFsHandler {

	private array $paths;

	private ?string $targetZIP = null;

	/**
	 * @throws \Exception
	 */
	public function __construct( array $filePaths, string $dir, string $uuid, int $stopAtTS ) {
		parent::__construct( $dir, $uuid, $stopAtTS );
		$this->paths = $filePaths;
	}

	/**
	 * @throws \Exception
	 */
	public function run() :array {
		try {
			$this->createZip();
		}
		catch ( \Exception $e ) {
			Services::WpFs()->deleteFile( $this->targetZip() );
			throw $e;
		}
		return [
			'href' => $this->zipURL(),
		];
	}

	/**
	 * @throws \Exception
	 */
	private function createZip() :void {
		( new Zipper(
			$this->dir,
			$this->paths,
			$this->targetZip()
		) )->create();
	}

	private function targetZip() :string {
		if ( empty( $this->targetZIP ) ) {
			$this->targetZIP = path_join( $this->workingDir(), FileNameFor::For( 'files_zip' ) );
			if ( \is_file( $this->targetZIP ) ) {
				Services::WpFs()->deleteFile( $this->targetZIP );
			}
		}
		return $this->targetZIP;
	}

	private function zipURL() :string {
		return remove_query_arg(
			'ver',
			self::con()->urls->forPluginItem(
				sprintf( '%s/%s', untrailingslashit( $this->baseArchivePath() ), \basename( $this->targetZip() ) )
			)
		);
	}
}