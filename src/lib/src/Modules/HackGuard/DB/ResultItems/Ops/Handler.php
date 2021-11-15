<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;

class Handler extends Base\Handler {

	const ITEM_TYPE_FILE = 'f';
	const ITEM_TYPE_PLUGIN = 'p';
	const ITEM_TYPE_THEME = 't';
}