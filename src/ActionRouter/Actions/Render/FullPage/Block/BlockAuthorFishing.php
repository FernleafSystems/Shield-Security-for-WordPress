<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

class BlockAuthorFishing extends BaseBlock {

	public const SLUG = 'render_block_author_fishing';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'page_title' => sprintf( '%s | %s', __( 'Block Username Fishing', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'title'      => __( 'Username Fishing Blocked', 'wp-simple-firewall' ),
				'subtitle'   => __( 'Username/Author Fishing is disabled on this site.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRestrictionDetailsBlurb() :array {
		$additional = [
			'query_param' => sprintf( __( 'The %s query parameter has been blocked to protect against username / author fishing.', 'wp-simple-firewall' ), '<code>author</code>' )
		];
		if ( !self::con()->comps->whitelabel->isEnabled() ) {
			$additional[ 'learn_more_link' ] = sprintf( '<a href="%s" target="_blank">%s</a>', 'https://clk.shldscrty.com/7l', __( 'Learn More', 'wp-simple-firewall' ) );
		}
		return \array_merge( $additional, parent::getRestrictionDetailsBlurb() );
	}
}