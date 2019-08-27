<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class NoticeVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities\Notices
 * @property string $id
 * @property bool   $display
 * @property string $non_display_reason
 * @property array  $render_data
 * @property string $template
 * @property string $schedule
 * @property string $type
 * @property bool   $plugin_page_only
 * @property string $plugin_admin     - show when plugin admin: yes, no, ignore
 * @property bool   $valid_admin
 * @property bool   $per_user
 * @property int    $min_install_days
 * @property bool   $twig
 */
class NoticeVO {

	use StdClassAdapter;
}