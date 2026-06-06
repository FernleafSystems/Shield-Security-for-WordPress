<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalysis\IpAnalysisDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * @phpstan-import-type IpAnalysisData from IpAnalysisDataBuilder
 * @phpstan-type SignalViewRow array{name:string,when:string,score_label:string}
 * @phpstan-type VerdictBar array{pivot_percent:int,fill_left_percent:int,fill_width_percent:int,fill_class:string}
 * @phpstan-type GeneralViewStrings array{
 *   title_security:string,
 *   title_identity:string,
 *   title_actions:string,
 *   title_signals:string,
 *   reset_offenses:string,
 *   block_ip:string,
 *   unblock_ip:string,
 *   bypass_ip:string,
 *   unbypass_ip:string,
 *   delete_notbot:string,
 *   see_details:string,
 *   signal_score:string,
 *   bot_risk:string,
 *   human:string,
 *   bot:string,
 *   human_indicators:string,
 *   bot_indicators:string,
 *   none_recorded:string,
 *   total:string,
 *   status:array{offenses:string,is_blocked:string,is_bypass:string,ip_reputation:string,snapi_ip_reputation:string,block_type:string},
 *   yes:string,
 *   no:string,
 *   identity:array{who_is_it:string,rdns:string,country:string},
 *   extras:array{ip_whois:string,query_ip_whois:string}
 * }
 * @phpstan-type GeneralViewVars array{
 *   ip:string,
 *   status:array{offenses:int,is_blocked:bool,is_bypass:bool,snapi_reputation_score:string},
 *   overview:array{
 *     verdict_label:string,
 *     verdict_icon_class:string,
 *     verdict_text_class:string,
 *     verdict_color:string,
 *     verdict_border_color:string,
 *     verdict_background_color:string,
 *     verdict_card_border_color:string,
 *     verdict_card_background_color:string,
 *     score_label:string,
 *     score_text_class:string,
 *     bot_risk_label:string,
 *     bot_risk_text_class:string,
 *     local_reputation_badge_class:string,
 *     shieldnet_reputation_badge_class:string,
 *     verdict_bar:VerdictBar
 *   },
 *   signals:array{
 *     total:int,
 *     positive:array{count:int,total_label:string,rows:list<SignalViewRow>},
 *     negative:array{count:int,total_label:string,rows:list<SignalViewRow>}
 *   },
 *   identity:array{who_is_it:string,rdns:string,country_name:string},
 *   extras:array{ip_whois:string}
 * }
 * @phpstan-type GeneralViewData array{hrefs:array{snapi_reputation_details:string},strings:GeneralViewStrings,vars:GeneralViewVars}
 */
class GeneralViewDataBuilder {

	use PluginControllerConsumer;

