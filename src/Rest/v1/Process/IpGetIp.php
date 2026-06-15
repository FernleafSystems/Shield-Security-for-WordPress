<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalysis\IpAnalysisDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

/**
 * @phpstan-import-type IpAnalysisData from IpAnalysisDataBuilder
 */
class IpGetIp extends IpBase {

	protected function process() :array {
		return [
			'ip_list' => $this->getIpListInfo(),
			'not_bot' => $this->getNotBotInfo(),
		];
	}

	/**
	 * @return array{
	 *   human_probability:int,
	 *   score_local:int,
	 *   score_shieldnet:int|string,
	 *   score_shieldnet_available:bool,
	 *   score_shieldnet_value:?int,
	 *   signals:array<string,string>
	 * }
	 */
	private function getNotBotInfo() :array {
		$analysis = $this->buildIpAnalysisData();
		$shieldNet = $analysis[ 'shieldnet_reputation' ];

		return [
			'human_probability'         => $analysis[ 'bot' ][ 'human_probability' ],
			'score_local'               => $analysis[ 'bot' ][ 'local_score' ],
			'score_shieldnet'           => $shieldNet[ 'is_available' ] ? $shieldNet[ 'score' ] : '-',
			'score_shieldnet_available' => $shieldNet[ 'is_available' ],
			'score_shieldnet_value'     => $shieldNet[ 'score' ],
			'signals'                   => $analysis[ 'signals' ][ 'rest_map' ],
		];
	}

	/**
	 * @return array
	 * @phpstan-return IpAnalysisData
	 */
	protected function buildIpAnalysisData() :array {
		return ( new IpAnalysisDataBuilder() )->build( $this->ip() );
	}

	private function getIpListInfo() :array {
		$ruleStatus = new IpRuleStatus( $this->ip() );
		if ( $ruleStatus->isBypass() ) {
			$ip = \current( $ruleStatus->getRulesForBypass() );
		}
		elseif ( $ruleStatus->isBlocked() ) {
			$ip = \current( $ruleStatus->getRulesForBlock() );
		}
		elseif ( $ruleStatus->isAutoBlacklisted() ) {
			$ip = $ruleStatus->getRuleForAutoBlock();
		}

		$info = [];
		if ( !empty( $ip ) ) {
			$dbh = self::con()->db_con->ip_rules;
			$info = [
				'type'           => $dbh::GetTypeName( $ip->type ),
				'offenses'       => $ruleStatus->getOffenses(),
				'label'          => $ip->label,
				'blocked_at'     => $ip->blocked_at,
				'unblocked_at'   => $ip->unblocked_at,
				'last_access_at' => $ip->last_access_at,
				'list'           => $dbh::GetTypeName( $ip->type ),
			];
		}

		return $info;
	}
}
