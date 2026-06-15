<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\LookupMeta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalNames;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\GetIPInfo;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\GetIPReputation;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * @phpstan-type SignalRow array{key:string,name:string,when:string,score:int}
 * @phpstan-type IpAnalysisData array{
 *   ip:string,
 *   ip_rules:array{offenses:int,is_blocked:bool,is_bypass:bool,block_type:string},
 *   identity:array{key:string,name:string},
 *   geo:array{country_code:string},
 *   rdns:array{is_available:bool,hostname:string},
 *   shieldnet_reputation:array{is_available:bool,score:?int},
 *   bot:array{is_available:bool,is_bot:bool,human_probability:int,local_score:int,bot_risk_score:int,human_threshold:int,bot_risk_threshold:int,is_high_bot_risk:bool},
 *   signals:array{total:int,positive_total:int,negative_total:int,positive_rows:list<SignalRow>,negative_rows:list<SignalRow>,rest_map:array<string,string>}
 * }
 */
class IpAnalysisDataBuilder {

	use PluginControllerConsumer;

	/**
	 * @return array
	 * @phpstan-return IpAnalysisData
	 */
	public function build( string $ip ) :array {
		$rules = $this->buildIpRules( $ip );
		$bot = $this->buildBotAnalysis( $ip );
		$signals = $this->buildSignals( $ip, $bot[ 'scores' ] );

		return [
			'ip'                    => $ip,
			'ip_rules'              => [
				'offenses'   => $rules->getOffenses(),
				'is_blocked' => $rules->isBlocked(),
				'is_bypass'  => $rules->isBypass(),
				'block_type' => $rules->isBlocked() ? (string)$rules->getBlockType() : '',
			],
			'identity'              => $this->buildIdentity( $ip, $rules ),
			'geo'                   => [
				'country_code' => $this->lookupCountryCode( $ip ),
			],
			'rdns'                  => $this->buildRdns( $ip ),
			'shieldnet_reputation'  => $this->buildShieldNetReputation( $ip ),
			'bot'                   => [
				'is_available'       => $bot[ 'is_available' ],
				'is_bot'             => $bot[ 'is_bot' ],
				'human_probability'  => $bot[ 'human_probability' ],
				'local_score'        => $bot[ 'local_score' ],
				'bot_risk_score'     => $bot[ 'bot_risk_score' ],
				'human_threshold'    => $bot[ 'human_threshold' ],
				'bot_risk_threshold' => $bot[ 'bot_risk_threshold' ],
				'is_high_bot_risk'   => $bot[ 'is_high_bot_risk' ],
			],
			'signals'               => $signals,
		];
	}

	protected function buildIpRules( string $ip ) :IpRuleStatus {
		return new IpRuleStatus( $ip );
	}

	/**
	 * @return array{key:string,name:string}
	 */
	protected function buildIdentity( string $ip, IpRuleStatus $rules ) :array {
		try {
			[ $ipKey, $ipName ] = ( new IpID( $ip ) )
				->setIgnoreUserAgent()
				->setVerifyDNS( false )
				->run();
		}
		catch ( \Throwable $e ) {
			$ipKey = IpID::UNKNOWN;
			$ipName = 'Unknown';
		}

		if ( $ipKey === IpID::UNKNOWN && $rules->isBypass() ) {
			$firstBypass = \current( $rules->getRulesForBypass() );
			$ipName = \is_object( $firstBypass ) ? (string)( $firstBypass->label ?? '' ) : $ipName;
		}

		return [
			'key'  => (string)$ipKey,
			'name' => (string)$ipName,
		];
	}

