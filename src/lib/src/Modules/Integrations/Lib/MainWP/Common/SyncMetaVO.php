<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;

/**
 * Class SyncVO - property should align with Sync Meta
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common
 * @property bool   $is_pro
 * @property bool   $is_mainwp_on
 * @property int    $installed_at
 * @property int    $sync_at
 * @property string $version
 * @property bool   $has_update
 */
class SyncMetaVO {

	use DynProperties;
}