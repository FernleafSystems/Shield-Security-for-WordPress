<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class DbTableExport {

	use HandlerConsumer;

	public function toCSV() {
		$content = [];
		/** @var EntryVO $entryVO */
		foreach ( $this->getDbHandler()->getIterator() as $entryVO ) {
			$content[] = $this->implodeForCSV( $this->getEntryAsRawArray( $entryVO ) );
		}
		array_unshift( $content, $this->implodeForCSV( $this->getActualColumns() ) );
		Services::Response()->downloadStringAsFile( implode( "\n", $content ), $this->getFileName() );
	}

	protected function implodeForCSV( array $line ) :string {
		return '"'.implode( '","', $line ).'"';
	}

	/**
	 * @param EntryVO $entryVO
	 * @return array
	 */
	protected function getEntryAsRawArray( $entryVO ) :array {
		$entry = $entryVO->getRawData();
		$schema = $this->getDbHandler()->getTableSchema();
		if ( $schema->is_ip_binary ) {
			$entry[ 'ip' ] = $entryVO->ip;
		}
		if ( $schema->hasColumn( 'meta' ) ) {
			$entry[ 'meta' ] = serialize( $entryVO->meta );
		}
		return $entry;
	}

	protected function getActualColumns() :array {
		return Services::WpDb()->getColumnsForTable( $this->getDbHandler()->getTableSchema()->table, 'strtolower' );
	}

	protected function getFileName() :string {
		return sprintf( 'table_export-%s-%s.csv', $this->getDbHandler()->getTableSchema()->table, date( 'Ymd_His' ) );
	}
}