	protected function lookupCountryCode( string $ip ) :string {
		try {
			return (string)( ( new LookupMeta() )
				->setIP( $ip )
				->countryCode() );
		}
		catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * @return array{is_available:bool,hostname:string}
	 */
	protected function buildRdns( string $ip ) :array {
		try {
			$info = ( new GetIPInfo() )
				->setIP( $ip )
				->retrieve();
		}
		catch ( \Throwable $e ) {
			$info = [];
		}

		$hostname = (string)( $info[ 'rdns' ][ 'hostname' ] ?? '' );
		return [
			'is_available' => $hostname !== '',
			'hostname'     => $hostname,
		];
	}

	/**
	 * @return array{is_available:bool,score:?int}
	 */
	protected function buildShieldNetReputation( string $ip ) :array {
		try {
			$rawScore = ( new GetIPReputation() )
				->setIP( $ip )
				->retrieve()[ 'reputation_score' ] ?? null;
		}
		catch ( \Throwable $e ) {
			$rawScore = null;
		}

		return [
			'is_available' => \is_numeric( $rawScore ),
			'score'        => \is_numeric( $rawScore ) ? (int)$rawScore : null,
		];
	}

	/**
	 * @return array{
	 *   is_available:bool,
	 *   is_bot:bool,
	 *   human_probability:int,
	 *   local_score:int,
	 *   bot_risk_score:int,
	 *   human_threshold:int,
	 *   bot_risk_threshold:int,
	 *   is_high_bot_risk:bool,
	 *   scores:array<string,int>
	 * }
	 */
	protected function buildBotAnalysis( string $ip ) :array {
		$scores = [];
		$localScore = 0;
		$humanProbability = 0;
		$isAvailable = false;

		try {
			$calculator = $this->createScoreCalculator( $ip );
			$scores = \array_map( '\intval', $calculator->scores() );
			$localScore = \array_sum( $scores );
			$humanProbability = $this->clampPercent( $calculator->probability() );
			$isAvailable = true;
		}
		catch ( \Throwable $e ) {
		}

		$humanThreshold = $this->silentCaptchaHumanThreshold();
		$botRiskScore = 100 - $humanProbability;
		$botRiskThreshold = 100 - $humanThreshold;
		$isBot = $isAvailable ? $this->isBot( $ip, $humanProbability, $humanThreshold ) : false;

		return [
			'is_available'       => $isAvailable,
			'is_bot'             => $isBot,
			'human_probability'  => $humanProbability,
			'local_score'        => $localScore,
			'bot_risk_score'     => $botRiskScore,
			'human_threshold'    => $humanThreshold,
			'bot_risk_threshold' => $botRiskThreshold,
			'is_high_bot_risk'   => $botRiskScore > $botRiskThreshold,
			'scores'             => $scores,
		];
	}

	protected function createScoreCalculator( string $ip ) :CalculateVisitorBotScores {
		return ( new CalculateVisitorBotScores() )->setIP( $ip );
	}

	protected function silentCaptchaHumanThreshold() :int {
		try {
			return $this->clampPercent( self::con()->comps->opts_lookup->getSilentCaptchaBotThreshold() );
		}
		catch ( \Throwable $e ) {
			return 0;
		}
	}

	protected function isBot( string $ip, int $humanProbability, int $humanThreshold ) :bool {
		try {
			return (bool)self::con()->comps->bot_signals->isBot( $ip, false );
		}
		catch ( \Throwable $e ) {
			return $humanProbability < $humanThreshold;
		}
	}

	/**
	 * @param array<string,int> $scores
	 * @return array{total:int,positive_total:int,negative_total:int,positive_rows:list<array{key:string,name:string,when:string,score:int}>,negative_rows:list<array{key:string,name:string,when:string,score:int}>,rest_map:array<string,string>}
	 */
	protected function buildSignals( string $ip, array $scores ) :array {
		$record = $this->loadSignalRecord( $ip );
		$positiveRows = [];
		$negativeRows = [];
		$restMap = [];
		$names = new BotSignalNames();

		foreach ( $scores as $scoreKey => $scoreValue ) {
			if ( $scoreValue === 0 ) {
				continue;
			}

			$row = [
				'key'   => (string)$scoreKey,
				'name'  => $names->getBotSignalName( $scoreKey ),
				'when'  => $this->signalTimestampLabel( (string)$scoreKey, $record ),
				'score' => (int)$scoreValue,
			];
			$restMap[ $this->restSignalMapKey( $restMap, $row[ 'name' ], $row[ 'key' ] ) ] = $row[ 'when' ];

			if ( $scoreValue > 0 ) {
				$positiveRows[] = $row;
			}
			else {
				$negativeRows[] = $row;
			}
		}

		\ksort( $restMap );

		return [
			'total'          => \count( $positiveRows ) + \count( $negativeRows ),
			'positive_total' => \array_sum( \array_column( $positiveRows, 'score' ) ),
			'negative_total' => \array_sum( \array_column( $negativeRows, 'score' ) ),
			'positive_rows'  => $positiveRows,
			'negative_rows'  => $negativeRows,
			'rest_map'       => $restMap,
		];
	}

	/**
	 * @param array<string,string> $restMap
	 */
	private function restSignalMapKey( array $restMap, string $name, string $key ) :string {
		if ( !\array_key_exists( $name, $restMap ) ) {
			return $name;
		}

		$fallback = \sprintf( '%s (%s)', $name, $key );
		if ( !\array_key_exists( $fallback, $restMap ) ) {
			return $fallback;
		}

		for ( $i = 2; ; $i++ ) {
			$candidate = \sprintf( '%s (%s %d)', $name, $key, $i );
			if ( !\array_key_exists( $candidate, $restMap ) ) {
				return $candidate;
			}
		}
	}

	protected function loadSignalRecord( string $ip ) :?BotSignalRecord {
		try {
			return ( new BotSignalsRecord() )
				->setIP( $ip )
				->retrieve();
		}
		catch ( \Throwable $e ) {
			return null;
		}
	}

	protected function signalTimestampLabel( string $scoreKey, ?BotSignalRecord $record ) :string {
		$column = $scoreKey.'_at';
		if ( empty( $record ) || empty( $record->{$column} ) ) {
			return \in_array( $scoreKey, [ 'known', 'created' ], true )
				? __( 'N/A', 'wp-simple-firewall' )
				: __( 'Never Recorded', 'wp-simple-firewall' );
		}

		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $record->{$column} )
					   ->diffForHumans();
	}

	protected function clampPercent( int $value ) :int {
		return \max( 0, \min( 100, $value ) );
	}
}
