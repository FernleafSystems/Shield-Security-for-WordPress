<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Lockdown;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class UI extends Base\ShieldUI {

	/**
	 * @return array
	 */
	public function getInsightsNoticesData() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Lockdown $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$notices = [
			'title'    => __( 'WP Lockdown', 'wp-simple-firewall' ),
			'messages' => []
		];

		{ //edit plugins
			$bEditingDisabled = $opts->isOptFileEditingDisabled() || !current_user_can( 'edit_plugins' );
			if ( !$bEditingDisabled ) { //assumes current user is admin
				$notices[ 'messages' ][ 'disallow_file_edit' ] = [
					'title'   => __( 'File Editing via WP', 'wp-simple-firewall' ),
					'message' => __( 'Direct editing of plugin/theme files is permitted.', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'disable_file_editing' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'WP Plugin file editing should be disabled.', 'wp-simple-firewall' )
				];
			}
		}

		return $notices;
	}
}