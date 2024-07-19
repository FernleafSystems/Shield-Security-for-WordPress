<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Admin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PluginsPageSupplements {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return is_admin() || is_network_admin();
	}

	protected function run() {
		add_filter( 'plugin_action_links_'.self::con()->base_file, [ $this, 'onWpPluginActionLinks' ], 50 );
		add_filter( 'plugin_row_meta', [ $this, 'onPluginRowMeta' ], 50, 2 );
		add_action( 'in_plugin_update_message-'.self::con()->base_file, function () {
			echo sprintf(
				' <span class="%s plugin_update_message">%s</span>',
				self::con()->getPluginPrefix(),
				__( 'Update Now To Keep Your Security Current With The Latest Features.', 'wp-simple-firewall' )
			);
		} );
	}

	/**
	 * @param array  $pluginMeta
	 * @param string $pluginFile
	 * @return array
	 */
	public function onPluginRowMeta( $pluginMeta, $pluginFile ) {
		if ( $pluginFile === self::con()->base_file ) {
			$template = '<strong><a href="%s" target="_blank">%s</a></strong>';
			foreach ( self::con()->cfg->plugin_meta as $slug => $href ) {
				$pluginMeta[ $slug ] = sprintf( $template, $href[ 'href' ], $href[ 'name' ] );
			}
		}
		return $pluginMeta;
	}

	/**
	 * @param array $actionLinks
	 * @return array
	 */
	public function onWpPluginActionLinks( $actionLinks ) {
		$con = self::con();
		$WP = Services::WpGeneral();

		if ( $con->comps->mu->isActiveMU() ) {
			foreach ( $actionLinks as $key => $actionHref ) {
				if ( \strpos( $actionHref, 'action=deactivate' ) ) {
					$actionLinks[ $key ] = sprintf( '<a href="%s">%s</a>',
						URL::Build( $WP->getAdminUrl_Plugins(), [
							'plugin_status' => 'mustuse'
						] ),
						__( 'Disable MU To Deactivate', 'wp-simple-firewall' )
					);
				}
			}
		}

		if ( $con->isValidAdminArea() ) {

			if ( \array_key_exists( 'edit', $actionLinks ) ) {
				unset( $actionLinks[ 'edit' ] );
			}

			$links = $con->cfg->action_links[ 'add' ];
			if ( \is_array( $links ) ) {

				$isPro = $con->isPremiumActive();
				$DP = Services::Data();
				$linkTemplate = '<a href="%s" target="%s" title="%s">%s</a>';
				foreach ( $links as $link ) {
					$link = \array_merge(
						[
							'highlight' => false,
							'show'      => 'always',
							'name'      => '',
							'title'     => '',
							'href'      => '',
							'target'    => '_top',
						],
						$link
					);

					$show = $link[ 'show' ];
					$show = ( $show == 'always' ) || ( $isPro && $show == 'pro' ) || ( !$isPro && $show == 'free' );
					if ( !$DP->isValidWebUrl( $link[ 'href' ] ) && \method_exists( $this, $link[ 'href' ] ) ) {
						$link[ 'href' ] = $this->{$link[ 'href' ]}();
					}

					if ( !$show || !$DP->isValidWebUrl( $link[ 'href' ] )
						 || empty( $link[ 'name' ] ) || empty( $link[ 'href' ] ) ) {
						continue;
					}

					$link[ 'name' ] = __( $link[ 'name' ], 'wp-simple-firewall' );

					$href = sprintf( $linkTemplate, $link[ 'href' ], $link[ 'target' ], $link[ 'title' ], $link[ 'name' ] );
					if ( $link[ 'highlight' ] ) {
						$href = sprintf( '<span style="font-weight: bold;">%s</span>', $href );
					}

					$actionLinks = \array_merge(
						[ $con->prefix( sanitize_key( $link[ 'name' ] ) ) => $href ],
						$actionLinks
					);
				}
			}
		}
		return $actionLinks;
	}
}