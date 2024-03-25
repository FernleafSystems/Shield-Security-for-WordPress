<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

	public const ITEM_TYPE_FILE = 'f';
	public const ITEM_TYPE_PLUGIN = 'p';
	public const ITEM_TYPE_THEME = 't';
}