	/**
	 * @return array
	 * @phpstan-return GeneralViewData
	 */
	public function build( string $ip ) :array {
		$analysis = $this->buildAnalysisData( $ip );
		$dataUnavailable = __( 'Data not available', 'wp-simple-firewall' );
		$bot = $analysis[ 'bot' ];
		$rules = $analysis[ 'ip_rules' ];
		$shieldNet = $analysis[ 'shieldnet_reputation' ];
		$isBot = $bot[ 'is_bot' ];
		$scoreAvailable = $bot[ 'is_available' ];
		$riskClass = $bot[ 'is_high_bot_risk' ] ? 'danger' : 'success';

		if ( !$scoreAvailable ) {
			$riskClass = 'secondary';
		}

		return [
			'hrefs'   => [
				'snapi_reputation_details' => URL::Build( 'https://clk.shldscrty.com/botornot', [ 'ip' => $ip ] ),
			],
			'strings' => $this->buildStrings( $rules ),
			'vars'    => [
				'ip'       => $ip,
				'status'   => [
					'offenses'               => $rules[ 'offenses' ],
					'is_blocked'             => $rules[ 'is_blocked' ],
					'is_bypass'              => $rules[ 'is_bypass' ],
					'snapi_reputation_score' => $shieldNet[ 'is_available' ] ? (string)$shieldNet[ 'score' ] : $dataUnavailable,
				],
				'overview' => [
					'verdict_label'                    => $scoreAvailable
						? ( $isBot ? __( 'Likely Bot', 'wp-simple-firewall' ) : __( 'Likely Human', 'wp-simple-firewall' ) )
						: $dataUnavailable,
					'verdict_icon_class'               => $scoreAvailable ? ( $isBot ? 'bi-robot' : 'bi-person-fill' ) : 'bi-question-circle',
					'verdict_text_class'               => $scoreAvailable ? ( $isBot ? 'danger' : 'success' ) : 'secondary',
					'verdict_color'                    => $scoreAvailable ? ( $isBot ? '#c62f3e' : '#008000' ) : '#6c757d',
					'verdict_border_color'             => $scoreAvailable ? ( $isBot ? '#f5c6cb' : '#b8d8b8' ) : '#d5d9dd',
					'verdict_background_color'         => $scoreAvailable ? ( $isBot ? '#fdeaec' : '#e6f5e6' ) : '#f3f4f5',
					'verdict_card_border_color'        => $scoreAvailable ? ( $isBot ? '#f3c2c8' : '#b8d8b8' ) : '#d5d9dd',
					'verdict_card_background_color'    => $scoreAvailable ? ( $isBot ? '#fff6f7' : '#f4fbf4' ) : '#fafafa',
					'score_label'                      => $scoreAvailable ? $this->formatScoreLabel( $bot[ 'local_score' ] ) : $dataUnavailable,
					'score_text_class'                 => $scoreAvailable ? ( $bot[ 'local_score' ] < 0 ? 'danger' : 'success' ) : 'secondary',
					'bot_risk_label'                   => $scoreAvailable ? \sprintf( '%s%%', $bot[ 'bot_risk_score' ] ) : $dataUnavailable,
					'bot_risk_text_class'              => $riskClass,
					'local_reputation_badge_class'     => $riskClass,
					'shieldnet_reputation_badge_class' => $shieldNet[ 'is_available' ] ? ( $isBot ? 'warning text-dark' : 'success' ) : 'secondary',
					'verdict_bar'                      => $this->buildVerdictBar(
						$bot[ 'human_probability' ],
						$bot[ 'human_threshold' ],
						$isBot,
						$scoreAvailable
					),
				],
				'signals'  => [
					'total'    => $analysis[ 'signals' ][ 'total' ],
					'positive' => [
						'count'       => \count( $analysis[ 'signals' ][ 'positive_rows' ] ),
						'total_label' => $this->formatScoreLabel( $analysis[ 'signals' ][ 'positive_total' ] ),
						'rows'        => $this->buildSignalRows( $analysis[ 'signals' ][ 'positive_rows' ] ),
					],
					'negative' => [
						'count'       => \count( $analysis[ 'signals' ][ 'negative_rows' ] ),
						'total_label' => $this->formatScoreLabel( $analysis[ 'signals' ][ 'negative_total' ] ),
						'rows'        => $this->buildSignalRows( $analysis[ 'signals' ][ 'negative_rows' ] ),
					],
				],
				'identity' => [
					'who_is_it'    => $analysis[ 'identity' ][ 'name' ] !== '' ? $analysis[ 'identity' ][ 'name' ] : __( 'Unknown', 'wp-simple-firewall' ),
					'rdns'         => $analysis[ 'rdns' ][ 'is_available' ] ? $analysis[ 'rdns' ][ 'hostname' ] : $dataUnavailable,
					'country_name' => $analysis[ 'geo' ][ 'country_code' ] !== '' ? $analysis[ 'geo' ][ 'country_code' ] : __( 'Unknown', 'wp-simple-firewall' ),
				],
				'extras'   => [
					'ip_whois' => \sprintf( 'https://whois.domaintools.com/%s', $ip ),
				],
			],
		];
	}

