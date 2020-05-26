<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\Actions\RemoveSecAdmin;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\Actions\SetSecAdminPin;
use WP_CLI;

class WpCli extends Base\WpCli {

	const MOD_COMMAND_KEY = 'secadmin';

}