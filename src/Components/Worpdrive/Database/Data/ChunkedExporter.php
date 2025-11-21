<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
	Config,
	Exporter,
	Table\TableDataExport,
	Table\TableHelper
};

class ChunkedExporter {

	/**
	 * @var resource
	 */
	private $dumpFile;

	private string $table;

	private int $startingOffset;

	private int $maxPageRows;

	private int $chunkSize;

	/**
	 * @throws \Exception
	 */
	public function __construct( $dumpFile, string $table, int $startingOffset, int $maxPageRows = 1000, int $chunkSize = 50 ) {
		if ( !\is_resource( $dumpFile ) ) {
			throw new \Exception( 'Dump file is not a valid resource' );
		}
		$this->dumpFile = $dumpFile;
		$this->table = $table;
		$this->maxPageRows = $maxPageRows;
		$this->startingOffset = $startingOffset;
		$this->chunkSize = $chunkSize;
	}

	/**
	 * @throws \Exception
	 */
	public function run() :array {
		$cfg = ( new Config() )->applyDumpDataOptions();
		$cfg->set( 'host', \defined( 'DB_HOST' ) ? DB_HOST : '' );
		$cfg->set( 'database', \defined( 'DB_NAME' ) ? DB_NAME : '' );
		$cfg->set( 'tables', [ $this->table ] );
		$exporter = new Exporter( $cfg );

		$tableDataExp = new TableDataExport( $this->table, $cfg );
		$primaryOrderColumn = ( new TableHelper( $this->table ) )->getAppropriatePrimaryKeyForOrdering();
		if ( empty( $primaryOrderColumn ) ) {
			// when no obvious PK is available, offset queries are slower; reduce page size to lower timeout risk
			$orderBy = '';
			$this->maxPageRows = (int)\max( 1, \round( 2*$this->maxPageRows/3 ) );
		}
		else {
			$orderBy = sprintf( 'ORDER BY `%s` ASC', $primaryOrderColumn );
		}

		$pageExportComplete = false;
		$offset = $this->startingOffset;
		$isFirstLoop = true;
		$lastProcessedPrimaryKey = $this->startingOffset; // keep track of the last PK we touched so stalled requests can't reset progress to 0
		$currentOffsetForResponse = $this->startingOffset;
		$tableExportComplete = false;
		do {
			if ( $isFirstLoop ) {
				$exporter->buildHeader()
						 ->buildPreDataExport()
						 ->buildTableDataStructureStart( $this->table );
				$this->writeDump( $exporter->getContent( true ) );
				$isFirstLoop = false;
			}

			// Default behaviour is to just get the next chunk of data.
			if ( empty( $primaryOrderColumn ) ) {
				$tableDataExp->buildDataRows( [], $orderBy, $this->chunkSize, $this->chunkSize*$offset++ );
				$currentOffsetForResponse = $offset;
			}
			else {
				// if we can order by primary key, then we don't need offset; we reuse the last PK to avoid skipping/duplication
				if ( !empty( $tableDataExp->getMostRecentRow() ) ) {
					$offset = (int)\max(
						$offset + 1,
						$tableDataExp->getMostRecentRow()[ $primaryOrderColumn ]
					);
				}

				$tableDataExp->buildDataRows(
					[
						sprintf( '`%s` %s %s', $primaryOrderColumn, $offset == 0 ? '>=' : '>', $offset )
					],
					$orderBy,
					$this->chunkSize
				);

				if ( !empty( $tableDataExp->getMostRecentRow() ) ) {
					$lastProcessedPrimaryKey = (int)$tableDataExp->getMostRecentRow()[ $primaryOrderColumn ];
				}
				$currentOffsetForResponse = !empty( $tableDataExp->getMostRecentRow() ) ? $lastProcessedPrimaryKey : $offset;
			}

			if ( $tableDataExp->getPreviousDataRowsCount() === 0 || $tableDataExp->getTotalDataRowsCount() >= $this->maxPageRows ) {
				$pageExportComplete = true;
				$tableExportComplete = $tableDataExp->getPreviousDataRowsCount() === 0;
				$this->writeDump(
					$exporter->buildTableDataStructureEnd( $this->table )
							 ->buildFooter()
							 ->getContent( true )
				);
			}
			else {
				$this->writeDump( $tableDataExp->getContent( true ) );
			}
		} while ( !$pageExportComplete && $exporter->getTotalDataRowsCount() < $this->maxPageRows );

		return [
			'table_export_complete' => $tableExportComplete,
			// current_offset feeds the caller's map; keep it advancing even when PK-based paging is used
			'current_offset'        => $currentOffsetForResponse,
			'exported_rows'         => $tableDataExp->getTotalDataRowsCount(),
		];
	}

	private function writeDump( array $raw ) :void {
		\fwrite( $this->dumpFile, \implode( "\n", $raw ) );
	}
}
