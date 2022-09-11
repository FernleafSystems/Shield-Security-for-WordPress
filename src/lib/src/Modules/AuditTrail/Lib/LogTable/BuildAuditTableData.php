<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForAuditTrail;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Services\Services;

class BuildAuditTableData extends BaseBuildTableData {

	/**
	 * @var LogRecord
	 */
	private $log;

	protected function loadRecordsWithDirectQuery() :array {
		return $this->loadRecordsWithSearch();
	}

	protected function getSearchPanesData() :array {
		return ( new BuildSearchPanesData() )
			->setMod( $this->getMod() )
			->build();
	}

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return array_values( array_map(
			function ( $log ) {
				$this->log = $log;
				$data = $this->log->getRawData();
				$data[ 'ip' ] = $this->log->ip;
				$data[ 'rid' ] = $this->log->rid ?? __( 'Unknown', 'wp-simple-firewall' );
				$data[ 'ip_linked' ] = $this->getColumnContent_LinkedIP( (string)$this->log->ip );
				$data[ 'event' ] = $this->getCon()->loadEventsService()->getEventName( $this->log->event_slug );
				$this->log->created_at = max( $this->log->updated_at, $this->log->created_at );
				$data[ 'created_since' ] = $this->getColumnContent_Date( $this->log->created_at );
				$data[ 'message' ] = $this->getColumnContent_Message();
				$data[ 'user' ] = $this->getColumnContent_User();
				$data[ 'user_id' ] = $this->getColumnContent_UserID();
				$data[ 'level' ] = $this->getColumnContent_Level();
				$data[ 'severity' ] = $this->getColumnContent_SeverityIcon();
				$data[ 'meta' ] = $this->getColumnContent_Meta();
				return $data;
			},
			$records
		) );
	}

	/**
	 * The Wheres need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchParams() :array {
		$wheres = [];
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			foreach ( array_filter( $this->table_data[ 'searchPanes' ] ) as $column => $selected ) {
				switch ( $column ) {
					case 'event':
						if ( count( $selected ) > 1 ) {
							$wheres[] = sprintf( "log.event_slug IN ('%s')", implode( '`,`', $selected ) );
						}
						else {
							$wheres[] = sprintf( "log.event_slug='%s'", array_pop( $selected ) );
						}
						break;
					case 'ip':
						$wheres[] = sprintf( "ips.ip=INET6_ATON('%s')", array_pop( $selected ) );
						break;
					default:
						break;
				}
			}
		}
		return $wheres;
	}

	protected function countTotalRecords() :int {
		return $this->getRecordsLoader()->countAll();
	}

	protected function countTotalRecordsFiltered() :int {
		$loader = $this->getRecordsLoader();
		$loader->wheres = $this->buildWheresFromSearchParams();
		return $loader->countAll();
	}

	protected function getSearchableColumns() :array {
		// Use the DataTables definition builder to locate searchable columns
		return array_filter( array_map(
			function ( $column ) {
				return ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '';
			},
			( new ForAuditTrail() )
				->setMod( $this->getMod() )
				->buildRaw()[ 'columns' ]
		) );
	}

	/**
	 * @return LogRecord[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		$loader = $this->getRecordsLoader();
		$loader->wheres = $wheres;
		$loader->limit = $limit;
		$loader->offset = $offset;
		$loader->order_dir = $this->getOrderDirection();
		$loader->order_by = $this->getOrderBy();
		return array_filter(
			$loader->run(),
			function ( $logRecord ) {
				return $this->getCon()->loadEventsService()->eventExists( $logRecord->event_slug );
			}
		);
	}

	protected function getRecordsLoader() :LoadLogs {
		return ( new LoadLogs() )->setMod( $this->getMod() );
	}

	private function getColumnContent_UserID() :string {
		return $this->log->meta_data[ 'uid' ] ?? '-';
	}

	private function getColumnContent_User() :string {
		$content = '-';
		$uid = $this->log->meta_data[ 'uid' ] ?? '';
		if ( !empty( $uid ) ) {
			if ( is_numeric( $uid ) ) {
				$user = Services::WpUsers()->getUserById( $uid );
				if ( !empty( $user ) ) {
					$content = sprintf( '<a href="%s" target="_blank">%s</a>',
						Services::WpUsers()->getAdminUrl_ProfileEdit( $user ),
						$user->user_login );
				}
				else {
					$content = sprintf( 'Unavailable (ID:%s)', $uid );
				}
			}
			else {
				$content = $uid === 'cron' ? 'WP Cron' : 'WP-CLI';
			}
		}
		return $content;
	}

	private function getColumnContent_Message() :string {
		$msg = AuditMessageBuilder::BuildFromLogRecord( $this->log );
		return sprintf( '<span class="message-header">%s</span><textarea readonly rows="%s">%s</textarea>',
			$this->getCon()->loadEventsService()->getEventName( $this->log->event_slug ),
			count( $msg ) + 1, sanitize_textarea_field( implode( "\n", $msg ) ) );
	}

	private function getColumnContent_Meta() :string {
		return sprintf(
			'<button type="button" class="btn btn-link"'.
			' data-toggle="popover"'.
			' data-rid="%s">%s</button>', $this->log->rid,
			sprintf( '<span class="meta-icon">%s</span>',
				$this->getCon()->svgs->raw( 'bootstrap/tags.svg' )
			)
		);
	}

	private function getColumnContent_Level() :string {
		return $this->getCon()->loadEventsService()->getEventDef( $this->log->event_slug )[ 'level' ];
	}

	private function getColumnContent_SeverityIcon() :string {
		$level = $this->getColumnContent_Level();
		$levelDetails = [
							'alert'   => [
								'icon' => 'x-octagon',
							],
							'warning' => [
								'icon' => 'exclamation-triangle',
							],
							'notice'  => [
								'icon' => 'info-square',
							],
							'info'    => [
								'icon' => 'info-circle',
							],
							'debug'   => [
								'icon' => 'question-diamond',
							],
						][ $level ];
		return sprintf( '<span class="severity-%s severity-icon">%s</span>', $level,
			$this->getCon()->svgs->raw( sprintf( 'bootstrap/%s.svg', $levelDetails[ 'icon' ] ) )
		);
	}
}