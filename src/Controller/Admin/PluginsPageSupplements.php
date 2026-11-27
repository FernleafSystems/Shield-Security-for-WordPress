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
				esc_attr( self::con()->getPluginPrefix() ),
				esc_html__( 'Update Now To Keep Your Security Current With The Latest Features.', 'wp-simple-firewall' )
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
						esc_html__( 'Disable MU To Deactivate', 'wp-simple-firewall' )
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

					$link[ 'name' ] = __( $link[ 'name' ], 'wp-simple-firewall' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Plugin page link names come from plugin.json, so they can't be literals here. The generated translations-strings-auto.php is how they reach the translation template.

					// The name can be translated text, so it's escaped; esc_html() keeps the config's entities, like &uarr;.
					$href = sprintf(
						$linkTemplate,
						esc_url( $link[ 'href' ] ),
						esc_attr( $link[ 'target' ] ),
						esc_attr( $link[ 'title' ] ),
						esc_html( $link[ 'name' ] )
					);
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