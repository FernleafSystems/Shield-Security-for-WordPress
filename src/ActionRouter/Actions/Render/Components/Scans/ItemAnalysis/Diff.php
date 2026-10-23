<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\BaseScans;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\HashVerificationResult;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Util\Diff as DiffUtil;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

/**
 * Loaded on demand when its tab is opened, because building a diff downloads the original file from WordPress.org
 * and sends both copies to the WP Hashes diff service. States that can be decided locally are decided first.
 *
 * @phpstan-type DiffState array{
 *   flags:array{has_diff:bool,can_retry:bool},
 *   vars:array{tone:string,icon:string,title:string,lines:list<string>,legend:string,diff_css:string,diff_content:string},
 *   strings:array{retry:string,removed:string,added:string}
 * }
 */
class Diff extends BaseScans {

	use LoadsFileScanItem;

	public const SLUG = 'scanitemanalysis_diff';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_diff.twig';

	/**
	 * @return DiffState
	 */
	protected function getRenderData() :array {
		$item = $this->loadFileScanItem();

		if ( $item->is_missing || !Services::WpFs()->isAccessibleFile( $item->path_full ) ) {
			return $this->message( 'neutral', 'bi-file-earmark-x', __( 'This file no longer exists', 'wp-simple-firewall' ), [
				__( 'A diff can only be shown for a file that is still on disk.', 'wp-simple-firewall' ),
			] );
		}

		$source = $this->resolveSource( $item );
		$reviewLine = __( 'Use the File Contents tab to review the file yourself.', 'wp-simple-firewall' );

		if ( $source->kind === OriginalFileSource::KIND_NONE ) {
			return $this->noOfficialCopy( [
				__( "This file isn't part of WordPress core or of a plugin or theme from WordPress.org, so there's no official original to compare it with.", 'wp-simple-firewall' ),
				$reviewLine,
			] );
		}

		if ( $item->is_unrecognised
			 && ( $source->kind === OriginalFileSource::KIND_CORE
				  || $item->comparison_basis === HashVerificationResult::COMPARISON_BASIS_PUBLISHED_REFERENCE ) ) {
			return $this->message( 'neutral', 'bi-info-circle', __( "This file isn't part of the official release", 'wp-simple-firewall' ), [
				sprintf(
					/* translators: %1$s: WordPress, plugin or theme name, %2$s: version */
					__( "The official release of %1\$s %2\$s doesn't include this file, so there's nothing to compare it with.", 'wp-simple-firewall' ),
					$source->assetName,
					$source->assetVersion
				),
				__( 'Files added to WordPress core, a plugin or a theme are worth checking carefully.', 'wp-simple-firewall' ).' '.$reviewLine,
			] );
		}

		if ( $source->kind === OriginalFileSource::KIND_PREMIUM_PLUGIN
			 || ( $source->kind === OriginalFileSource::KIND_THEME && !$this->isThemeOnWpOrg( $source ) ) ) {
			return $this->noOfficialCopy( [
				sprintf(
					$source->kind === OriginalFileSource::KIND_PREMIUM_PLUGIN
						/* translators: %s: plugin name */
						? __( "%s isn't distributed through WordPress.org, so there's no public original of this file for Shield to compare it with.", 'wp-simple-firewall' )
						/* translators: %s: theme name */
						: __( "The %s theme isn't distributed through WordPress.org, so there's no public original of this file for Shield to compare it with.", 'wp-simple-firewall' ),
					$source->assetName
				),
				__( 'This is normal for premium and custom plugins and themes.', 'wp-simple-firewall' ).' '.$reviewLine,
			] );
		}

		$original = $this->fetchOriginalFile( $source, $item->path_full );
		if ( $original === null ) {
			return $this->retryableMessage( __( "We couldn't fetch the original file", 'wp-simple-firewall' ),
				__( "WordPress.org didn't return the original file, so the diff couldn't be created. Please try again in a few minutes.", 'wp-simple-firewall' )
			);
		}

		$diff = $this->buildDiff( $original, $item->path_full );
		if ( $diff === null ) {
			return $this->retryableMessage( __( "We couldn't create the diff", 'wp-simple-firewall' ),
				__( "The comparison service didn't respond as expected. Please try again in a few minutes.", 'wp-simple-firewall' )
			);
		}

		$data = $this->message( 'neutral', '', '', [] );
		$data[ 'flags' ][ 'has_diff' ] = true;
		$data[ 'vars' ][ 'legend' ] = sprintf(
			/* translators: %1$s: WordPress, plugin or theme name, %2$s: version */
			__( 'Compared with the official WordPress.org copy of %1$s %2$s.', 'wp-simple-firewall' ),
			$source->assetName,
			$source->assetVersion
		);
		$data[ 'vars' ][ 'diff_css' ] = $diff[ 'css' ];
		$data[ 'vars' ][ 'diff_content' ] = $diff[ 'content' ];
		return $data;
	}

