<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PluginBadgeMode;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class RenderPluginBadge extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use AuthNotRequired;

	public const SLUG = 'render_plugin_badge';
	public const TEMPLATE = '/snippets/plugin_badge_widget.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$isFloating = (bool)$this->action_data[ 'is_floating' ];
		$mode = PluginBadgeMode::renderMode( $con->comps->opts_lookup->getPluginBadgeMode() );

		if ( $con->comps->whitelabel->isEnabled() ) {
			$badgeUrl = $con->comps->whitelabel->homeUrl();
			$name = $con->comps->whitelabel->pluginName();
			$logo = $con->comps->whitelabel->dashboardLogoUrl();
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

		// The template prints this as HTML, so the name in it is escaped.
		$protectedBy = sprintf( __( 'Protected By %s', 'wp-simple-firewall' ),
			'<br/><span class="plugin-badge-name">'.esc_html( $name ).'</span>' );

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
				apply_filters( 'icwp_shield_plugin_badge_attributes', $badgeAttrs, $isFloating ),
				$isFloating
			);
			if ( \is_array( $filteredBadgeAttrs ) ) {
				$badgeAttrs = \array_merge( $badgeAttrs, $filteredBadgeAttrs );
			}
		}

		return [
			'content' => [
				'custom_css' => esc_js( $this->stringAttr( $badgeAttrs[ 'custom_css' ] ) ),
			],
			'flags'   => [
				'nofollow'    => !empty( $badgeAttrs[ 'nofollow' ] ),
				'is_floating' => $isFloating,
				'mode'        => $mode,
			],
			'hrefs'   => [
				// Only web addresses: a javascript: URL from a setting or a filter is dropped.
				'badge' => esc_url_raw( $this->stringAttr( $badgeAttrs[ 'url' ] ), [ 'http', 'https' ] ),
				'logo'  => esc_url_raw( $this->stringAttr( $badgeAttrs[ 'logo' ] ) ),
			],
			'strings' => [
				'badge_label' => sprintf( __( '%s security badge', 'wp-simple-firewall' ), $this->stringAttr( $badgeAttrs[ 'name' ] ) ),
				'close_label' => __( 'Close security badge', 'wp-simple-firewall' ),
				'link_label'  => sprintf( __( 'Learn more about %s security protection (opens in a new tab)', 'wp-simple-firewall' ), $this->stringAttr( $badgeAttrs[ 'name' ] ) ),
				'protected'   => $this->stringAttr( $badgeAttrs[ 'protected_by' ] ),
				'name'        => $this->stringAttr( $badgeAttrs[ 'name' ] ),
			],
		];
	}

	/**
	 * @param mixed $value
	 */
	private function stringAttr( $value ) :string {
		return \is_scalar( $value ) ? (string)$value : '';
	}
}
