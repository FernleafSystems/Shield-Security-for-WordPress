<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

final class ScanStatus {

	public const QUEUED = 'queued';
	public const BUILDING = 'building';
	public const BUILT = 'built';
	public const RUNNING = 'running';
	public const FAILED = 'failed';
	public const COMPLETED = 'completed';

	public const ACTIVE = [
		self::QUEUED,
		self::BUILDING,
		self::BUILT,
		self::RUNNING,
	];

	public const CURRENT = [
		self::BUILDING,
		self::BUILT,
		self::RUNNING,
	];

	public const READY = [
		self::BUILT,
		self::RUNNING,
	];

	public static function sqlList( array $statuses ) :string {
		$statuses = \array_values( \array_filter(
			\array_map( static fn( $status ) :string => (string)$status, $statuses ),
			static fn( string $status ) :bool => $status !== ''
		) );

		return "'".\implode( "','", \array_map(
			static fn( string $status ) :string => \str_replace( "'", "''", $status ),
			$statuses
		) )."'";
	}
}
