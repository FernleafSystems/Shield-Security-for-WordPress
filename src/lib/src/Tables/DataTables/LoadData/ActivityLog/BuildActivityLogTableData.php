<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\{
	LoadLogs,
	LogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class BuildActivityLogTableData extends BaseBuildTableData {

	/**
	 * @var LogRecord
	 */
	private $log;

	protected function loadRecordsWithDirectQuery() :array {
		return $this->loadRecordsWithSearch();
	}

	protected function getSearchPanesData() :array {
		return ( new BuildSearchPanesData() )->build();
	}

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return \array_values( \array_map(
			function ( $log ) {
				$this->log = $log;
				$data = $this->log->getRawData();
				$data[ 'ip' ] = $this->log->ip;
				$data[ 'rid' ] = $this->log->rid ?? __( 'Unknown', 'wp-simple-firewall' );
				$data[ 'identity' ] = $this->getColumnContent_Identity();
				$data[ 'event' ] = self::con()->comps->events->getEventName( $this->log->event_slug );
				$this->log->created_at = \max( $this->log->updated_at, $this->log->created_at );
				$data[ 'created_since' ] = $this->getColumnContent_Date( $this->log->created_at );
				$data[ 'message' ] = $this->getColumnContent_Message();
				$data[ 'user' ] = $this->getColumnContent_User();
				$data[ 'uid' ] = $this->getColumnContent_UserID();
				$data[ 'level' ] = $this->getColumnContent_Level();
				$data[ 'severity' ] = $this->getColumnContent_SeverityIcon();
				$data[ 'meta' ] = $this->getColumnContent_Meta();
				$data[ 'day' ] = Services::Request()
										 ->carbon( true )->setTimestamp( $this->log->created_at )->toDateString();
				return $data;
			},
			$records
		) );
	}

	/**
	 * The `Where`s need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchParams() :array {
		$wheres = [];
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			foreach ( \array_filter( $this->table_data[ 'searchPanes' ] ) as $column => $selected ) {
				switch ( $column ) {
					case 'day':
						$wheres[] = $this->buildSqlWhereForDaysSearch( $selected, 'log' );
						break;
					case 'event':
						if ( \count( $selected ) > 1 ) {
							$wheres[] = sprintf( "`log`.`event_slug` IN ('%s')", \implode( "','", $selected ) );
						}
						else {
							$wheres[] = sprintf( "`log`.`event_slug`='%s'", \array_pop( $selected ) );
						}
						break;
					case 'ip':
						$wheres[] = sprintf( "`ips`.`ip`=INET6_ATON('%s')", \array_pop( $selected ) );
						break;
					case 'user':
						if ( \count( $selected ) > 0 ) {
							$wheres[] = sprintf( "`req`.`uid` IN (%s)", \implode( ',', \array_values( $selected ) ) );
						}
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
		return \array_filter( \array_map(
			function ( $column ) {
				return ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '';
			},
			( new ForActivityLog() )->buildRaw()[ 'columns' ]
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
		return \array_filter(
			$loader->run(),
			function ( $logRecord ) {
				return self::con()->comps->events->eventExists( $logRecord->event_slug );
			}
		);
	}

	protected function getRecordsLoader() :LoadLogs {
		return new LoadLogs();
	}

	private function getColumnContent_UserID() :string {
		return $this->log->meta_data[ 'uid' ] ?? '-';
	}

	protected function getColumnContent_Identity() :string {
		$ip = (string)$this->log->ip;
		if ( !empty( $ip ) ) {
			try {
				$ipID = ( new IpID( $ip ) )->run();
				if ( $ipID[ 0 ] === IpID::THIS_SERVER ) {
					$id = __( 'This Server', 'wp-simple-firewall' );
				}
				elseif ( $ipID[ 0 ] === IpID::VISITOR ) {
					$id = __( 'Your Current IP', 'wp-simple-firewall' );
				}
				elseif ( $ipID[ 0 ] === IpID::UNKNOWN ) {
					$id = __( 'Unidentified', 'wp-simple-firewall' );
				}
				else {
					$id = sprintf( '<code>%s</code>', $ipID[ 1 ] );
				}
			}
			catch ( \Exception $e ) {
				$id = '';
			}

			$loggedIn = \is_numeric( $this->getColumnContent_UserID() );
			$content = \implode( '', \array_filter( [
				sprintf( '%s',
					$loggedIn ?
						sprintf( '%s and authenticated as %s', $id, $this->getColumnContent_User() )
						: sprintf( '%s and not authenticated', $id )
				),
				sprintf( '<h6 class="text-nowrap mb-0">%s</h6>',
					$this->getIpAnalysisLink( $ip )
				),
			] ) );
		}
		else {
			$content = 'No IP';
		}
		return $content;
	}

	protected function getColumnContent_User() :string {
		$content = '-';
		$uid = $this->log->meta_data[ 'uid' ] ?? '';
		if ( !empty( $uid ) ) {
			if ( \is_numeric( $uid ) ) {
				$WPU = Services::WpUsers();
				$user = $WPU->getUserById( $uid );
				$content = empty( $user ) ?
					sprintf( 'Unavailable (ID:%s)', $uid ) :
					sprintf( '<a href="%s" target="_blank">%s</a>', $WPU->getAdminUrl_ProfileEdit( $user ), $user->user_login );
			}
			else {
				$content = $uid === 'cron' ? 'WP Cron' : 'WP-CLI';
			}
		}
		return $content;
	}

	private function getColumnContent_Message() :string {
		$msg = ActivityLogMessageBuilder::BuildFromLogRecord( $this->log, "<br/> \n" );
		return sprintf( '<span class="message-header">%s</span><p class="m-0">%s</p>',
			self::con()->comps->events->getEventName( $this->log->event_slug ),
			sanitize_textarea_field( \implode( "<br/>", $msg ) )
		);
	}

	private function getColumnContent_Meta() :string {
		$label = __( 'Click to display meta data for this request in a popover', 'wp-simple-firewall' );
		return sprintf( '<button type="button" aria-label="%s" class="btn btn-link" title="%s" data-toggle="popover" data-rid="%s">%s</button>',
			$label,
			$label,
			$this->log->rid,
			sprintf( '<span class="meta-icon">%s</span>', self::con()->svgs->raw( 'tags.svg' ) )
		);
	}

	private function getColumnContent_Level() :string {
		return self::con()->comps->events->getEventDef( $this->log->event_slug )[ 'level' ];
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
		return sprintf( '<div class="severity-%s severity-icon">%s</div>', $level,
			self::con()->svgs->raw( $levelDetails[ 'icon' ] )
		);
	}
}