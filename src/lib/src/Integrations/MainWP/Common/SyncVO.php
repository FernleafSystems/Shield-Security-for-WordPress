<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class SyncVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common
 * @property int    $installed_at
 * @property int    $sync_at
 * @property string $version
 * @property bool   $has_update
 */
class SyncVO {

	use StdClassAdapter;
}