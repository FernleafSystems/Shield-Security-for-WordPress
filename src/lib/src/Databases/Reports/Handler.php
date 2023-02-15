<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public const TYPE_ALERT = 'alt';
	public const TYPE_INFO = 'nfo';
}