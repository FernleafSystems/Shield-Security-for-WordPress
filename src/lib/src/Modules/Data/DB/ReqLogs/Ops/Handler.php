<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

class Handler extends Base\Handler {

	const TYPE_AJAX = 'A';
	const TYPE_CRON = 'C';
	const TYPE_COMMENT = 'M';
	const TYPE_HTTP = 'H';
	const TYPE_LOGIN = 'L';
	const TYPE_2FA = '2';
	const TYPE_REST = 'R';
	const TYPE_WPCLI = 'W';
	const TYPE_XMLRPC = 'X';

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