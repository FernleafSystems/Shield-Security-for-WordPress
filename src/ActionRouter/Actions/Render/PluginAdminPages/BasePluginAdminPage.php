<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminAuthClear;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Navigation\BuildBreadCrumbs;
use FernleafSystems\Wordpress\Services\Services;

abstract class BasePluginAdminPage extends BaseRender {

	public const TEMPLATE = '/wpadmin/plugin_pages/base_inner_page.twig';

	protected function getPageContextualHrefs() :array {
		return [];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [];
	}

	protected function getInnerPageTitle() :string {
		return '';
	}

	protected function getInnerPageSubTitle() :string {
		return '';
	}

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		$data = parent::getAllRenderDataArrays();
		$data[ 25 ] = $this->getCommonAdminPageRenderData();
		return $data;
	}

	/**
	 * @throws ActionException
	 */
	protected function buildRenderData() :array {
		$data = parent::buildRenderData();
		$data[ 'hrefs' ][ 'inner_page_header_segments' ] = $this->buildInnerPageHeaderSegments(
			$data[ 'hrefs' ][ 'breadcrumbs' ],
			\array_key_exists( 'inner_page_title', $data[ 'strings' ] )
				? $data[ 'strings' ][ 'inner_page_title' ]
				: '',
			$data[ 'strings' ][ 'no_inner_page_title' ]
		);
		return $data;
	}

	protected function getCommonAdminPageRenderData() :array {
		$urls = self::con()->plugin_urls;

		$hrefs = $this->getPageContextualHrefs();
		if ( self::con()->comps->sec_admin->hasActiveSession() ) {
			$hrefs[] = [
				'title' => __( 'End Security Admin Session', 'wp-simple-firewall' ),
				'href'  => $urls->noncedPluginAction( SecurityAdminAuthClear::class, $urls->adminHome() ),
			];
		}
		$hrefs[] = $this->getPageContextualHrefs_Help();

		return [
			'hrefs' => [
				'breadcrumbs'                 => $this->getBreadCrumbs(),
				'inner_page_contextual_hrefs' => \array_filter( $hrefs ),
			],
		];
	}

	/**
	 * @return list<array{text:string, title:string, href:string}>
	 */
	protected function getBreadCrumbs() :array {
		return ( new BuildBreadCrumbs() )->current();
	}

	/**
	 * @param list<array{text:string, title:string, href:string}> $breadcrumbs
	 * @return list<array{text:string, title:string, href:string}>
	 */
	protected function buildInnerPageHeaderSegments( array $breadcrumbs, string $innerPageTitle, string $fallbackTitle ) :array {
		$segments = $breadcrumbs;

		$leafTitle = \trim( $innerPageTitle ) === '' ? $fallbackTitle : $innerPageTitle;
		if ( !empty( $segments ) ) {
			$lastSegment = \end( $segments );
			$lastText = \trim( $lastSegment[ 'text' ] );
			if ( \strtolower( $lastText ) === \strtolower( \trim( $leafTitle ) ) ) {
				$leafTitle = '';
			}
		}

		if ( \trim( $leafTitle ) !== '' ) {
			$segments[] = [
				'text'  => $leafTitle,
				'href'  => '',
				'title' => '',
			];
		}

		return $segments;
	}

	protected function getTextInputFromRequestOrActionData( string $key, string $default = '' ) :string {
		$value = Services::Request()->query( $key, null );
		if ( $value === null && \array_key_exists( $key, $this->action_data ) ) {
			$value = $this->action_data[ $key ];
		}
		if ( $value === null ) {
			$value = $default;
		}
		return \trim( sanitize_text_field( (string)$value ) );
	}
}
