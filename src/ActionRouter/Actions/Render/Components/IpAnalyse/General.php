<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\LookupMeta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalNames;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\{
	GetIPInfo,
	GetIPReputation
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class General extends Base {

	public const SLUG = 'ipanalyse_general';
	public const TEMPLATE = '/wpadmin/components/ip_analyse/ip_general.twig';

	protected function getRenderData() :array {
		$ip = $this->getAnalyseIP();

		$countryCode = ( new LookupMeta() )
			->setIP( $ip )
			->countryCode();

		try {
			[ $ipKey, $ipName ] = ( new IpID( $ip ) )
				->setIgnoreUserAgent()
				->setVerifyDNS( false )
				->run();
		}
		catch ( \Exception $e ) {
			$ipKey = IpID::UNKNOWN;
			$ipName = 'Unknown';
		}

		$ipRules = new IpRuleStatus( $ip );

		if ( $ipKey === IpID::UNKNOWN && $ipRules->isBypass() ) {
			$firstBypass = \current( $ipRules->getRulesForBypass() );
			$ipName = $firstBypass->label ?? '';
		}

		$shieldNetScore = ( new GetIPReputation() )
							  ->setIP( $ip )
							  ->retrieve()[ 'reputation_score' ] ?? '-';
		$info = ( new GetIPInfo() )
			->setIP( $ip )
			->retrieve();

		$calcScores = ( new CalculateVisitorBotScores() )->setIP( $ip );
		$scores = $calcScores->scores();
		$totalScore = \array_sum( $scores );
		$humanScore = $calcScores->probability();
		$botRiskScore = 100 - $humanScore;
		$humanThreshold = self::con()->comps->opts_lookup->getSilentCaptchaBotThreshold();
		$botRiskThreshold = $this->clampPercent( 100 - $humanThreshold );
		$signalNames = ( new BotSignalNames() )->getBotSignalNames();

		$signalTimestamps = [];
		try {
			$record = ( new BotSignalsRecord() )
				->setIP( $ip )
				->retrieve();
			if ( !empty( $record ) ) {
				$carbon = Services::Request()->carbon();
				foreach ( $scores as $scoreKey => $scoreValue ) {
					if ( $scoreValue !== 0 ) {
						$column = $scoreKey.'_at';
						if ( empty( $record->{$column} ) ) {
							$signalTimestamps[ $scoreKey ] = \in_array( $scoreKey, [ 'known', 'created' ] )
								? __( 'N/A', 'wp-simple-firewall' )
								: __( 'Never Recorded', 'wp-simple-firewall' );
						}
						else {
							$signalTimestamps[ $scoreKey ] = $carbon->setTimestamp( $record->{$column} )->diffForHumans();
						}
					}
				}
			}
		}
		catch ( \Exception $e ) {
			// No signal record.
		}

		$positiveSignals = \array_filter(
			$signalTimestamps,
			fn( $k ) => ( $scores[ $k ] ?? 0 ) > 0,
			\ARRAY_FILTER_USE_KEY
		);
		$negativeSignals = \array_filter(
			$signalTimestamps,
			fn( $k ) => ( $scores[ $k ] ?? 0 ) < 0,
			\ARRAY_FILTER_USE_KEY
		);
		$positiveTotal = \array_sum( \array_intersect_key( $scores, $positiveSignals ) );
		$negativeTotal = \array_sum( \array_intersect_key( $scores, $negativeSignals ) );
		$isBot = self::con()->comps->bot_signals->isBot( $ip, false );
		$isHighBotRisk = $botRiskScore > $botRiskThreshold;
		$riskClass = $isHighBotRisk ? 'danger' : 'success';

		return [
			'hrefs'   => [
				'snapi_reputation_details' => URL::Build( 'https://clk.shldscrty.com/botornot', [ 'ip' => $ip ] ),
			],
			'strings' => [
				'title_security' => __( 'Security Status', 'wp-simple-firewall' ),
				'title_identity' => __( 'IP Identity', 'wp-simple-firewall' ),
				'title_actions'  => __( 'Actions', 'wp-simple-firewall' ),
				'title_signals'  => __( 'Signal Analysis', 'wp-simple-firewall' ),

				'reset_offenses' => __( 'Reset', 'wp-simple-firewall' ),
				'block_ip'       => __( 'Block IP', 'wp-simple-firewall' ),
				'unblock_ip'     => __( 'Unblock IP', 'wp-simple-firewall' ),
				'bypass_ip'      => __( 'Add IP Bypass', 'wp-simple-firewall' ),
				'unbypass_ip'    => __( 'Remove IP Bypass', 'wp-simple-firewall' ),
				'delete_notbot'  => __( 'Reset For This IP', 'wp-simple-firewall' ),
				'see_details'    => __( 'See Details', 'wp-simple-firewall' ),
				'signal_score'   => __( 'Signal Score', 'wp-simple-firewall' ),
				'bot_risk'       => __( 'Bot Risk', 'wp-simple-firewall' ),
				'human'          => __( 'Human', 'wp-simple-firewall' ),
				'bot'            => __( 'Bot', 'wp-simple-firewall' ),
				'human_indicators' => __( 'Human Indicators', 'wp-simple-firewall' ),
				'bot_indicators' => __( 'Bot Indicators', 'wp-simple-firewall' ),
				'none_recorded'  => __( 'None recorded', 'wp-simple-firewall' ),
				'total'          => __( 'Total', 'wp-simple-firewall' ),

				'status' => [
					'offenses'            => __( 'Offenses', 'wp-simple-firewall' ),
					'is_blocked'          => __( 'IP Blocked', 'wp-simple-firewall' ),
					'is_bypass'           => __( 'Bypass Listed', 'wp-simple-firewall' ),
					'ip_reputation'       => __( 'Local Reputation', 'wp-simple-firewall' ),
					'snapi_ip_reputation' => sprintf( __( '%s Score', 'wp-simple-firewall' ), self::con()->labels->getBrandName( 'shieldnet' ) ),
					'block_type'          => $ipRules->isBlocked() ? Handler::GetTypeName( $ipRules->getBlockType() ) : ''
				],

				'yes' => CommonDisplayStrings::get( 'yes_label' ),
				'no'  => CommonDisplayStrings::get( 'no_label' ),

				'identity' => [
					'who_is_it'   => __( 'Is this a known IP address?', 'wp-simple-firewall' ),
					'rdns'        => 'rDNS',
					'country'     => __( 'Country', 'wp-simple-firewall' ),
				],

				'extras' => [
					'ip_whois'       => __( 'IP Whois', 'wp-simple-firewall' ),
					'query_ip_whois' => __( 'Query IP Whois', 'wp-simple-firewall' ),
				],
			],
			'vars'    => [
				'ip'               => $ip,
				'status'           => [
					'offenses'               => $ipRules->getOffenses(),
					'is_blocked'             => $ipRules->isBlocked(),
					'is_bypass'              => $ipRules->isBypass(),
					'ip_reputation_score'    => $botRiskScore,
					'snapi_reputation_score' => \is_numeric( $shieldNetScore ) ? $shieldNetScore : __( 'Unavailable', 'wp-simple-firewall' ),
				],
				'overview'         => [
					'verdict_label'                    => $isBot ? __( 'Likely Bot', 'wp-simple-firewall' ) : __( 'Likely Human', 'wp-simple-firewall' ),
					'verdict_icon_class'               => $isBot ? 'bi-robot' : 'bi-person-fill',
					'verdict_text_class'               => $isBot ? 'danger' : 'success',
					'verdict_color'                    => $isBot ? '#c62f3e' : '#008000',
					'verdict_border_color'             => $isBot ? '#f5c6cb' : '#b8d8b8',
					'verdict_background_color'         => $isBot ? '#fdeaec' : '#e6f5e6',
					'score_label'                      => $this->formatScoreLabel( $totalScore ),
					'score_text_class'                 => $totalScore < 0 ? 'danger' : 'success',
					'bot_risk_label'                   => sprintf( '%s%%', $botRiskScore ),
					'bot_risk_text_class'              => $riskClass,
					'local_reputation_badge_class'     => $riskClass,
					'shieldnet_reputation_badge_class' => $isBot ? 'warning text-dark' : 'success',
					'risk_bar'                         => $this->buildRiskBar( $botRiskScore, $botRiskThreshold ),
				],
				'signals'          => [
					'total'    => \count( $signalTimestamps ),
					'positive' => [
						'count'       => \count( $positiveSignals ),
						'total_label' => $this->formatScoreLabel( $positiveTotal ),
						'rows'        => $this->buildSignalRows( $positiveSignals, $scores, $signalNames ),
					],
					'negative' => [
						'count'       => \count( $negativeSignals ),
						'total_label' => $this->formatScoreLabel( $negativeTotal ),
						'rows'        => $this->buildSignalRows( $negativeSignals, $scores, $signalNames ),
					],
				],
				'identity'         => [
					'who_is_it'    => $ipName,
					'rdns'         => empty( $info[ 'rdns' ][ 'hostname' ] ) ? __( 'Unavailable', 'wp-simple-firewall' ) : $info[ 'rdns' ][ 'hostname' ],
					'country_name' => empty( $countryCode ) ? __( 'Unknown', 'wp-simple-firewall' ) : $countryCode,
				],
				'extras'           => [
					'ip_whois' => sprintf( 'https://whois.domaintools.com/%s', $ip ),
				],
			],
		];
	}

	/**
	 * @param array<string, string> $signals
	 * @param array<string, int>    $scores
	 * @param array<string, string> $signalNames
	 * @return array<int, array{name: string, when: string, score_label: string}>
	 */
	private function buildSignalRows( array $signals, array $scores, array $signalNames ) :array {
		$rows = [];
		foreach ( $signals as $signal => $when ) {
			$rows[] = [
				'name'        => $signalNames[ $signal ],
				'when'        => $when,
				'score_label' => $this->formatScoreLabel( $scores[ $signal ] ),
			];
		}
		return $rows;
	}

	/**
	 * @param int|float $score
	 */
	private function formatScoreLabel( $score ) :string {
		return $score > 0 ? sprintf( '+%s', $score ) : (string)$score;
	}

	/**
	 * @return array{threshold_percent: int, fill_left_percent: int, fill_width_percent: int, fill_class: string}
	 */
	private function buildRiskBar( int $botRiskScore, int $botRiskThreshold ) :array {
		$risk = $this->clampPercent( $botRiskScore );
		$threshold = $this->clampPercent( $botRiskThreshold );
		$isHighBotRisk = $risk > $threshold;

		return [
			'threshold_percent'  => $threshold,
			'fill_left_percent'  => $isHighBotRisk ? $threshold : $risk,
			'fill_width_percent' => $isHighBotRisk ? $risk - $threshold : $threshold - $risk,
			'fill_class'         => $isHighBotRisk ? 'danger' : 'success',
		];
	}

	private function clampPercent( int $value ) :int {
		return \max( 0, \min( 100, $value ) );
	}
}
