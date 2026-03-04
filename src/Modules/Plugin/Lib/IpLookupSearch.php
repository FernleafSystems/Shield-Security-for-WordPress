<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class IpLookupSearch {

	use PluginControllerConsumer;

	/**
	 * @return string[]
	 */
	public function findMatchingIps( string $terms, int $limit = 0 ) :array {
		$ipTerms = \array_filter(
			\array_map( '\trim', \explode( ' ', $terms ) ),
			static function ( string $term ) :bool {
				return \preg_match( '#^[\d.]{3,}$#i', $term ) === 1
					   || \preg_match( '#^[\da-f:]{3,}$#i', $term ) === 1;
			}
		);

		if ( empty( $ipTerms ) ) {
			return [];
		}

		$matchedIps = [];
		$dbhIPs = self::con()->db_con->ips;
		$table = $dbhIPs->getTableSchema()->table;
		$limit = \max( 0, $limit );

		foreach ( $ipTerms as $ipTerm ) {
			if ( $limit > 0 && \count( $matchedIps ) >= $limit ) {
				break;
			}

			$selector = $dbhIPs->getQuerySelector();
			if ( $limit > 0 ) {
				$selector->setLimit( \max( 1, $limit - \count( $matchedIps ) ) );
			}

			if ( \preg_match( '#[.:]#', $ipTerm ) === 1 ) {
				$selector->addRawWhere( [
					\sprintf( 'INET6_NTOA(`%s`.`ip`)', $table ),
					'LIKE',
					"'%$ipTerm%'"
				] );
			}
			else {
				$selector->addRawWhere( [
					\sprintf( '`%s`.`ip`', $table ),
					'=',
					"X'$ipTerm'"
				] );
			}

			$ips = $selector->queryWithResult();
			foreach ( \is_array( $ips ) ? $ips : [] as $ipRecord ) {
				if ( \is_string( $ipRecord->ip ?? null ) && $ipRecord->ip !== '' ) {
					$matchedIps[ $ipRecord->ip ] = true;
				}
			}
		}

		$results = \array_keys( $matchedIps );
		\natsort( $results );
		$results = \array_values( $results );
		return $limit > 0
			? \array_slice( $results, 0, $limit )
			: $results;
	}
}
