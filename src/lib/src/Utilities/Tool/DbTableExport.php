<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\HandlerConsumer;
use FernleafSystems\Wordpress\Services\Services;

// TODO: BROKEN
class DbTableExport {

	use HandlerConsumer;

	public function toCSV() :array {
		$content = [];
		/** @var Record $record */
		foreach ( $this->getDbH()->getIterator() as $record ) {
			if ( !empty( $record ) ) {
				$content[] = $this->implodeForCSV( $this->getEntryAsRawArray( $record ) );
			}
		}
		\array_unshift( $content, $this->implodeForCSV( $this->getActualColumns() ) );
		return [
			'name'    => $this->getFileName(),
			'content' => \implode( "\n", $content )
		];
	}

	protected function implodeForCSV( array $line ) :string {
		return '"'.\implode( '","', $line ).'"';
	}

	/**
	 * @param Record $record
	 */
	protected function getEntryAsRawArray( $record ) :array {
		$entry = $record->getRawData();
		$schema = $this->getDbH()->getTableSchema();
		if ( $schema->hasColumn( 'ip' ) && $schema->is_ip_binary ) {
			$entry[ 'ip' ] = $record->ip;
		}
		if ( $schema->hasColumn( 'meta' ) ) {
			$entry[ 'meta' ] = \serialize( $record->meta );
		}
		return $entry;
	}

	protected function getActualColumns() :array {
		return Services::WpDb()->getColumnsForTable( $this->getDbH()->getTableSchema()->table, '\strtolower' );
	}

	protected function getFileName() :string {
		return sprintf( 'table_export-%s-%s.csv', $this->getDbH()->getTableSchema()->table, date( 'Ymd_His' ) );
	}
}