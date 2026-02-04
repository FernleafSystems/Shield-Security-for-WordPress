<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Schema;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
	Config,
	Exporter,
	TableEnum
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Filesystem\ZipCreate\Zipper;
use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Utility\FileNameFor;
use FernleafSystems\Wordpress\Services\Services;

class SchemaHandler extends \FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\BaseDbHandler {

	private string $method;

	private array $tables;

	private ?string $targetZIP = null;

	/**
	 * @throws \Exception
	 */
	public function __construct( string $method, string $uuid, int $stopAtTS ) {
		parent::__construct( $uuid, $stopAtTS );
		$this->method = $method;
	}

	/**
	 * @throws \Exception
	 */
	public function run() :array {
		$data = [
			'tables'    => $this->tables(),
			'db_prefix' => Services::WpDb()->getPrefix(),
		];
		if ( $this->method === 'zip' ) {
			$this->dumpSchemaToZip();
			$data[ 'schema_href' ] = $this->zipURL();
		}
		else { //'direct'
			$data[ 'schema_dump' ] = $this->dumpSchema();
		}
		return $data;
	}

	/**
	 * @throws \Exception
	 */
	private function dumpSchema() :array {
		$cfg = ( new Config() )->applyDumpSchemaOptions();
		$cfg->set( 'tables', \array_keys( $this->tables() ) );
		return ( new Exporter( $cfg ) )->export();
	}

	/**
	 * @throws \Exception
	 */
	private function dumpSchemaToZip() :void {
		$toFile = Services::WpFs()->putFileContent(
			$this->schemaDumpFile(),
			\implode( "\n", $this->dumpSchema()[ 'content' ] )
		);
		if ( !$toFile ) {
			throw new \Exception( 'Failed to create schema dump file.' );
		}
		try {
			$this->createZip();
		}
		catch ( \Exception $e ) {
		}
	}

	private function schemaDumpFile() :string {
		return path_join( $this->workingDir(), 'db_schema_dump.sql' );
	}

	/**
	 * @throws \Exception
	 */
	private function createZip() :void {
		$items = [ $this->schemaDumpFile() ];
		( new Zipper(
			$this->workingDir(),
			\array_map( '\basename', $items ),
			$this->targetZip()
		) )->create();
		Services::WpFs()->deleteFile( $this->schemaDumpFile() );
	}

	private function targetZip() :string {
		if ( empty( $this->targetZIP ) ) {
			$this->targetZIP = path_join( $this->workingDir(), FileNameFor::For( 'db_schema_zip' ) );
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

	/**
	 * @throws \Exception
	 */
	private function tables() :array {
		return $this->tables ??= ( new TableEnum() )->enum();
	}
}