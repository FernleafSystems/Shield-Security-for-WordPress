<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
	Config,
	Exporter,
	SqlIdentifier,
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
		SqlIdentifier::assertSafe( $table, 'Table name' );
		if ( $startingOffset < 0 ) {
			throw new \InvalidArgumentException( 'Starting offset must be zero or greater.' );
		}
		if ( $maxPageRows < 1 ) {
			throw new \InvalidArgumentException( 'Maximum page rows must be one or greater.' );
		}
		if ( $chunkSize < 1 ) {
			throw new \InvalidArgumentException( 'Chunk size must be one or greater.' );
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
		$tableHelper = new TableHelper( $this->table );
		$primaryOrderColumn = $tableHelper->getAppropriatePrimaryKeyForOrdering();
		if ( empty( $primaryOrderColumn ) ) {
			// when no obvious PK is available, offset queries are slower; reduce page size to lower timeout risk
			$orderColumns = $tableHelper->getPrimaryKeyColumns();
			$orderBy = empty( $orderColumns ) ? '' : \sprintf( 'ORDER BY %s', \implode(
				', ',
				\array_map(
					fn( string $column ) => SqlIdentifier::quote( $column, 'Primary key column' ).' ASC',
					$orderColumns
				)
			) );
			$this->maxPageRows = (int)\max( 1, \round( 2*$this->maxPageRows/3 ) );
		}
		else {
			$orderBy = \sprintf( 'ORDER BY %s ASC', SqlIdentifier::quote( $primaryOrderColumn, 'Primary key column' ) );
		}

		$pageExportComplete = false;
		$offset = $this->startingOffset;
		$isFirstLoop = true;
		$lastProcessedPrimaryKey = $this->startingOffset; // keep track of the last PK we touched so stalled requests can't reset progress to 0
		$currentOffsetForResponse = $this->startingOffset;
		$tableExportComplete = false;

		// Guard against infinite loops: calculate maximum reasonable iterations
		// +10 buffer: generous margin for edge cases (uneven chunks, off-by-one).
		// Intentionally large because false positives are worse than a few extra iterations.
		$maxIterations = (int)\ceil( $this->maxPageRows / $this->chunkSize ) + 10;
		$iterations = 0;

		do {
			if ( ++$iterations > $maxIterations ) {
				throw new \Exception( sprintf(
					'Export exceeded maximum iterations (%d) for table - possible infinite loop detected',
					$maxIterations
				) );
			}
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
						\sprintf(
							'%s %s %s',
							SqlIdentifier::quote( $primaryOrderColumn, 'Primary key column' ),
							$offset == 0 ? '>=' : '>',
							$offset
						)
					],
					$orderBy,
					$this->chunkSize
				);

				if ( !empty( $tableDataExp->getMostRecentRow() ) ) {
					$lastProcessedPrimaryKey = (int)$tableDataExp->getMostRecentRow()[ $primaryOrderColumn ];
				}
				$currentOffsetForResponse = !empty( $tableDataExp->getMostRecentRow() ) ? $lastProcessedPrimaryKey : $offset;
			}

			if ( $tableDataExp->getPreviousDataRowsCount() === 0 ) {
				$pageExportComplete = true;
				$tableExportComplete = true;
				$this->writeDump(
					$exporter->buildTableDataStructureEnd( $this->table )
							 ->buildFooter()
							 ->getContent( true )
				);
			}
			else {
				$this->writeDump( $tableDataExp->getContent( true ) );
				if ( $tableDataExp->getTotalDataRowsCount() >= $this->maxPageRows ) {
					$pageExportComplete = true;
					$tableExportComplete = false;
					$this->writeDump(
						$exporter->buildTableDataStructureEnd( $this->table )
								 ->buildFooter()
								 ->getContent( true )
					);
				}
			}
		} while ( !$pageExportComplete && $tableDataExp->getTotalDataRowsCount() < $this->maxPageRows );

		// Incomplete exports must advance so the caller cannot loop forever on the same map.
		if ( !$tableExportComplete && $currentOffsetForResponse <= $this->startingOffset ) {
			throw new \Exception( sprintf(
				'Export failed to make progress for table %s - offset did not advance from %d',
				$this->table,
				$this->startingOffset
			) );
		}

		return [
			'table_export_complete' => $tableExportComplete,
			// current_offset feeds the caller's map; keep it advancing even when PK-based paging is used
			'current_offset'        => $currentOffsetForResponse,
			'exported_rows'         => $tableDataExp->getTotalDataRowsCount() ?? 0,
		];
	}

	private function writeDump( array $raw ) :void {
		if ( \fwrite( $this->dumpFile, \implode( "\n", $raw ) ) === false ) {
			throw new \Exception( 'Failed to write Worpdrive database dump content.' );
		}
	}
}
