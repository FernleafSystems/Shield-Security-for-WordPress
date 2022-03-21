<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string $id
 * @property string $mod
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
 * @property bool   $can_dismiss
 * @property int    $min_install_days
 * @property bool   $twig
 */
class NoticeVO extends DynPropertiesClass {

}