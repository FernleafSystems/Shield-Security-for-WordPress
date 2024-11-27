<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class RenderPluginBadge extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use AuthNotRequired;

	public const SLUG = 'render_plugin_badge';
	public const TEMPLATE = '/snippets/plugin_badge_widget.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$opts = $con->opts;

		if ( $con->comps->whitelabel->isEnabled() ) {
			$badgeUrl = $opts->optGet( 'wl_homeurl' );
			$name = $opts->optGet( 'wl_pluginnamemain' );
			$logo = $opts->optGet( 'wl_dashboardlogourl' );
		}
		else {
			$badgeUrl = 'https://clk.shldscrty.com/wpsecurityfirewall';
			$name = $con->labels->Name;
			$logo = $con->urls->forImage( 'shield/shield-security-logo-colour-32px.png' );

			$lic = $con->comps->license->getLicense();
			if ( !empty( $lic->aff_ref ) ) {
				$badgeUrl = URL::Build( $badgeUrl, [ 'ref' => $lic->aff_ref ] );
			}
		}

		$protectedBy = sprintf( __( 'This Site Is Protected By %s', 'wp-simple-firewall' ),
			'<br/><span class="plugin-badge-name">'.$name.'</span>' );

		$badgeAttrs = [
			'name'         => $name,
			'url'          => $badgeUrl,
			'logo'         => $logo,
			'protected_by' => apply_filters( 'icwp_shield_plugin_badge_text', $protectedBy ),
			'custom_css'   => '',
			'nofollow'     => false,
		];
		if ( $con->isPremiumActive() ) {
			$filteredBadgeAttrs = apply_filters( 'shield/plugin_badge_attributes',
				/** @deprecated */
				apply_filters( 'icwp_shield_plugin_badge_attributes', $badgeAttrs, $this->action_data[ 'is_floating' ] ),
				$this->action_data[ 'is_floating' ]
			);
			if ( \is_array( $filteredBadgeAttrs ) ) {
				$badgeAttrs = $filteredBadgeAttrs;
			}
		}

		return [
			'content' => [
				'custom_css' => esc_js( $badgeAttrs[ 'custom_css' ] ),
			],
			'flags'   => [
				'nofollow'    => !empty( $badgeAttrs[ 'nofollow' ] ),
				'is_floating' => $this->action_data[ 'is_floating' ]
			],
			'hrefs'   => [
				'badge' => $badgeAttrs[ 'url' ],
				'logo'  => $badgeAttrs[ 'logo' ],
			],
			'strings' => [
				'alt'       => 'Powerful Protection for WordPress, from Shield Security',
				'protected' => $badgeAttrs[ 'protected_by' ],
				'name'      => $badgeAttrs[ 'name' ],
			],
		];
	}
}