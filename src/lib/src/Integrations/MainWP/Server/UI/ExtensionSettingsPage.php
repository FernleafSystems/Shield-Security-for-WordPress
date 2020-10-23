<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\UI;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use MainWP\Dashboard\MainWP_DB;

class ExtensionSettingsPage {

	use PluginControllerConsumer;

	public function render() {
		$con = $this->getCon();
		do_action( 'mainwp_pageheader_extensions', $this->getCon()->getRootFile() );

		$sites = apply_filters( 'mainwp_getsites', $con->getRootFile(), $this->childKey );
		var_dump( $sites );
		?>
		https://mainwp.com/passing-information-to-your-child-sites/
		<div id="uploader_select_sites_box" class="mainwp_config_box_right">
        <?php
		do_action( 'mainwp_select_sites_box', __( "Select Sites", 'mainwp' ), 'checkbox', true, true, 'mainwp_select_sites_box_right', "", [], [] );
		?></div>
		<?php

		do_action( 'mainwp_pagefooter_extensions', $con->getRootFile() );
	}
}
