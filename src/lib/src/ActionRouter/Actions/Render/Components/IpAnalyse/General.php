<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\LookupMeta;
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
		$ip = $this->action_data[ 'ip' ];

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

		if ( $ipKey === IpID::UNKNOWN && !empty( $bypassIP ) ) {
			$ipName = $bypassIP->label ?? '';
		}

		$shieldNetScore = ( new GetIPReputation() )
							  ->setIP( $ip )
							  ->retrieve()[ 'reputation_score' ] ?? '-';
		$info = ( new GetIPInfo() )
			->setIP( $ip )
			->retrieve();

		$ruleStatus = new IpRuleStatus( $ip );
		return [
			'flags'   => [
				'has_geo' => !empty( $countryCode ),
			],
			'hrefs'   => [
				'snapi_reputation_details' => URL::Build( 'https://clk.shldscrty.com/botornot', [ 'ip' => $ip ] ),
			],
			'strings' => [
				'title_general' => __( 'Identifying Info', 'wp-simple-firewall' ),
				'title_status'  => __( 'IP Status', 'wp-simple-firewall' ),

				'reset_offenses' => __( 'Reset', 'wp-simple-firewall' ),
				'block_ip'       => __( 'Block IP', 'wp-simple-firewall' ),
				'unblock_ip'     => __( 'Unblock IP', 'wp-simple-firewall' ),
				'bypass_ip'      => __( 'Add IP Bypass', 'wp-simple-firewall' ),
				'unbypass_ip'    => __( 'Remove IP Bypass', 'wp-simple-firewall' ),
				'delete_notbot'  => __( 'Reset For This IP', 'wp-simple-firewall' ),
				'see_details'    => __( 'See Details', 'wp-simple-firewall' ),

				'status' => [
					'is_you'              => __( 'Is It You?', 'wp-simple-firewall' ),
					'offenses'            => __( 'Number of offenses', 'wp-simple-firewall' ),
					'is_blocked'          => __( 'IP Blocked', 'wp-simple-firewall' ),
					'is_bypass'           => __( 'Bypass IP', 'wp-simple-firewall' ),
					'ip_reputation'       => __( 'IP Reputation Score', 'wp-simple-firewall' ),
					'snapi_ip_reputation' => __( 'ShieldNET IP Reputation Score', 'wp-simple-firewall' ),
					'block_type'          => $ruleStatus->isBlocked() ? Handler::GetTypeName( $ruleStatus->getBlockType() ) : ''
				],

				'yes' => __( 'Yes', 'wp-simple-firewall' ),
				'no'  => __( 'No', 'wp-simple-firewall' ),

				'identity' => [
					'who_is_it'   => __( 'Is this a known IP address?', 'wp-simple-firewall' ),
					'rdns'        => 'rDNS',
					'country'     => __( 'Country', 'wp-simple-firewall' ),
					'timezone'    => __( 'Timezone', 'wp-simple-firewall' ),
					'coordinates' => __( 'Coordinates', 'wp-simple-firewall' ),
				],

				'extras' => [
					'title'          => __( 'Extras', 'wp-simple-firewall' ),
					'ip_whois'       => __( 'IP Whois', 'wp-simple-firewall' ),
					'query_ip_whois' => __( 'Query IP Whois', 'wp-simple-firewall' ),
				],
			],
			'vars'    => [
				'ip'       => $ip,
				'status'   => [
					'is_you'                 => Services::IP()::IpIn( $ip, [ self::con()->this_req->ip ] ),
					'offenses'               => $ruleStatus->getOffenses(),
					'is_blocked'             => $ruleStatus->isBlocked(),
					'is_bypass'              => $ruleStatus->isBypass(),
					'is_crowdsec'            => $ruleStatus->isBlockedByCrowdsec(),
					'ip_reputation_score'    => ( new CalculateVisitorBotScores() )
						->setIP( $ip )
						->probability(),
					'snapi_reputation_score' => \is_numeric( $shieldNetScore ) ? $shieldNetScore : 'Unavailable',
					'is_bot'                 => self::con()->comps->bot_signals->isBot( $ip, false ),
				],
				'identity' => [
					'who_is_it'    => $ipName,
					'rdns'         => empty( $info[ 'rdns' ][ 'hostname' ] ) ? __( 'Unavailable', 'wp-simple-firewall' ) : $info[ 'rdns' ][ 'hostname' ],
					'country_name' => empty( $countryCode ) ? __( 'Unknown', 'wp-simple-firewall' ) : $countryCode,
				],
				'extras'   => [
					'ip_whois' => sprintf( 'https://whois.domaintools.com/%s', $ip ),
				],
			],
		];
	}
}