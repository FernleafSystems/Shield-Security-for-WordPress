<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class EnumConditions {

	private const CONDITIONS = [
		Conditions\AccessiblePathExists::class,
		Conditions\DirContainsFile::class,
		Conditions\IsAdeScore::class,
		Conditions\IsBotOfType::class,
		Conditions\ShieldIsForceOff::class,
		Conditions\IsIpBlacklisted::class,
		Conditions\IsIpBlockedAuto::class,
		Conditions\IsIpBlockedByShield::class,
		Conditions\IsIpBlockedCrowdsec::class,
		Conditions\IsIpBlockedManual::class,
		Conditions\IsIpHighReputation::class,
		Conditions\IsIpValidPublic::class,
		Conditions\IsIpWhitelisted::class,
		Conditions\IsLoggedInNormal::class,
		Conditions\IsPhpCli::class,
		Conditions\IsPluginActive::class,
		Conditions\IsPluginInstalled::class,
		Conditions\IsThemeActive::class,
		Conditions\IsThemeInstalled::class,
		Conditions\IsRateLimitExceeded::class,
		Conditions\IsRequestSecurityAdmin::class,
		Conditions\IsRequestStatus404::class,
		Conditions\IsRequestToLoginPage::class,
		Conditions\IsRequestToInvalidPlugin::class,
		Conditions\IsRequestToInvalidTheme::class,
		Conditions\IsRequestToPluginAsset::class,
		Conditions\IsRequestToThemeAsset::class,
		Conditions\IsRestApiRequestToNamespace::class,
		Conditions\IsRestApiRequestToRoute::class,
		Conditions\IsRestApiRequestAuthenticated::class,
		Conditions\IsRequestWhitelisted::class,
		Conditions\IsServerLoad::class,
		Conditions\ShieldConfigIsLiveLoggingEnabled::class,
		Conditions\ShieldConfigPluginGlobalDisabled::class,
		Conditions\ShieldConfigIsTrafficRateLimitingEnabled::class,
		Conditions\ShieldConfigIsSiteLockdownActive::class,
		Conditions\IsUserAdminNormal::class,
		Conditions\IsUserId::class,
		Conditions\IsUserPasswordExpired::class,
		Conditions\IsUserSecurityAdmin::class,
		Conditions\UserHasWpCapability::class,
		Conditions\UserHasWpRole::class,
		Conditions\MatchRequestCountryCode::class,
		Conditions\MatchRequestHostname::class,
		Conditions\MatchRequestIpAddress::class,
		Conditions\MatchRequestIpIdentity::class,
		Conditions\MatchRequestMethod::class,
		Conditions\MatchRequestParamFileUploads::class,
		Conditions\MatchRequestPath::class,
		Conditions\MatchRequestScriptName::class,
		Conditions\MatchRequestUseragent::class,
		Conditions\MatchUserEmail::class,
		Conditions\MatchUserMeta::class,
		Conditions\MatchUsername::class,
		Conditions\PhpDefineIs::class,
		Conditions\RequestBypassesAllRestrictions::class,
		Conditions\RequestHasAnyParameters::class,
		Conditions\RequestHasPostParameters::class,
		Conditions\RequestHasQueryParameters::class,
		Conditions\RequestIsHttps::class,
		Conditions\RequestIsPathWhitelisted::class,
		Conditions\RequestIsServerLoopback::class,
		Conditions\RequestIsSiteBlockdownBlocked::class,
		Conditions\ShieldConfigurationOption::class,
		Conditions\ShieldRestrictionsEnabled::class,
		Conditions\UserSessionDuration::class,
		Conditions\UserSessionTokenDuration::class,
		Conditions\ShieldSessionParameterValueMatches::class,
		Conditions\ShieldUser2faProviderIsEnabled::class,
		Conditions\ShieldUser2faHasActive::class,
		Conditions\ShieldUser2faFactorIsActive::class,
		Conditions\IsTrustedBot::class,
		Conditions\RequestParameterExists::class,
		Conditions\RequestParameterValueMatches::class,
		Conditions\IsWpSearch::class,
		Conditions\WpIsAdmin::class,
		Conditions\WpIsAjax::class,
		Conditions\WpIsCron::class,
		Conditions\WpIsDebug::class,
		Conditions\WpIsPermalinksEnabled::class,
		Conditions\WpIsWpcli::class,
		Conditions\WpIsXmlrpc::class,
	];
	public const CONDITION_TYPE_BOTS = 'bots';
	public const CONDITION_TYPE_NORMAL = 'normal';
	public const CONDITION_TYPE_FS = 'filesystem';
	public const CONDITION_TYPE_PHP = 'php';
	public const CONDITION_TYPE_REQUEST = 'request';
	public const CONDITION_TYPE_SESSION = 'session';
	public const CONDITION_TYPE_SHIELD = 'shield';
	public const CONDITION_TYPE_SYSTEM = 'system';
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
			self::CONDITION_TYPE_BOTS,
			self::CONDITION_TYPE_REQUEST,
			self::CONDITION_TYPE_USER,
			self::CONDITION_TYPE_SESSION,
			self::CONDITION_TYPE_SHIELD,
			self::CONDITION_TYPE_WP,
			self::CONDITION_TYPE_PHP,
			self::CONDITION_TYPE_SYSTEM,
			self::CONDITION_TYPE_FS,
		] );
	}
}