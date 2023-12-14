<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Conditions\MatchRequestIpIdentities,
	Responses};

class RulesEnum {

	private const CONDITIONS = [
		Conditions\DirContainsFile::class,
		Conditions\IsAdeScoreAtLeast::class,
		Conditions\IsForceOff::class,
		Conditions\IsIpBlacklisted::class,
		Conditions\IsIpBlockedAuto::class,
		Conditions\IsIpBlockedByShield::class,
		Conditions\IsIpBlockedCrowdsec::class,
		Conditions\IsIpBlockedManual::class,
		Conditions\IsIpHighReputation::class,
		Conditions\IsIpValidPublic::class,
		Conditions\IsIpWhitelisted::class,
		Conditions\IsLoggedInNormal::class,
		Conditions\IsNotLoggedInNormal::class,
		Conditions\IsRateLimitExceeded::class,
		Conditions\IsRequestSecurityAdmin::class,
		Conditions\IsRequestStatus404::class,
		Conditions\IsRequestToInvalidPlugin::class,
		Conditions\IsRequestToInvalidTheme::class,
		Conditions\IsRequestToPluginAsset::class,
		Conditions\IsRequestToThemeAsset::class,
		Conditions\IsShieldPluginDisabled::class,
		Conditions\IsSiteLockdownActive::class,
		Conditions\IsUserAdminNormal::class,
		Conditions\IsUserSecurityAdmin::class,
		Conditions\MatchRequestIpAddress::class,
		Conditions\MatchRequestIpIdentity::class,
		Conditions\MatchRequestIpIdentities::class,
		Conditions\MatchRequestIpAddresses::class,
		Conditions\MatchRequestParam::class,
		Conditions\MatchRequestParamFileUploads::class,
		Conditions\MatchRequestParamPost::class,
		Conditions\MatchRequestParamQuery::class,
		Conditions\MatchRequestPath::class,
		Conditions\MatchRequestPaths::class,
		Conditions\MatchRequestScriptName::class,
		Conditions\MatchRequestScriptNames::class,
		Conditions\MatchRequestStatusCode::class,
		Conditions\MatchRequestUseragent::class,
		Conditions\MatchRequestUseragents::class,
		Conditions\RequestBypassesAllRestrictions::class,
		Conditions\RequestHasAnyParameters::class,
		Conditions\RequestHasPostParameters::class,
		Conditions\RequestHasQueryParameters::class,
		Conditions\RequestIsPathWhitelisted::class,
		Conditions\RequestIsServerLoopback::class,
		Conditions\RequestIsSiteBlockdownBlocked::class,
		Conditions\RequestSubjectToAnyShieldRestrictions::class,
		Conditions\RequestIsTrustedBot::class,
		Conditions\RequestParamValueMatchesPost::class,
		Conditions\RequestParamValueMatchesQuery::class,
		Conditions\WpIsAdmin::class,
		Conditions\WpIsAjax::class,
		Conditions\WpIsPermalinksEnabled::class,
		Conditions\WpIsWpcli::class,
		Conditions\WpIsXmlrpc::class,
	];
	private const CONDITIONS_DEPRECATED = [
		Conditions\RequestParamIs::class,
		Conditions\RequestPostParamIs::class,
		Conditions\RequestQueryParamIs::class,
	];
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
		Responses\ProcessIpBlockedShield::class,
		Responses\SetPhpDefine::class,
		Responses\TrafficRateLimitExceeded::class,
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
	public const TYPE_NORMAL = 'normal';
	public const TYPE_REQUEST = 'request';
	public const TYPE_SHIELD = 'shield';
	public const TYPE_FS = 'filesystem';
	public const TYPE_USER = 'user';
	public const TYPE_WP = 'wordpress';

	/**
	 * Retrieves the conditions used in the application.
	 *
	 * This method returns an array of conditions by applying filters
	 * to the 'shield/rules/enum_conditions' hook. The conditions are
	 * filtered to only include those that start with the letter 'C'.
	 *
	 * @return string[]|Conditions\Base[].
	 */
	public static function Conditions() :array {
		return \apply_filters( 'shield/rules/enum_conditions', self::CONDITIONS );
	}

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

	public static function Types() :array {
		return \apply_filters( 'shield/rules/enum_types', [
			self::TYPE_REQUEST,
			self::TYPE_SHIELD,
			self::TYPE_USER,
			self::TYPE_WP,
			self::TYPE_FS,
		] );
	}
}