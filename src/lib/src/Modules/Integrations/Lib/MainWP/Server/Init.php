<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\SitesListTableColumn;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\SyncHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Init {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :string {
		$con = $this->getCon();
		/** @var Options $optsInt */
		$optsInt = $con->getModule_Integrations()->getOptions();

		// TODO: Consider have an "error" screen message to show it's not enabled instead?
		if ( !$optsInt->isEnabledMainWP() ) {
			throw new \Exception( 'MainWP Extension is not enabled' );
		}

		$extensionsPage = $this->addOurExtension();

		$childEnabled = apply_filters( 'mainwp_extension_enabled_check', $con->getRootFile() );
		$key = $childEnabled[ 'key' ] ?? '';
		if ( empty( $key ) ) {
			throw new \Exception( 'No child key provided' );
		}

		if ( Controller::isMainWPServerVersionSupported() && $con->isPremiumActive() ) {

			if ( $con->isPremiumActive() ) {
				( new SyncHandler() )
					->setMod( $this->getMod() )
					->execute();
				$this->attachSitesListingShieldColumn();
				$extensionsPage->execute();
			}

			add_action( 'mainwp_secure_check_admin_referer_is_accepted', function ( $isRequestAccepted ) {
				$this->blockPluginDisable( $isRequestAccepted );
			} );
		}

		return $key;
	}

	private function attachSitesListingShieldColumn() {
		add_filter( 'mainwp_sitestable_getcolumns', function ( $columns ) {

			// We double-check to ensure that our extension has been successfully registered by this stage.
			// Prevents a fatal error that can be caused if we can't get our extension data when the extension reg has failed.
			if ( $this->getCon()->getModule_Integrations()->getControllerMWP()->isServerExtensionLoaded() ) {
				$columns[ 'shield' ] = 'Shield';
				add_filter( 'mainwp_sitestable_item', function ( array $item ) {
					$item[ 'shield' ] = $this->getCon()->action_router->render( SitesListTableColumn::SLUG, [
						'raw_mainwp_site_data' => $item
					] );
					return $item;
				} );
			}

			return $columns;
		} );
	}

	private function addOurExtension() :ExtensionSettingsPage {

		// We must create a simple class (MwpExtensionLoader) without any ModConsumer so that it can be reliably stored
		// in the MainWP extensions option. Previously the saving wasn't working and the extension wouldn't appear.
		add_filter( 'mainwp_getextensions', function ( $extensions ) {
			$con = $this->getCon();
			$extensions[] = [
				'plugin'   => $con->getRootFile(),
				// while this is a "callback" field, a Closure isn't supported as it's serialized for DB storage. Sigh.
				'callback' => [ new MwpExtensionLoader(), 'run' ],
				'icon'     => $con->urls->forImage( 'pluginlogo_col_32x32.png' ),
			];
			return $extensions;
		} );

		// Here we add extra data to our extension that can't be added through the normal channel due to the way they've coded it.
		add_filter( "pre_update_option_mainwp_extensions", function ( $value ) {
			if ( is_array( $value ) ) {
				$con = $this->getCon();
				foreach ( $value as $key => $ext ) {
					if ( ( $ext[ 'plugin' ] ?? '' ) === $con->getRootFile() ) {
						$value[ $key ][ 'description' ] = implode( ' ', [
							'Shield Security for MainWP builds upon the already powerful security platform,',
							'helping you extend security management across your entire portfolio with ease.'
						] );
						$value[ $key ][ 'DocumentationURI' ] = $con->labels->url_helpdesk;
					}
				}
			}
			return $value;
		} );

		return ( new ExtensionSettingsPage() )->setMod( $this->getMod() );
	}

	/**
	 * MainWP assumes that a MainWP Extension is only that. But we've integrated the extension as part of the
	 * Shield plugin, not as a separate entity. So when the admin unwittingly clicks "disable extension", they're
	 * actually disabling the entire plugin.
	 *
	 * We step in here and prevent this, and instead just disable the MainWP option within Shield.
	 */
	private function blockPluginDisable( $isRequestAccepted ) {
		if ( $isRequestAccepted ) {
			$con = $this->getCon();
			$req = Services::Request();
			if ( $req->post( 'action' ) === 'mainwp_extension_plugin_action'
				 && $req->post( 'what' ) === 'disable'
				 && $req->post( 'slug' ) === $con->base_file ) {
				$this->getMod()->getOptions()->setOpt( 'enable_mainwp', 'N' );
				wp_send_json( [
					'error' => sprintf( 'The MainWP integration option within the %s plugin has been disabled.',
							$con->getHumanName() )
							   .' '.sprintf( "You'll need to re-enable the option to view the %s extension on this page again.",
							$con->getHumanName() )
				] );
				die();
			}
		}
		return $isRequestAccepted;
	}
}