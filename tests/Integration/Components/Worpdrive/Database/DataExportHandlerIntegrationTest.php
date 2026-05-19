<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\Worpdrive\Database;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data\DataExportHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;
use FernleafSystems\Wordpress\Services\Services;

class DataExportHandlerIntegrationTest extends ShieldWordPressTestCase {

	private string $archiveDir = '';

	private string $uuid = '';

	public function set_up() :void {
		parent::set_up();

		$this->uuid = 'wdhandlerfailure'.\strtolower( \bin2hex( \random_bytes( 4 ) ) );
		$this->archiveDir = $this->archiveDirForUuid( $this->uuid );
		$this->deleteArchiveDir();
	}

	public function tear_down() :void {
		$this->deleteArchiveDir();

		parent::tear_down();
	}

	public function testInvalidExportMapDoesNotCreateSuccessfulDbZip() :void {
		global $wpdb;

		$unknownTable = $wpdb->prefix.'worpdrive_unknown_'.$this->uuid;
		$result = ( new DataExportHandler(
			[
				$unknownTable => [
					'offset'        => 0,
					'page'          => 0,
					'completed_at'  => 0,
					'exported_rows' => 0,
					'max_page_rows' => 100,
					'chunk_size'    => 10,
				],
			],
			$this->uuid,
			\time() + 120
		) )->run();

		$this->assertSame( '', $result[ 'href' ] );
		$this->assertSame( [], $result[ 'table_export_map' ] );
		$trackerFile = path_join( $this->archiveDir, 'db_tracker.json' );
		$this->assertFileExists( $trackerFile );
		$trackerContent = \file_get_contents( $trackerFile );
		$this->assertIsString( $trackerContent );
		$this->assertSame( [], \json_decode( $trackerContent, true ) );

		$zipFiles = \glob( path_join( $this->archiveDir, '*zipped_db_exp.archive' ) );
		$this->assertIsArray( $zipFiles );
		$this->assertSame( [], $zipFiles );
		$this->assertDirectoryDoesNotExist( path_join( $this->archiveDir, 'db_dump' ) );
	}

	private function archiveDirForUuid( string $uuid ) :string {
		return \trailingslashit( \wp_normalize_path(
			\path_join( self::con()->getRootDir(), \sprintf( 'tmp/archive-%s/', $uuid ) )
		) );
	}

	private function deleteArchiveDir() :void {
		if ( !empty( $this->archiveDir ) && \is_dir( $this->archiveDir ) ) {
			Services::WpFs()->deleteDir( $this->archiveDir );
		}
	}
}