	protected function resolveSource( ResultItem $item ) :OriginalFileSource {
		return OriginalFileSource::forItem( $item );
	}

	/**
	 * Asks WordPress.org whether it hosts the theme.
	 */
	protected function isThemeOnWpOrg( OriginalFileSource $source ) :bool {
		return $source->asset instanceof WpThemeVo && $source->asset->isWpOrg();
	}

	/**
	 * Downloads the official copy of the file, for the installed version, from WordPress.org.
	 * @return string|null - the original file contents, or null when it couldn't be fetched
	 */
	protected function fetchOriginalFile( OriginalFileSource $source, string $path ) :?string {
		try {
			switch ( $source->kind ) {
				case OriginalFileSource::KIND_CORE:
					$download = ( new WpOrg\Wp\Files() )->getOriginalFileFromVcs( $path );
					break;
				case OriginalFileSource::KIND_WPORG_PLUGIN:
					$download = ( new WpOrg\Plugin\Repo() )
						->setWorkingSlug( $source->asset->slug )
						->setWorkingVersion( $source->assetVersion )
						->downloadFromVcs( ( new WpOrg\Plugin\Files() )->getRelativeFilePathFromItsInstallDir( $path ) );
					break;
				default:
					$download = ( new WpOrg\Theme\Files() )->getOriginalFileFromVcs( $path );
					break;
			}
		}
		catch ( \Exception $e ) {
			$download = null;
		}

		$FS = Services::WpFs();
		return !empty( $download ) && $FS->isAccessibleFile( $download ) ? (string)$FS->getFileContent( $download ) : null;
	}

	/**
	 * Sends both copies to the WP Hashes diff service.
	 * @return array{css:string,content:string}|null
	 */
	protected function buildDiff( string $original, string $path ) :?array {
		$conv = new ConvertLineEndings();
		try {
			$res = ( new DiffUtil() )->getDiff( $conv->dosToLinux( $original ), $conv->fileDosToLinux( $path ) );
		}
		catch ( \Exception $e ) {
			$res = null;
		}

		$html = \is_array( $res ) ? ( $res[ 'html' ] ?? null ) : null;
		if ( !\is_array( $html ) || empty( $html[ 'content' ] ) ) {
			return null;
		}
		return [
			'css'     => (string)\base64_decode( (string)( $html[ 'css_default' ] ?? '' ) ),
			'content' => $this->labelEmptyHeaders( (string)\base64_decode( (string)$html[ 'content' ] ) ),
		];
	}

	private function labelEmptyHeaders( string $content ) :string {
		$label = \esc_attr__( 'No line number', 'wp-simple-firewall' );
		return \preg_replace_callback(
			'#<th(?<attributes>\s[^>]*)?>\s*</th>#i',
			static fn( array $matches ) :string => sprintf(
				'<th%s><span class="screen-reader-text">%s</span></th>',
				$matches[ 'attributes' ] ?? '',
				$label
			),
			$content
		) ?? $content;
	}

	/**
	 * @param list<string> $lines
	 * @return DiffState
	 */
	private function noOfficialCopy( array $lines ) :array {
		return $this->message( 'neutral', 'bi-info-circle', __( 'No official copy to compare against', 'wp-simple-firewall' ), $lines );
	}

	/**
	 * @param list<string> $lines
	 * @return DiffState
	 */
	private function message( string $tone, string $icon, string $title, array $lines ) :array {
		return [
			'flags'   => [
				'has_diff'  => false,
				'can_retry' => false,
			],
			'vars'    => [
				'tone'         => $tone,
				'icon'         => $icon,
				'title'        => $title,
				'lines'        => $lines,
				'legend'       => '',
				'diff_css'     => '',
				'diff_content' => '',
			],
			'strings' => [
				'retry'   => __( 'Try again', 'wp-simple-firewall' ),
				'removed' => __( 'removed', 'wp-simple-firewall' ),
				'added'   => __( 'added', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return DiffState
	 */
	private function retryableMessage( string $title, string $line ) :array {
		$data = $this->message( 'warning', 'bi-exclamation-triangle', $title, [ $line ] );
		$data[ 'flags' ][ 'can_retry' ] = true;
		return $data;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'rid',
		];
	}
}
