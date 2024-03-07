<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\HumanSpam\TestContent;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Does the same as the WordPress blacklist filter, but more intelligently and with a nod towards much higher
 * performance. It also uses defined options for which fields are checked for SPAM instead of just checking
 * EVERYTHING!
 */
class HumanDictionary {

	use PluginControllerConsumer;

	/**
	 * @return \WP_Error|true
	 */
	public function scan( array $commData ) {
		$result = true;

		$items = \array_intersect_key(
			[
				'comment_content' => $commData[ 'comment_content' ],
				'url'             => $commData[ 'comment_author_url' ],
				'author_name'     => $commData[ 'comment_author' ],
				'author_email'    => $commData[ 'comment_author_email' ],
				'ip_address'      => self::con()->this_req->ip,
				'user_agent'      => \substr( Services::Request()->getUserAgent(), 0, 254 )
			],
			\array_flip( apply_filters( 'shield/human_spam_check_items', self::con()->cfg->configuration->def( 'human_spam_check_items' ) ) )
		);

		$spam = ( new TestContent() )->findSpam( $items );

		if ( !empty( $spam ) ) {
			$key = \key( \reset( $spam ) );
			$word = \key( $spam );

			$result = new \WP_Error(
				'human',
				sprintf( __( 'Human SPAM filter found "%s" in "%s"', 'wp-simple-firewall' ), $word, $key ),
				[
					'word' => $word,
					'key'  => $key
				]
			);
		}

		return $result;
	}
}