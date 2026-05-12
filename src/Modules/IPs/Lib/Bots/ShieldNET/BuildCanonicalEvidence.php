<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord;

class BuildCanonicalEvidence extends BaseBuildData {

	private const EVIDENCE_SIGNAL_MAP = [
		'human_verified'   => [ 'notbot', 'altcha' ],
		'authenticated'    => [ 'auth' ],
		'credential_attack' => [ 'btloginfail', 'btlogininvalid' ],
		'recon'            => [ 'bt404', 'btfake', 'btcheese', 'btinvalidscript', 'btauthorfishing' ],
		'xmlrpc_abuse'     => [ 'btxml' ],
		'spam'             => [ 'humanspam', 'markspam' ],
		'enforcement'      => [ 'ratelimit', 'firewall', 'offense', 'blocked' ],
	];

	protected function buildFromRecords( array $records ) :array {
		$evidenceData = \array_filter( \array_map(
			function ( BotSignalRecord $record ) {
				$evidence = $this->mapSignalsToEvidence( $this->determineSignals( $record ) );
				return ( !empty( $evidence ) && $this->isUnknownCrawlerIp( $record->ip ) ) ? [
					'ip'       => $record->ip,
					'evidence' => $evidence,
				] : [];
			},
			$records
		) );

		\usort( $evidenceData, fn( array $a, array $b ) => \count( $b[ 'evidence' ] ) <=> \count( $a[ 'evidence' ] ) );

		return \array_slice( $evidenceData, 0, 100 );
	}

	private function mapSignalsToEvidence( array $signals ) :array {
		$evidence = [];
		foreach ( self::EVIDENCE_SIGNAL_MAP as $bucket => $mappedSignals ) {
			if ( !empty( \array_intersect( $signals, $mappedSignals ) ) ) {
				$evidence[] = $bucket;
			}
		}
		return $evidence;
	}
}
