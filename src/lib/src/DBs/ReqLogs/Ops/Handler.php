<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

	public const TYPE_AJAX = 'A';
	public const TYPE_CRON = 'C';
	public const TYPE_COMMENT = 'M';
	public const TYPE_HTTP = 'H';
	public const TYPE_LOGIN = 'L';
	public const TYPE_2FA = '2';
	public const TYPE_REST = 'R';
	public const TYPE_WPCLI = 'W';
	public const TYPE_XMLRPC = 'X';

	public static function GetTypeName( string $type ) :string {
		switch ( $type ) {
			case Handler::TYPE_AJAX:
				$type = 'AJAX';
				break;
			case Handler::TYPE_COMMENT:
				$type = 'COMMENT';
				break;
			case Handler::TYPE_CRON:
				$type = 'CRON';
				break;
			case Handler::TYPE_LOGIN:
				$type = 'LOGIN';
				break;
			case Handler::TYPE_2FA:
				$type = '2FA';
				break;
			case Handler::TYPE_REST:
				$type = 'REST API';
				break;
			case Handler::TYPE_WPCLI:
				$type = 'WP-CLI';
				break;
			case Handler::TYPE_XMLRPC:
				$type = 'XML-RPC';
				break;

			case Handler::TYPE_HTTP:
			default:
				$type = 'HTTP';
				break;
		}
		return $type;
	}
}