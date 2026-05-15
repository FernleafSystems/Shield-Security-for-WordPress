<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy;

class PolicyEvidenceMapper {

	private const EVENT_TYPE_MAP = [
		'bottrack_loginfailed'     => PolicyEvidence::TYPE_AUTH_ABUSE,
		'bottrack_logininvalid'    => PolicyEvidence::TYPE_AUTH_ABUSE,
		'2fa_verify_fail'          => PolicyEvidence::TYPE_AUTH_ABUSE,
		'2fa_nonce_verify_fail'    => PolicyEvidence::TYPE_AUTH_ABUSE,
		'login_block'              => PolicyEvidence::TYPE_AUTH_ABUSE,
		'block_lostpassword'       => PolicyEvidence::TYPE_AUTH_ABUSE,
		'block_register'           => PolicyEvidence::TYPE_AUTH_ABUSE,

		'bottrack_404'             => PolicyEvidence::TYPE_PROBE_ABUSE,
		'bottrack_fakewebcrawler'  => PolicyEvidence::TYPE_PROBE_ABUSE,
		'bottrack_invalidscript'   => PolicyEvidence::TYPE_PROBE_ABUSE,
		'bottrack_linkcheese'      => PolicyEvidence::TYPE_PROBE_ABUSE,
		'bottrack_xmlrpc'          => PolicyEvidence::TYPE_PROBE_ABUSE,
		'block_xml'                => PolicyEvidence::TYPE_PROBE_ABUSE,
		'block_author_fishing'     => PolicyEvidence::TYPE_PROBE_ABUSE,
		'hide_login_url'           => PolicyEvidence::TYPE_PROBE_ABUSE,
		'block_anonymous_restapi'  => PolicyEvidence::TYPE_PROBE_ABUSE,

		'cooldown_fail'            => PolicyEvidence::TYPE_RATE_ABUSE,
		'request_limit_exceeded'   => PolicyEvidence::TYPE_RATE_ABUSE,

		'comment_spam_block'       => PolicyEvidence::TYPE_CONTENT_ABUSE,
		'spam_block_antibot'       => PolicyEvidence::TYPE_CONTENT_ABUSE,
		'spam_block_bot'           => PolicyEvidence::TYPE_CONTENT_ABUSE,
		'spam_block_cooldown'      => PolicyEvidence::TYPE_CONTENT_ABUSE,
		'spam_block_human'         => PolicyEvidence::TYPE_CONTENT_ABUSE,
		'spam_block_humanrepeated' => PolicyEvidence::TYPE_CONTENT_ABUSE,
		'spam_form_fail'           => PolicyEvidence::TYPE_CONTENT_ABUSE,
		'block_checkout'           => PolicyEvidence::TYPE_CONTENT_ABUSE,

		'ip_offense'               => PolicyEvidence::TYPE_IP_ENFORCEMENT,
		'ip_blocked'               => PolicyEvidence::TYPE_IP_ENFORCEMENT,
		'ip_block_auto'            => PolicyEvidence::TYPE_IP_ENFORCEMENT,
	];

	private const CRITICAL_EVENTS = [
		'block_author_fishing',
		'request_limit_exceeded',
	];

	private const IGNORED_EVENTS = [
		'request_policy_decision',
		'request_policy_block',
		'bottrack_notbot',
		'bottrack_altcha',
		'frontpage_load',
		'loginpage_load',
		'login_success',
		'2fa_success',
		'2fa_verify_success',
		'spam_form_pass',
		'comment_markspam',
		'comment_unmarkspam',
		'ip_block_manual',
		'ip_bypass_add',
		'ip_unblock',
	];

	/**
	 * @return PolicyEvidence[]
	 */
	public function fromEvent( string $event, array $meta = [] ) :array {
		if ( $event === 'bottrack_multiple' ) {
			$mapped = [];
			foreach ( (array)( $meta[ 'data' ][ 'events' ] ?? [] ) as $childEvent ) {
				if ( \is_string( $childEvent ) ) {
					$mapped = \array_merge( $mapped, $this->fromEvent( $childEvent, $meta ) );
				}
			}
			return $mapped;
		}

		if ( \in_array( $event, self::IGNORED_EVENTS, true ) ) {
			return [];
		}

		if ( $event === 'firewall_block' ) {
			$conditionMeta = $meta[ 'audit_params' ] ?? [];
			$category = (string)( $conditionMeta[ 'scan' ] ?? $conditionMeta[ 'match_category' ] ?? '' );
			$isCritical = \in_array( $category, RequestPolicyEvaluator::CRITICAL_FIREWALL_CATEGORIES, true );
			return [
				new PolicyEvidence( [
					'detector'       => PolicyEvidence::DETECTOR_FIREWALL,
					'type'           => PolicyEvidence::TYPE_FIREWALL_ABUSE,
					'severity'       => $isCritical ? PolicyEvidence::SEVERITY_CRITICAL : PolicyEvidence::SEVERITY_NOISY,
					'source_event'   => $event,
					'condition_meta' => $conditionMeta,
				] ),
			];
		}

		$type = self::EVENT_TYPE_MAP[ $event ] ?? '';
		if ( empty( $type ) ) {
			return [];
		}

		return [
			new PolicyEvidence( [
				'detector'     => PolicyEvidence::DETECTOR_EVENT,
				'type'         => $type,
				'severity'     => \in_array( $event, self::CRITICAL_EVENTS, true )
					? PolicyEvidence::SEVERITY_CRITICAL
					: PolicyEvidence::SEVERITY_SIGNAL,
				'source_event' => $event,
			] ),
		];
	}
}
