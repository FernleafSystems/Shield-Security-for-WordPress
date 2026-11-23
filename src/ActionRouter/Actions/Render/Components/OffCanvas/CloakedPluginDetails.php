<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\CloakedPluginsQueueIssueProvider;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

/**
 * One cloaked plugin, opened from its row in the Actions Queue: what was found, the file, what the file says about
 * itself, and what can be done about it.
 */
class CloakedPluginDetails extends OffCanvasBase {

	use SecurityAdminRequired;

	public const SLUG = 'offcanvas_cloaked_plugin';

	protected function buildCanvasTitle() :string {
		return __( 'Cloaked Plugin', 'wp-simple-firewall' );
	}

	protected function buildCanvasBody() :string {
		return self::con()
				   ->comps
				   ->render
				   ->setTemplate( '/wpadmin/components/actions_queue/cloaked_plugin_drawer.twig' )
				   ->setData( [
					   'strings' => [
						   'gone'          => __( 'This plugin is no longer cloaked.', 'wp-simple-firewall' ),
						   'why'           => __( 'Why Shield flagged it', 'wp-simple-firewall' ),
						   'file'          => __( 'The file', 'wp-simple-firewall' ),
						   'claims'        => __( 'What the file says about itself', 'wp-simple-firewall' ),
						   'claims_note'   => __( "These details are read from the plugin's own file. Anyone can write anything there, so don't rely on them.", 'wp-simple-firewall' ),
						   'ignored'       => __( 'Ignored', 'wp-simple-firewall' ),
						   'what_to_do'    => __( 'What you can do', 'wp-simple-firewall' ),
					   ],
					   'vars'    => [
						   'plugin' => ( new CloakedPluginsQueueIssueProvider() )->drawerData( $this->fields()->string( 'identifier', '' ) ),
					   ],
				   ] )
				   ->render();
	}
}
