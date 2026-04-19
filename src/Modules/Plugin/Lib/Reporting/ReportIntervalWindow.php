<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

class ReportIntervalWindow {

	public int $start_at;

	public int $end_at;

	public string $timezone;

	public function __construct( int $startAt, int $endAt, string $timezone = 'UTC' ) {
		if ( $endAt < $startAt ) {
			throw new \InvalidArgumentException( 'Report interval end must not be earlier than the start.' );
		}

		$this->start_at = $startAt;
		$this->end_at = $endAt;
		$this->timezone = \trim( $timezone ) !== '' ? $timezone : 'UTC';
	}
}
