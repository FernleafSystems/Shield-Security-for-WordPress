<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Enum;

class EnumModules {

	public const SECURITY_ADMIN = 'admin_access_restriction';
	public const ACTIVITY = 'audit_trail';
	public const COMMENTS = 'comments_filter';
	public const FIREWALL = 'firewall';
	public const SCANS = 'hack_protect';
	public const INTEGRATIONS = 'integrations';
	public const IPS = 'ips';
	public const LOGIN = 'login_protect';
	public const PLUGIN = 'plugin';
	public const USERS = 'user_management';
	// @deprecated 19.2
	public const AUTOUPDATES = 'autoupdates';
	public const DATA = 'data';
	public const HEADERS = 'headers';
	public const LICENSE = 'license';
	public const LOCKDOWN = 'lockdown';
	public const TRAFFIC = 'traffic';

	public static function All() :array {
		return [
			self::SECURITY_ADMIN,
			self::ACTIVITY,
			self::COMMENTS,
			self::FIREWALL,
			self::SCANS,
			self::INTEGRATIONS,
			self::IPS,
			self::LOGIN,
			self::PLUGIN,
			self::USERS,
		];
	}
}