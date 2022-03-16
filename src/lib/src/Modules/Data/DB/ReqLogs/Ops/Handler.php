<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

class Handler extends Base\Handler {

	const TYPE_AJAX = 'AJAX';
	const TYPE_CRON = 'CRON';
	const TYPE_HTTP = 'HTTP';
	const TYPE_REST = 'REST';
	const TYPE_WPCLI = 'WPCLI';
	const TYPE_XMLRPC = 'XML';
}