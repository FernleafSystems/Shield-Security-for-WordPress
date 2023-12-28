<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class EnumConditions {

	private const CONDITIONS = [
		Conditions\DirContainsFile::class,
		Conditions\IsAdeScore::class,
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
		Conditions\IsRateLimitExceeded::class,
		Conditions\IsRequestSecurityAdmin::class,
		Conditions\IsRequestStatus404::class,
		Conditions\IsRequestToLoginPage::class,
		Conditions\IsRequestToInvalidPlugin::class,
		Conditions\IsRequestToInvalidTheme::class,
		Conditions\IsRequestToPluginAsset::class,
		Conditions\IsRequestToThemeAsset::class,
		Conditions\IsRequestWhitelisted::class,
		Conditions\IsShieldPluginDisabled::class,
		Conditions\IsSiteLockdownActive::class,
		Conditions\IsUserAdminNormal::class,
		Conditions\IsUserSecurityAdmin::class,
		Conditions\MatchRequestIpAddress::class,
		Conditions\MatchRequestIpIdentity::class,
		Conditions\MatchRequestIpIdentities::class,
		Conditions\MatchRequestIpAddresses::class,
		Conditions\MatchRequestMethod::class,
		Conditions\MatchRequestParam::class,
		Conditions\MatchRequestParamFileUploads::class,
		Conditions\MatchRequestParamPost::class,
		Conditions\MatchRequestParamQuery::class,
		Conditions\MatchRequestPath::class,
		Conditions\MatchRequestPaths::class,
		Conditions\MatchRequestScriptName::class,
		Conditions\MatchRequestUseragent::class,
		Conditions\MatchRequestUseragents::class,
		Conditions\RequestBypassesAllRestrictions::class,
		Conditions\RequestHasAnyParameters::class,
		Conditions\RequestHasPostParameters::class,
		Conditions\RequestHasQueryParameters::class,
		Conditions\RequestIsPathWhitelisted::class,
		Conditions\RequestIsServerLoopback::class,
		Conditions\RequestIsSiteBlockdownBlocked::class,
		Conditions\ShieldRestrictionsEnabled::class,
		Conditions\RequestIsTrustedBot::class,
		Conditions\RequestParameterExists::class,
		Conditions\RequestParameterValueMatches::class,
		Conditions\WpIsAdmin::class,
		Conditions\WpIsAjax::class,
		Conditions\WpIsPermalinksEnabled::class,
		Conditions\WpIsWpcli::class,
		Conditions\WpIsXmlrpc::class,
	];
	public const CONDITION_TYPE_NORMAL = 'normal';
	public const CONDITION_TYPE_REQUEST = 'request';
	public const CONDITION_TYPE_SHIELD = 'shield';
	public const CONDITION_TYPE_FS = 'filesystem';
	public const CONDITION_TYPE_USER = 'user';
	public const CONDITION_TYPE_WP = 'wordpress';
	public const CONDITION_TYPE_PROXYCHECK = 'proxycheck';

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

	public static function Types() :array {
		return \apply_filters( 'shield/rules/enum_types', [
			self::CONDITION_TYPE_REQUEST,
			self::CONDITION_TYPE_SHIELD,
			self::CONDITION_TYPE_USER,
			self::CONDITION_TYPE_WP,
			self::CONDITION_TYPE_FS,
		] );
	}
}