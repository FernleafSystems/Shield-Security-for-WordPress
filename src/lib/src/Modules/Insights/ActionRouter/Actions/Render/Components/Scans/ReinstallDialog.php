<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class ReinstallDialog extends BaseScans {

	use SecurityAdminNotRequired;

	const SLUG = 'render_reinstall_dialog';
	const TEMPLATE = '/snippets/dialog_plugins_reinstall.twig';

	protected function getRenderData() :array {
		return [
			'strings'     => [
				'are_you_sure'       => __( 'Are you sure?', 'wp-simple-firewll' ),
				'really_reinstall'   => __( 'Really Re-Install Plugin', 'wp-simple-firewll' ),
				'wp_reinstall'       => __( 'WordPress will now download and install the latest available version of this plugin.', 'wp-simple-firewll' ),
				'in_case'            => sprintf( '%s: %s',
					__( 'Note', 'wp-simple-firewall' ),
					__( 'In case of possible failure, it may be better to do this while the plugin is inactive.', 'wp-simple-firewll' )
				),
				'reinstall_first'    => __( 'Re-install first?', 'wp-simple-firewall' ),
				'corrupted'          => __( "This ensures files for this plugin haven't been corrupted in any way.", 'wp-simple-firewall' ),
				'choose'             => __( "You can choose to 'Activate Only' (not recommended), or close this message to cancel activation.", 'wp-simple-firewall' ),
				'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
				'download'           => sprintf(
					__( 'For best security practices, %s will download and re-install the latest available version of this plugin.', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName()
				)
			],
			'js_snippets' => []
		];
	}
}