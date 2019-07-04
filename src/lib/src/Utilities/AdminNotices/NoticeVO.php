<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class NoticeVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities\Notices
 * @property string $id
 * @property bool   $display
 * @property array  $render_data
 * @property string $template
 * @property string $schedule
 * @property string $type
 * @property bool   $plugin_page_only
 * @property bool   $plugin_admin_only
 * @property bool   $valid_admin
 * @property bool   $twig
 */
class NoticeVO {

	use StdClassAdapter;
}