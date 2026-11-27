<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use IPLib\Factory;

class IPRecords {

	use PluginControllerConsumer;

	private static array $ips = [];

	/**
	 * @throws \Exception
	 */
	public function loadIP( string $ip, bool $autoCreate = true, bool $cacheRecord = true ) :Ops\Record {

		if ( !empty( self::$ips[ $ip ] ) ) {
			$record = self::$ips[ $ip ];
		}
		else {
			$parsedRange = Factory::parseRangeString( $ip );
			if ( empty( $parsedRange ) ) {
				throw new \Exception( __( 'Not a valid IP range', 'wp-simple-firewall' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: callers either discard or log it, or AddRule consumers (AJAX responses, WP-CLI) show it as text.
			}
			$ip = \explode( '/', $parsedRange->asSubnet()->toString() )[ 0 ];

			/** @var Ops\Select $select */
			$select = self::con()->db_con->ips->getQuerySelector();
			$record = $select->filterByIPHuman( $ip )
							 ->setNoOrderBy()
							 ->first();

			if ( empty( $record ) && $autoCreate ) {
				$this->addIP( $ip );
				$record = $this->loadIP( $ip, false );
			}

			if ( !$record instanceof Ops\Record ) {
				throw new \Exception( 'IP Record unavailable: '.$ip ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message: callers either discard or log it, or AddRule consumers (AJAX responses, WP-CLI) show it as text.
			}

			if ( $cacheRecord ) {
				self::$ips[ $ip ] = $record;
			}
		}

		return $record;
	}

	public function addIP( string $ip ) {
		$dbh = self::con()->db_con->ips;
		/** @var Ops\Insert $insert */
		$insert = $dbh->getQueryInserter();
		/** @var Ops\Record $record */
		$record = $dbh->getRecord();
		$record->ip = $ip;
		$insert->insert( $record );
	}
}
