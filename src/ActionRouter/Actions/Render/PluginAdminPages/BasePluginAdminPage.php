<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminAuthClear;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

abstract class BasePluginAdminPage extends BaseRender {

	public const TEMPLATE = '/wpadmin/plugin_pages/base_inner_page.twig';

	protected function getPageContextualHrefs() :array {
		return [];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [];
	}

	/**
	 * @throws ActionException
	 */
	protected function getAllRenderDataArrays() :array {
		$data = parent::getAllRenderDataArrays();
		$data[ 25 ] = $this->getCommonAdminPageRenderData();
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
		$hrefs = \array_values( \array_map(
			fn( array $href ) :array => $this->normalizePageContextualHref( $href ),
			\array_filter( $hrefs )
		) );

		return [
			'hrefs' => [
				'inner_page_contextual_hrefs' => $hrefs,
			],
		];
	}

	/**
	 * @param array{
	 *   title?:string,
	 *   href?:string,
	 *   id?:string,
	 *   classes?:list<string>,
	 *   new_window?:bool,
	 *   data?:array<string,string>,
	 *   disabled?:bool,
	 *   is_action?:bool
	 * } $href
	 * @return array{
	 *   title:string,
	 *   href:string,
	 *   id:string,
	 *   classes:list<string>,
	 *   new_window:bool,
	 *   data:array<string,string>,
	 *   disabled:bool,
	 *   is_action:bool
	 * }
	 */
	private function normalizePageContextualHref( array $href ) :array {
		$isAction = (bool)( $href[ 'is_action' ] ?? false );

		return [
			'title'      => (string)( $href[ 'title' ] ?? '' ),
			'href'       => $isAction ? '' : (string)( $href[ 'href' ] ?? '' ),
			'id'         => (string)( $href[ 'id' ] ?? '' ),
			'classes'    => \array_values( $href[ 'classes' ] ?? [] ),
			'new_window' => !$isAction && (bool)( $href[ 'new_window' ] ?? false ),
			'data'       => $href[ 'data' ] ?? [],
			'disabled'   => (bool)( $href[ 'disabled' ] ?? false ),
			'is_action'  => $isAction,
		];
	}

}
