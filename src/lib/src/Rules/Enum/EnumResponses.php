<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class EnumResponses {

	private const RESPONSES = [
		Responses\CallUserFuncArray::class,
		Responses\DisableFileEditing::class,
		Responses\DisplayBlockPage::class,
		Responses\DoAction::class,
		Responses\EventFire::class,
		Responses\EventFireDefault::class,
		Responses\FirewallBlock::class,
		Responses\HookAddAction::class,
		Responses\HookAddFilter::class,
		Responses\HookRemoveAction::class,
		Responses\HttpRedirect::class,
		Responses\OverrideShieldIpBlock::class,
		Responses\ProcessIpBlockedShield::class,
		Responses\SetPhpDefine::class,
		Responses\SetRequestToBeLogged::class,
		Responses\TriggerIpBlock::class,
		Responses\TriggerIpOffense::class,
		Responses\UpdateIpRuleLastAccessAt::class,
		Responses\WpDie::class,
	];
	private const RESPONSES_DEPRECATED = [
		Responses\BlockAuthorFishing::class,
		Responses\DisableXmlrpc::class,
		Responses\ForceSslAdmin::class,
		Responses\HideGeneratorTag::class,
		Responses\ProcessIpWhitelisted::class,
		Responses\ProcessIpBlockedCrowdsec::class,
		Responses\ProcessRequestBlockedBySiteBlockdown::class,
	];

	/**
	 * Retrieves the responses for the shield rules.
	 *
	 * The method calls the 'shield/rules/enum_responses' filter hook
	 * to allow modifications to the default list of responses before returning them.
	 *
	 * @return string[]|Responses\Base[]
	 */
	public static function Responses() :array {
		return \apply_filters( 'shield/rules/enum_responses', self::RESPONSES );
	}
}