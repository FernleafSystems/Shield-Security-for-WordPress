<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class ReinstallDialog extends BaseScans {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_reinstall_dialog';
	public const TEMPLATE = '/snippets/dialog_plugins_reinstall.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'really_reinstall'   => __( 'Really Re-Install Plugin', 'wp-simple-firewall' ),
				'wp_reinstall'       => __( 'WordPress will now download and install the latest available version of this plugin.', 'wp-simple-firewall' ),
				'in_case'            => sprintf( '%s: %s',
					__( 'Note', 'wp-simple-firewall' ),
					__( 'In case of possible failure, it may be better to do this while the plugin is inactive.', 'wp-simple-firewall' )
				),
				'okay_reinstall'     => sprintf( '%s, %s', __( 'Yes', 'wp-simple-firewall' ), __( 'Re-Install It', 'wp-simple-firewall' ) ),
				'reinstall_first'    => __( 'Re-install first?', 'wp-simple-firewall' ),
				'corrupted'          => __( "This ensures files for this plugin haven't been corrupted in any way.", 'wp-simple-firewall' ),
				'choose'             => __( "You can choose to 'Activate Only' (not recommended), or close this message to cancel activation.", 'wp-simple-firewall' ),
				'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
				'activate_only'      => __( 'Activate Only', 'wp-simple-firewall' ),
				'reinstall_activate' => __( 'Re-install First', 'wp-simple-firewall' ).'. '.__( 'Then Activate', 'wp-simple-firewall' ),
				'cancel'             => __( 'Cancel', 'wp-simple-firewall' ),
				'download'           => sprintf(
					__( 'For best security practices, %s will download and re-install the latest available version of this plugin.', 'wp-simple-firewall' ), self::con()->labels->Name
				)
			],
		];
	}
}