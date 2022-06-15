<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\HumanSpam\TestContent;
use FernleafSystems\Wordpress\Services\Services;

class Human {

	use ModConsumer;

	/**
	 * Does the same as the WordPress blacklist filter, but more intelligently and with a nod towards much higher
	 * performance. It also uses defined options for which fields are checked for SPAM instead of just checking
	 * EVERYTHING!
	 * @param array $commData
	 * @return \WP_Error|true
	 */
	public function scan( $commData ) {
		/** @var CommentsFilter\Options $opts */
		$opts = $this->getOptions();

		$mResult = true;

		$items = array_intersect_key(
			[
				'comment_content' => $commData[ 'comment_content' ],
				'url'             => $commData[ 'comment_author_url' ],
				'author_name'     => $commData[ 'comment_author' ],
				'author_email'    => $commData[ 'comment_author_email' ],
				'ip_address'      => $this->getCon()->this_req->ip,
				'user_agent'      => substr( Services::Request()->getUserAgent(), 0, 254 )
			],
			array_flip( $opts->getHumanSpamFilterItems() )
		);

		$spam = ( new TestContent() )
			->setCon( $this->getCon() )
			->findSpam( $items, true );

		if ( !empty( $spam ) ) {
			$key = key( reset( $spam ) );
			$word = key( $spam );

			$mResult = new \WP_Error(
				'human',
				sprintf( __( 'Human SPAM filter found "%s" in "%s"', 'wp-simple-firewall' ), $word, $key ),
				[
					'word' => $word,
					'key'  => $key
				]
			);
		}

		return $mResult;
	}
}
