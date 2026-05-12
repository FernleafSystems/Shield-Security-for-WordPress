<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord;

class BuildLegacySignals extends BaseBuildData {

	protected function buildFromRecords( array $records ) :array {
		$signalsData = \array_filter( \array_map(
			function ( BotSignalRecord $record ) {
				$signals = $this->determineSignals( $record );
				return ( !empty( $signals ) && $this->isUnknownCrawlerIp( $record->ip ) ) ? [
					'ip'      => $record->ip,
					'signals' => $signals,
				] : [];
			},
			$records
		) );

		\usort( $signalsData, function ( array $a, array $b ) {
			$countA = \count( $a[ 'signals' ] );
			$countB = \count( $b[ 'signals' ] );

			if ( $countA === $countB ) {
				if ( $countA === 1 && \in_array( 'frontpage', $a[ 'signals' ], true ) ) {
					$order = 1;
				}
				elseif ( $countB === 1 && \in_array( 'frontpage', $b[ 'signals' ], true ) ) {
					$order = -1;
				}
				else {
					$order = 0;
				}
			}
			else {
				$order = $countA > $countB ? -1 : 1;
			}

			return $order;
		} );

		return \array_slice( $signalsData, 0, 100 );
	}
}
