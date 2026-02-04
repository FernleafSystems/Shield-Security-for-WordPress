<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Exc\TimeLimitReachedException;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\ZipCreate\Zipper;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility\{
	DeletePreExistingFilesForType,
	FileNameFor
};
use FernleafSystems\Wordpress\Services\{
	Services,
	Utilities\PasswordGenerator
};

class DataExportHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\BaseDbHandler {

	private array $tableExportMap;

	private ?string $dumpDir = null;

	private ?string $targetZIP = null;

	/**
	 * @throws \Exception
	 */
	public function __construct( array $tableExportMap, string $uuid, int $stopAtTS ) {
		parent::__construct( $uuid, $stopAtTS );
		$this->tableExportMap = $tableExportMap;
	}

	/**
	 * @throws \Exception
	 */
	public function run() :array {
		$map = new ExportMap( $this->tableExportMap );
		try {
			// Allow 2s for ZIP.
			( new PagedExporter( $this->dumpDir(), $map, $this->stopAtTS - 2 ) )->run();
			$exportSuccess = true;
		}
		catch ( TimeLimitReachedException $e ) {
			$exportSuccess = true;
		}
		catch ( \Exception $e ) {
			$exportSuccess = false;
		}
		finally {
			Services::WpFs()
					->putFileContent( path_join( $this->workingDir(), 'db_tracker.json' ), wp_json_encode( $map->status() ) );
		}

		if ( $exportSuccess ) {
			$this->createZip();
		}

		return [
			'href'             => $exportSuccess ? $this->zipURL() : '',
			'table_export_map' => $map->status(),
		];
	}

	/**
	 * @throws \Exception
	 */
	private function createZip() :void {
		$items = Services::WpFs()->enumItemsInDir( $this->dumpDir() );
		\natsort( $items );
		( new Zipper(
			$this->dumpDir(),
			\array_map( '\basename', $items ),
			$this->targetZip()
		) )->create();
		Services::WpFs()->deleteDir( $this->dumpDir );
	}

	private function dumpDir() :string {
		if ( empty( $this->dumpDir ) ) {
			$this->dumpDir = path_join( $this->workingDir(), 'db_dump' );
			if ( \is_dir( $this->dumpDir ) ) {
				Services::WpFs()->deleteDir( $this->dumpDir );
			}
			Services::WpFs()->mkdir( $this->dumpDir );
		}
		return $this->dumpDir;
	}

	private function targetZip() :string {
		if ( empty( $this->targetZIP ) ) {
			( new DeletePreExistingFilesForType() )->delete( $this->workingDir(), 'db_exports_zip' );
			$this->targetZIP = path_join( $this->workingDir(), PasswordGenerator::Uniqid( 4 ).'_'.FileNameFor::For( 'db_exports_zip' ) );
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