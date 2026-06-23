<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LogRecord;

class RequestLogDisplayPathBuilder {

	private RequestQueryRedactor $queryRedactor;

	public function __construct( ?RequestQueryRedactor $queryRedactor = null ) {
		$this->queryRedactor = $queryRedactor ?? new RequestQueryRedactor();
	}

	public function build( LogRecord $record ) :string {
		return $this->buildFromPathAndQuery(
			(string)$record->path,
			(string)( $record->meta[ 'query' ] ?? '' )
		);
	}

	public function buildFromPathAndQuery( string $path, string $query ) :string {
		$query = $this->queryRedactor->redact( $query );

		return $query === '' ? $path : $path.'?'.$query;
	}
}
