<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\SearchTextParser;

class InvestigationStructuredSearch {

	public function hasActiveFilters( array $parsedSearch ) :bool {
		return !empty( $parsedSearch[ 'remaining' ] )
			|| !empty( $parsedSearch[ 'ip' ] )
			|| !empty( $parsedSearch[ SearchTextParser::USER_ID ] )
			|| !empty( $parsedSearch[ SearchTextParser::USER_NAME ] )
			|| !empty( $parsedSearch[ SearchTextParser::USER_EMAIL ] );
	}

	public function filterRecordsForIpToken( array $records, array $parsedSearch ) :array {
		if ( empty( $parsedSearch[ 'ip' ] ) ) {
			return $records;
		}

		$searchIp = (string)$parsedSearch[ 'ip' ];
		return \array_values( \array_filter(
			$records,
			fn( array $record ) :bool => \stripos( (string)( $record[ 'ip' ] ?? '' ), $searchIp ) !== false
		) );
	}

	/**
	 * @param callable(string):int $resolveUsernameToUid
	 * @param callable(string):int $resolveEmailToUid
	 */
	public function passesUserSubject(
		array $parsedSearch,
		int $subjectUserId,
		callable $resolveUsernameToUid,
		callable $resolveEmailToUid
	) :bool {
		$ids = [];

		if ( !empty( $parsedSearch[ SearchTextParser::USER_ID ] ) ) {
			$ids[] = (int)$parsedSearch[ SearchTextParser::USER_ID ];
		}
		if ( !empty( $parsedSearch[ SearchTextParser::USER_NAME ] ) ) {
			$ids[] = (int)$resolveUsernameToUid( (string)$parsedSearch[ SearchTextParser::USER_NAME ] );
		}
		if ( !empty( $parsedSearch[ SearchTextParser::USER_EMAIL ] ) ) {
			$ids[] = (int)$resolveEmailToUid( (string)$parsedSearch[ SearchTextParser::USER_EMAIL ] );
		}

		$ids = \array_values( \array_unique( \array_filter( $ids, fn( int $uid ) :bool => $uid > 0 ) ) );
		if ( empty( $ids ) ) {
			return empty( $parsedSearch[ SearchTextParser::USER_NAME ] )
				&& empty( $parsedSearch[ SearchTextParser::USER_EMAIL ] )
				&& ( empty( $parsedSearch[ SearchTextParser::USER_ID ] )
					|| (int)$parsedSearch[ SearchTextParser::USER_ID ] > 0 );
		}

		return \count( $ids ) === 1 && (int)\current( $ids ) === $subjectUserId;
	}
}
