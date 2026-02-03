<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Components {

	use PluginControllerConsumer;

	public const COMPONENTS = [
		Component\AllComponents::class,
		/** SPECIAL */

		Component\ActivityLogEnabled::class,
		Component\AdeLogin::class,
		Component\AdeLostPassword::class,
		Component\AdeRegister::class,
		Component\IpAdeThreshold::class,
		Component\CommentApprovedMinimum::class,
		Component\CommentSpamAntibot::class,
		Component\CommentSpamHuman::class,
		Component\ContactFormSpam::class,
		Component\FirewallAggressive::class,
		Component\FirewallDirTraversal::class,
		Component\FirewallFieldTruncation::class,
		Component\FirewallPhpCode::class,
		Component\FirewallSqlQueries::class,
		Component\HttpHeaders::class,
		Component\IpAddressSource::class,
		Component\IpAutoBlockShield::class,
		Component\IpAutoBlockOffenseLimit::class,
		Component\IpAutoBlockCrowdsec::class,
		Component\IpTrackSignal404::class,
		Component\IpTrackSignalFakeWebcrawler::class,
		Component\IpTrackSignalInvalidScript::class,
		Component\IpTrackSignalLinkCheese::class,
		Component\IpTrackSignalLoginFailed::class,
		Component\IpTrackSignalLoginInvalid::class,
		Component\IpTrackSignalXmlrpc::class,
		Component\LockdownAnonymousRestApi::class,
		Component\LockdownAuthorDiscovery::class,
		Component\LockdownFileEditing::class,
		Component\LockdownXmlrpc::class,
		Component\Login2fa::class,
		Component\LoginCooldown::class,
		Component\LoginFormThirdParties::class,
		Component\PluginBadge::class,
		Component\PluginReportEmail::class,
		Component\ScanEnabledAfsAreaWpCore::class,
		Component\ScanEnabledAfsAreaPlugins::class,
		Component\ScanEnabledAfsAreaThemes::class,
		Component\ScanEnabledAfsAreaWpContent::class,
		Component\ScanEnabledAfsAreaWpRoot::class,
		Component\ScanEnabledApc::class,
		Component\ScanEnabledMal::class,
		Component\ScanEnabledWpv::class,
		Component\ScanEnabledWpvAutoupdate::class,
		Component\ScanEnabledAfsAutoRepairCore::class,
		Component\ScanEnabledAfsAutoRepairPlugins::class,
		Component\ScanEnabledAfsAutoRepairThemes::class,
		Component\ScanEnabledFileLockerHtaccess::class,
		Component\ScanEnabledFileLockerIndex::class,
		Component\ScanEnabledFileLockerWpconfig::class,
		Component\ScanEnabledFileLockerWebconfig::class,
		Component\ScanFrequency::class,
		Component\ScanResultsApc::class,
		Component\ScanResultsMal::class,
		Component\ScanResultsPtg::class,
		Component\ScanResultsWcf::class,
		Component\ScanResultsWpv::class,
		Component\SecurityAdmin::class,
		Component\SecurityAdminAdmins::class,
		Component\SecurityAdminOptions::class,
		Component\ShieldPro::class,
		Component\SystemSslCertificate::class,
		Component\SystemLibOpenssl::class,
		Component\SystemPhpVersion::class,
		Component\TrafficLogEnabled::class,
		Component\TrafficRateLimiting::class,
		Component\UserAdminExists::class,
		Component\UserEmailValidation::class,
		Component\UserPasswordPolicies::class,
		Component\UserPasswordPwned::class,
		Component\UserPasswordStrength::class,
		Component\UserSuspendInactive::class,
		Component\WpDbPassword::class,
		Component\WpUpdates::class,
		Component\WpPluginsInactive::class,
		Component\WpPluginsUpdates::class,
		Component\WpThemesInactive::class,
		Component\WpThemesUpdates::class,
	];

	private static $built;

	public function __construct() {
		if ( !\is_array( self::$built ) ) {
			self::$built = [];
		}
	}

	/**
	 * @throws \Exception
	 */
	public function buildComponent( string $class ) :array {
		if ( !\is_array( self::$built[ $class ] ?? null ) ) {
			if ( !\in_array( $class, self::COMPONENTS ) ) {
				throw new \Exception( sprintf( 'Invalid component class: %s', $class ) );
			}
			/** @var Component\Base $compObj */
			$compObj = new $class();
			self::$built[ $class ] = $compObj->build();
		}
		return self::$built[ $class ];
	}
}