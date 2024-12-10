<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord;
use FernleafSystems\Wordpress\Services\Utilities\Net\DNS;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpMeta\{
	IpMetaRecord,
	LoadIpMeta
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\TrustedServices;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions\SessionVO;

/**
 * This is set within Rule processing when checking for logged-in user.
 * @property ?SessionVO      $session
 *
 * @property ?IpMetaRecord   $ip_meta_record
 * @property BotSignalRecord $botsignal_record
 *
 * @property bool            $is_force_off
 * @property bool            $is_security_admin
 * @property bool            $is_ip_blocked
 * @property bool            $is_ip_blocked_crowdsec
 * @property bool            $is_ip_blocked_shield
 * @property bool            $is_ip_blocked_shield_auto
 * @property bool            $is_ip_blocked_shield_manual
 * @property bool            $is_ip_high_reputation
 * @property bool            $is_ip_blacklisted
 * @property bool            $is_ip_whitelisted
 * @property bool            $request_bypasses_all_restrictions
 * @property bool            $request_subject_to_shield_restrictions
 * @property bool            $is_site_lockdown_active
 * @property bool            $is_site_lockdown_blocked
 * ** Dynamic **
 * @property bool            $is_trusted_request
 */
class ThisRequest extends \FernleafSystems\Wordpress\Services\Request\ThisRequest {

	/**
	 * @var IpRuleStatus
	 */
	private $ipStatus;

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'ip_meta_record':
				if ( $value === null ) {
					$this->ip_meta_record = $value = empty( $this->ip ) ? null : ( new LoadIpMeta() )->single( $this->ip );
				}
				break;

			case 'is_ip_blocked':
				if ( \is_null( $value ) ) {
					$value = $this->is_ip_blocked_shield || $this->is_ip_blocked_crowdsec;
				}
				break;

			case 'is_ip_blocked_shield':
				if ( \is_null( $value ) ) {
					$value = $this->is_ip_blocked_shield_auto || $this->is_ip_blocked_shield_manual;
				}
				break;

			case 'is_ip_blocked_shield_auto':
				$value = apply_filters( 'shield/is_ip_blocked_auto', $this->getIpStatus()->hasAutoBlock() );
				break;

			case 'is_ip_blocked_crowdsec':
				$value = $this->getIpStatus()->hasCrowdsecBlock();
				break;

			case 'is_ip_blocked_shield_manual':
				$value = $this->getIpStatus()->hasManualBlock();
				break;

			case 'is_ip_blacklisted':
				$status = $this->getIpStatus();
				$value = $status->isBlockedByShield() || $status->isAutoBlacklisted();
				break;

			case 'is_trusted_request':
				$value = \apply_filters( 'shield/is_trusted_request', \in_array( $this->ip_id, ( new TrustedServices() )->enum() ), $this );
				break;

			default:
				break;
		}
		return $value;
	}

	public function getIpStatus() :IpRuleStatus {
		return $this->ipStatus ?? $this->ipStatus = new IpRuleStatus( $this->ip );
	}

	public function getHostname() :string {
		return DNS::Reverse( $this->ip );
	}
}