<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class EnumResponses {

	private const RESPONSES = [
		Responses\DisableFileEditing::class,
		Responses\DisableRestApiRequest::class,
		Responses\DisplayBlockPage::class,
		Responses\DoAction::class,
		Responses\EventFire::class,
		Responses\EventFireDefault::class,
		Responses\FirewallBlock::class,
		Responses\HookAddAction::class,
		Responses\HookAddFilter::class,
		Responses\HookRemoveAction::class,
		Responses\HttpCookieSet::class,
		Responses\HttpRedirect::class,
		Responses\PhpCallUserFuncArray::class,
		Responses\PhpDie::class,
		Responses\PhpErrorLog::class,
		Responses\PhpSendHttpHeader::class,
		Responses\PhpSetDefine::class,
		Responses\PreventShieldIpAutoBlock::class,
		Responses\SetRequestToBeLogged::class,
		Responses\TriggerIpBlock::class,
		Responses\TriggerIpOffense::class,
		Responses\UpdateIpGeoData::class,
		Responses\UpdateIpRuleLastAccessAt::class,
		Responses\UserClearAuthCookies::class,
		Responses\UserSessionRotateAuthCookies::class,
		Responses\UserSessionLogoutAll::class,
		Responses\UserSessionLogoutCurrent::class,
		Responses\UserSetMeta::class,
		Responses\UserSuspend::class,
		Responses\UserUnsuspend::class,
		Responses\WpDie::class,
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