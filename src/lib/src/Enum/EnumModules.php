<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Enum;

class EnumModules {

	public const SECURITY_ADMIN = 'admin_access_restriction';
	public const ACTIVITY = 'audit_trail';
	public const AUTOUPDATES = 'autoupdates';
	public const COMMENTS = 'comments_filter';
	public const DATA = 'data';
	public const FIREWALL = 'firewall';
	public const SCANS = 'hack_protect';
	public const HEADERS = 'headers';
	public const INTEGRATIONS = 'integrations';
	public const IPS = 'ips';
	public const LICENSE = 'license';
	public const LOCKDOWN = 'lockdown';
	public const LOGIN = 'login_protect';
	public const PLUGIN = 'plugin';
	public const TRAFFIC = 'traffic';
	public const USERS = 'user_management';
}