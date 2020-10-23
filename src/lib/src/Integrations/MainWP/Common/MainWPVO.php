<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class MainwpVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common
 * @property string $child_key
 * @property bool   $is_client
 * @property bool   $is_server
 */
class MainWPVO {

	use StdClassAdapter;
}