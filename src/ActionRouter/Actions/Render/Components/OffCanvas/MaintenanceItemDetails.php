<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\OperatorChromeContract;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

/**
 * One to-do, opened from its row in the Actions Queue: where it stands, what its check looks at, why that matters,
 * and what can be done about it. A check that's passing explains itself the same way, without the steps.
 */
class MaintenanceItemDetails extends OffCanvasBase {

	use SecurityAdminRequired;

	public const SLUG = 'offcanvas_maintenance_item';

	/**
	 * The request a queue row makes for the drawer of the to-do it belongs to.
	 */
	public static function requestJsonFor( string $maintenanceKey ) :string {
		return OperatorChromeContract::encodeJson( ActionData::BuildAjaxRender( self::class, [
			'maintenance_key' => $maintenanceKey,
		] ) );
	}

	protected function buildCanvasTitle() :string {
		return __( 'About This Check', 'wp-simple-firewall' );
	}

	protected function buildCanvasBody() :string {
		// The to-do as the queue has it now, which may be not at all.
		$key = $this->fields()->string( 'maintenance_key', '' );
		$provider = new MaintenanceIssueStateProvider();
		$state = $provider->buildStates()[ $key ] ?? null;
		$guidance = $state === null ? null : $provider->guidanceFor( $key );

		return self::con()
				   ->comps
				   ->render
				   ->setTemplate( '/wpadmin/components/actions_queue/maintenance_item_drawer.twig' )
				   ->setData( [
					   'strings' => [
						   'gone'       => __( 'This item is no longer in the Actions Queue.', 'wp-simple-firewall' ),
						   'checks'     => __( 'What this checks', 'wp-simple-firewall' ),
						   'why'        => __( 'Why it matters', 'wp-simple-firewall' ),
						   'what_to_do' => __( 'What you can do', 'wp-simple-firewall' ),
						   'practice'   => __( 'Good practice', 'wp-simple-firewall' ),
					   ],
					   'vars'    => [
						   'item' => $guidance === null ? null : [
							   'title'      => $state[ 'label' ],
							   'icon_class' => $state[ 'icon_class' ],
							   'status'     => $state[ 'description' ],
							   // Something ignored is still there to be put right.
							   'has_issue'  => $state[ 'count' ] > 0 || $state[ 'ignored_count' ] > 0,
							   'guidance'   => $guidance,
						   ],
					   ],
				   ] )
				   ->render();
	}
}
