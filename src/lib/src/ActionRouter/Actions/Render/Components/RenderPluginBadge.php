<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	PluginBadgeClose,
	Render\BaseRender,
	Traits
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class RenderPluginBadge extends BaseRender {

	use Traits\AuthNotRequired;

	public const SLUG = 'render_plugin_badge';
	public const TEMPLATE = '/snippets/plugin_badge_widget.twig';

	protected function getRenderData() :array {
		$con = $this->con();
		$wlCon = $con->getModule_SecAdmin()->getWhiteLabelController();

		if ( $wlCon->isEnabled() && $wlCon->isReplacePluginBadge() ) {
			/** @var Options $secAdminOpts */
			$secAdminOpts = $con->getModule_SecAdmin()->getOptions();
			$badgeUrl = $secAdminOpts->getOpt( 'wl_homeurl' );
			$name = $secAdminOpts->getOpt( 'wl_pluginnamemain' );
			$logo = $secAdminOpts->getOpt( 'wl_dashboardlogourl' );
		}
		else {
			$badgeUrl = 'https://shsec.io/wpsecurityfirewall';
			$name = $con->getHumanName();
			$logo = $con->urls->forImage( 'shield/shield-security-logo-colour-32px.png' );

			$lic = $con->getModule_License()
					   ->getLicenseHandler()
					   ->getLicense();
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
			'ajax'    => [
				'plugin_badge_close' => ActionData::BuildJson( PluginBadgeClose::class ),
			],
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
				'protected' => $badgeAttrs[ 'protected_by' ],
				'name'      => $badgeAttrs[ 'name' ],
			],
		];
	}
}