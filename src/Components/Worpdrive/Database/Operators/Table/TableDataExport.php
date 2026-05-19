<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
	Config,
	SqlIdentifier
};
use FernleafSystems\Wordpress\Services\Services;

class TableDataExport {

	private Config $cfg;

	private string $table;

	private array $content = [];

	private ?int $totalDataRows = null;

	private ?int $previousDataRows = null;

	private ?array $mostRecentRow = null;

	public function __construct( string $table, Config $cfg ) {
		SqlIdentifier::assertSafe( $table, 'Table name' );
		$this->cfg = $cfg;
		$this->table = $table;
	}

	public function getContent( bool $flush = false ) :array {
		$content = $this->content;
		if ( $flush ) {
			$this->content = [];
		}
		return $content;
	}

	public function getTotalDataRowsCount() :?int {
		return $this->totalDataRows;
	}

	public function getPreviousDataRowsCount() :?int {
		return $this->previousDataRows;
	}

	public function getMostRecentRow() :?array {
		return $this->mostRecentRow;
	}

	/**
	 * @throws \Exception
	 */
	public function buildDataRows( array $where = [], string $orderBy = '', int $limit = 0, int $offset = 0 ) :void {
		$DB = Services::WpDb();

		$rows = $DB->selectCustom( sprintf(
			"SELECT * FROM %s %s %s %s;",
			SqlIdentifier::quote( $this->table, 'Table name' ),
			empty( $where ) ? '' : sprintf( ' WHERE %s', \implode( ' AND ', $where ) ),
			$orderBy,
			empty( $limit ) ? '' : sprintf( ' LIMIT %s OFFSET %s', $limit, $offset )
		) );

		// Detect query failures early so chunked exports cannot loop on the same offset.
		if ( !\is_array( $rows ) ) {
			throw new \Exception( sprintf( 'Database query failed for table: %s', $this->table ) );
		}

		$this->previousDataRows = \count( $rows );
		$this->totalDataRows = ( $this->totalDataRows ?? 0 ) + $this->previousDataRows;

		if ( empty( $rows ) ) {
			$this->mostRecentRow = null;
			return;
		}

		$this->mostRecentRow = \array_pop( $rows );
		$rows[] = $this->mostRecentRow;

		// Describing the table allows us to do smarter things with the values
		$columns = ( new TableHelper( $this->table ) )->showColumns();

		$this->addContent( ( new TableRowsSqlBuilder( $this->cfg ) )->buildInsertLines( $this->table, $rows, $columns ) );

		if ( $this->cfg->has( 'single-transaction' ) ) {
			if ( $DB->doSql( 'COMMIT;' ) === false ) {
				throw new \Exception( 'Failed to commit transaction' );
			}
		}
	}

	public function addLine( string $line ) {
		$this->addContent( [ $line ] );
	}

	public function addContent( array $lines ) {
		$this->content = \array_merge( $this->content, $lines );
	}
}