	/**
	 * @param array{offenses:int,is_blocked:bool,is_bypass:bool,block_type:string} $rules
	 * @return array
	 * @phpstan-return GeneralViewStrings
	 */
	private function buildStrings( array $rules ) :array {
		return [
			'title_security' => __( 'Security Status', 'wp-simple-firewall' ),
			'title_identity' => __( 'IP Identity', 'wp-simple-firewall' ),
			'title_actions'  => __( 'Actions', 'wp-simple-firewall' ),
			'title_signals'  => __( 'Signal Analysis', 'wp-simple-firewall' ),

			'reset_offenses'   => __( 'Reset', 'wp-simple-firewall' ),
			'block_ip'         => __( 'Block IP', 'wp-simple-firewall' ),
			'unblock_ip'       => __( 'Unblock IP', 'wp-simple-firewall' ),
			'bypass_ip'        => __( 'Add Bypass', 'wp-simple-firewall' ),
			'unbypass_ip'      => __( 'Remove IP Bypass', 'wp-simple-firewall' ),
			'delete_notbot'    => __( 'Reset', 'wp-simple-firewall' ),
			'see_details'      => __( 'See Details', 'wp-simple-firewall' ),
			'signal_score'     => __( 'Signal Score', 'wp-simple-firewall' ),
			'bot_risk'         => __( 'Bot Risk', 'wp-simple-firewall' ),
			'human'            => __( 'Human', 'wp-simple-firewall' ),
			'bot'              => __( 'Bot', 'wp-simple-firewall' ),
			'human_indicators' => __( 'Human Indicators', 'wp-simple-firewall' ),
			'bot_indicators'   => __( 'Bot Indicators', 'wp-simple-firewall' ),
			'none_recorded'    => __( 'None recorded', 'wp-simple-firewall' ),
			'total'            => __( 'Total', 'wp-simple-firewall' ),

			'status' => [
				'offenses'            => __( 'Offenses', 'wp-simple-firewall' ),
				'is_blocked'          => __( 'IP Blocked', 'wp-simple-firewall' ),
				'is_bypass'           => __( 'Bypass Listed', 'wp-simple-firewall' ),
				'ip_reputation'       => __( 'Local Reputation', 'wp-simple-firewall' ),
				'snapi_ip_reputation' => \sprintf( __( '%s Score', 'wp-simple-firewall' ), self::con()->labels->getBrandName( 'shieldnet' ) ),
				'block_type'          => $rules[ 'is_blocked' ] ? Handler::GetTypeName( $rules[ 'block_type' ] ) : '',
			],

			'yes' => CommonDisplayStrings::get( 'yes_label' ),
			'no'  => CommonDisplayStrings::get( 'no_label' ),

			'identity' => [
				'who_is_it' => __( 'Is this a known IP address?', 'wp-simple-firewall' ),
				'rdns'      => 'rDNS',
				'country'   => __( 'Country', 'wp-simple-firewall' ),
			],

			'extras' => [
				'ip_whois'       => __( 'IP Whois', 'wp-simple-firewall' ),
				'query_ip_whois' => __( 'Query IP Whois', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return array
	 * @phpstan-return IpAnalysisData
	 */
	protected function buildAnalysisData( string $ip ) :array {
		return ( new IpAnalysisDataBuilder() )->build( $ip );
	}

	/**
	 * @param list<array{key:string,name:string,when:string,score:int}> $signals
	 * @return array
	 * @phpstan-return list<SignalViewRow>
	 */
	private function buildSignalRows( array $signals ) :array {
		return \array_map(
			fn( array $signal ) :array => [
				'name'        => $signal[ 'name' ],
				'when'        => $signal[ 'when' ],
				'score_label' => $this->formatScoreLabel( $signal[ 'score' ] ),
			],
			$signals
		);
	}

	/**
	 * @param int|float $score
	 */
	private function formatScoreLabel( $score ) :string {
		return $score > 0 ? \sprintf( '+%s', $score ) : (string)$score;
	}

	/**
	 * @return array
	 * @phpstan-return VerdictBar
	 */
	private function buildVerdictBar( int $humanScore, int $humanThreshold, bool $isBot, bool $isAvailable = true ) :array {
		if ( !$isAvailable ) {
			return [
				'pivot_percent'      => 0,
				'fill_left_percent'  => 0,
				'fill_width_percent' => 0,
				'fill_class'         => 'secondary',
			];
		}

		$score = $this->clampPercent( $humanScore );
		$threshold = $this->clampPercent( $humanThreshold );
		$fillLeft = $isBot
			? \min( $score, $threshold )
			: $threshold;
		$fillWidth = $isBot
			? \max( 0, $threshold - $score )
			: \max( 0, $score - $threshold );

		return [
			'pivot_percent'      => $threshold,
			'fill_left_percent'  => $fillLeft,
			'fill_width_percent' => $fillWidth,
			'fill_class'         => $isBot ? 'danger' : 'success',
		];
	}

	private function clampPercent( int $value ) :int {
		return \max( 0, \min( 100, $value ) );
	}
}
