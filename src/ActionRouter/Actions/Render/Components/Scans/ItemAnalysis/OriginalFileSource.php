<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

/**
 * Where an official copy of a scanned file could come from, resolved without any network request. Whether a theme
 * is hosted on WordPress.org can only be learned by asking it, so that is left to the Diff tab.
 */
class OriginalFileSource {

	public const KIND_CORE = 'core';
	public const KIND_WPORG_PLUGIN = 'wporg_plugin';
	public const KIND_PREMIUM_PLUGIN = 'premium_plugin';
	public const KIND_THEME = 'theme';
	public const KIND_NONE = 'none';

	public string $kind;

	public string $assetName;

	public string $assetVersion;

	/**
	 * @var WpPluginVo|WpThemeVo|null
	 */
	public $asset;

	/**
	 * @param WpPluginVo|WpThemeVo|null $asset
	 */
	public function __construct( string $kind, string $assetName, string $assetVersion, $asset = null ) {
		$this->kind = $kind;
		$this->assetName = $assetName;
		$this->assetVersion = $assetVersion;
		$this->asset = $asset;
	}

	public static function forItem( ResultItem $item ) :self {
		if ( $item->is_in_plugin ) {
			$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $item->path_full );
			if ( $plugin instanceof WpPluginVo ) {
				return new self(
					$plugin->isWpOrg() ? self::KIND_WPORG_PLUGIN : self::KIND_PREMIUM_PLUGIN,
					(string)$plugin->Name,
					(string)$plugin->Version,
					$plugin
				);
			}
		}
		elseif ( $item->is_in_theme ) {
			$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $item->path_full );
			if ( $theme instanceof WpThemeVo ) {
				return new self( self::KIND_THEME, (string)$theme->Name, (string)$theme->Version, $theme );
			}
		}
		elseif ( $item->is_in_core && !$item->is_in_wproot && !$item->is_in_wpcontent ) {
			return new self( self::KIND_CORE, 'WordPress', Services::WpGeneral()->getVersion() );
		}

		return new self( self::KIND_NONE, '', '' );
	}

	public function label() :string {
		switch ( $this->kind ) {
			case self::KIND_CORE:
				/* translators: %s: WordPress version */
				return sprintf( __( 'WordPress %s core', 'wp-simple-firewall' ), $this->assetVersion );
			case self::KIND_WPORG_PLUGIN:
				/* translators: %1$s: plugin name, %2$s: plugin version */
				return sprintf( __( '%1$s %2$s (plugin from WordPress.org)', 'wp-simple-firewall' ), $this->assetName, $this->assetVersion );
			case self::KIND_PREMIUM_PLUGIN:
				/* translators: %1$s: plugin name, %2$s: plugin version */
				return sprintf( __( '%1$s %2$s (premium or custom plugin)', 'wp-simple-firewall' ), $this->assetName, $this->assetVersion );
			case self::KIND_THEME:
				/* translators: %1$s: theme name, %2$s: theme version */
				return sprintf( __( '%1$s %2$s (theme)', 'wp-simple-firewall' ), $this->assetName, $this->assetVersion );
			default:
				return '';
		}
	}
}
