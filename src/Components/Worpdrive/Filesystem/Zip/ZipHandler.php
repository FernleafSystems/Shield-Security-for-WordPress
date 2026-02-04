<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\Zip;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\ZipCreate\Zipper;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility\{
	DeletePreExistingFilesForType,
	FileNameFor
};
use FernleafSystems\Wordpress\Services\{
	Services,
	Utilities\PasswordGenerator
};

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
			( new Zipper( $this->dir, $this->paths, $this->targetZip() ) )->create();
		}
		catch ( \Exception $e ) {
			Services::WpFs()->deleteFile( $this->targetZip() );
			throw $e;
		}
		return [
			'href' => $this->zipURL(),
		];
	}

	private function targetZip() :string {
		if ( empty( $this->targetZIP ) ) {
			( new DeletePreExistingFilesForType() )->delete( $this->workingDir(), 'files_zip' );
			$this->targetZIP = path_join( $this->workingDir(), PasswordGenerator::Uniqid( 4 ).'_'.FileNameFor::For( 'files_zip' ) );
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