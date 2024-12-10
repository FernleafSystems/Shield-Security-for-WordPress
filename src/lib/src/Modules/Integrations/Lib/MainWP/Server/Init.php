<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\MainWP\SitesListTableColumn;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data\SyncHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use MainWP\Dashboard\MainWP_Extensions_Groups;

class Init {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :string {
		$con = self::con();

		// TODO: Consider have an "error" screen message to show it's not enabled instead?
		if ( !$con->comps->opts_lookup->enabledIntegrationMainwp() ) {
			throw new \Exception( 'MainWP Extension is not enabled' );
		}

		$extensionsPage = $this->addOurExtension();

		$childEnabled = apply_filters( 'mainwp_extension_enabled_check', $con->getRootFile() );
		$key = $childEnabled[ 'key' ] ?? '';
		if ( empty( $key ) ) {
			throw new \Exception( 'No child key provided' );
		}

		if ( Controller::isMainWPServerVersionSupported() && $con->caps->canMainwpLevel1() ) {

			( new SyncHandler() )->execute();
			$this->attachSitesListingShieldColumn();
			$extensionsPage->execute();

			add_action( 'admin_init', function () {
				$this->blockPluginDisable();
			} );
		}

		return $key;
	}

	private function attachSitesListingShieldColumn() {
		add_filter( 'mainwp_sitestable_getcolumns', function ( $columns ) {

			// We double-check to ensure that our extension has been successfully registered by this stage.
			// Prevents a fatal error that can be caused if we can't get our extension data when the extension reg has failed.
			if ( self::con()->comps->mainwp->isServerExtensionLoaded() ) {
				$columns[ 'shield' ] = 'Shield';
				add_filter( 'mainwp_sitestable_item', function ( array $item ) {
					$item[ 'shield' ] = self::con()->action_router->render( SitesListTableColumn::SLUG, [
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
			$extensions[] = [
				'plugin'   => self::con()->getRootFile(),
				// while this is a "callback" field, a Closure isn't supported as it's serialized for DB storage. Sigh.
				'callback' => [ new MwpExtensionLoader(), 'run' ],
				'icon'     => self::con()->urls->forImage( 'pluginlogo_col_32x32.png' ),
			];
			return $extensions;
		} );

		// Here we add extra data to our extension that can't be added through the normal channel due to the way they've coded it.
		add_filter( "pre_update_option_mainwp_extensions", function ( $value ) {
			if ( \is_array( $value ) ) {
				foreach ( $value as $key => $ext ) {
					if ( ( $ext[ 'plugin' ] ?? '' ) === self::con()->getRootFile() ) {
						$value[ $key ][ 'description' ] = \implode( ' ', [
							'Shield Security for MainWP builds upon the already powerful security platform,',
							'helping you extend security management across your entire portfolio with ease.'
						] );
						$value[ $key ][ 'DocumentationURI' ] = self::con()->labels->url_helpdesk;
					}
				}
			}
			return $value;
		} );

		// Add Shield extension to the MainWP Security menu
		if ( \defined( 'MAINWP_VERSION' ) && \version_compare( MAINWP_VERSION, '5.1', '>' ) ) {
			add_filter( 'mainwp_plugins_install_checks', function ( $plugins ) {
				$plugins[] = [
					'page'     => 'Extensions-Wp-Simple-Firewall',
					'slug'     => self::con()->base_file,
					'slug_pro' => self::con()->base_file,
					'name'     => 'Shield Security',
				];
				return $plugins;
			} );
			add_action( 'mainwp_admin_menu', function () {
				if ( \class_exists( '\MainWP\Dashboard\MainWP_Extensions_Groups' ) ) {
					MainWP_Extensions_Groups::add_extension_menu( [
						'type'                 => 'extension',
						'title'                => esc_html__( 'Shield Security', 'wp-simple-fiewall' ),
						'slug'                 => self::con()->base_file,
						'parent_key'           => 'Extensions-Mainwp-Security',
						'ext_page'             => 'admin.php?page=Extensions-Wp-Simple-Firewall',
						'leftsub_order_level2' => 0,
						'level'                => 2,
						'active_path'          => [ 'Extensions-Wp-Simple-Firewall' => 'managesites' ],
					] );
				}
			}, 10, 2 );
		}

		return new ExtensionSettingsPage();
	}

	/**
	 * MainWP assumes that a MainWP Extension is only that. But we've integrated the extension as part of the
	 * Shield plugin, not as a separate entity. So when the admin unwittingly clicks "disable extension", they're
	 * actually disabling the entire plugin.
	 *
	 * We step in here and prevent this, and instead just disable the MainWP option within Shield.
	 * We also then return an error message outlining what's happened. If they want to actually disable
	 * the Shield plugin, they can do that, separately.
	 *
	 * 2024-08-12
	 * Since MainWP 5.1+ we had to change our approach as a particular filter was eliminated. Now we just query the
	 * actual raw POST data to see if it's a match.  We also copy their nonce verification.
	 */
	private function blockPluginDisable() {
		$con = self::con();
		$req = Services::Request();
		if ( $con->this_req->wp_is_ajax && !empty( $req->post( 'security' ) ) ) {
			$requestIsAMatch = true;
			foreach (
				[
					'action' => 'mainwp_extension_plugin_action',
					'slug'   => $con->base_file,
					'what'   => 'disable',
				] as $key => $value
			) {
				if ( $req->post( $key ) !== $value ) {
					$requestIsAMatch = false;
					break;
				}
			}

			if ( $requestIsAMatch && wp_verify_nonce( sanitize_key( $req->post( 'security' ) ), 'mainwp_extension_plugin_action' ) ) {
				$con->opts->optSet( 'enable_mainwp', 'N' );
				wp_send_json( [
					'error' => sprintf( 'The MainWP integration within %s has been disabled.', $con->labels->Name )
							   .' '.sprintf( "You'll need to re-enable the option to view the %s extension on this page again.", $con->labels->Name )
				] );
				die();
			}
		}
	}
}