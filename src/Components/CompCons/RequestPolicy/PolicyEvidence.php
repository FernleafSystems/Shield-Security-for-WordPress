<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

class PolicyEvidence {

	public const DETECTOR_FIREWALL = 'firewall';
	public const DETECTOR_CROWDSEC = 'crowdsec';
	public const DETECTOR_SHIELD_IP = 'shield_ip';
	public const DETECTOR_EVENT = 'event';

	public const TYPE_NONE = 'none';
	public const TYPE_AUTH_FAILURE = 'auth_failure';
	public const TYPE_AUTH_INVALID_USER = 'auth_invalid_user';
	public const TYPE_XMLRPC = 'xmlrpc';
	public const TYPE_USERNAME_FISHING = 'username_fishing';
	public const TYPE_RATE_LIMIT = 'rate_limit';
	public const TYPE_FIREWALL_NOISY = 'firewall_noisy';
	public const TYPE_FIREWALL_CRITICAL = 'firewall_critical';
	public const TYPE_FIREWALL_LOG = 'firewall_log';
	public const TYPE_IP_OFFENSE = 'ip_offense';
	public const TYPE_IP_BLOCKED = 'ip_blocked';
	public const TYPE_CROWDSEC = 'crowdsec';
	public const TYPE_SHIELD_AUTO_BLOCK = 'shield_auto_block';
	public const TYPE_SHIELD_MANUAL_BLOCK = 'shield_manual_block';

	public const SEVERITY_INFO = 'info';
	public const SEVERITY_SIGNAL = 'signal';
	public const SEVERITY_NOISY = 'noisy';
	public const SEVERITY_CRITICAL = 'critical';

	public string $detector = self::DETECTOR_EVENT;

	public string $type = self::TYPE_NONE;

	public string $severity = self::SEVERITY_INFO;

	public string $rule_slug = '';

	public array $condition_meta = [];

	public function __construct( array $data = [] ) {
		foreach ( $data as $key => $value ) {
			if ( \property_exists( $this, $key ) ) {
				$this->{$key} = $value;
			}
		}
	}

	public function isFirewallCritical() :bool {
		return $this->type === self::TYPE_FIREWALL_CRITICAL || $this->severity === self::SEVERITY_CRITICAL;
	}
}
