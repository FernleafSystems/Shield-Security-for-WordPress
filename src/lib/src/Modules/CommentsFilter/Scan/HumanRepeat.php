<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Looks for repeated spam comments posted from the same IP that has already been flagged as spam
 */
class HumanRepeat {

	use PluginControllerConsumer;

	/**
	 * @return \WP_Error|true
	 */
	public function scan( array $commData ) {
		$mResult = true;

		$ip = $commData[ 'comment_author_IP' ] ?? '';

		if ( Services::IP()->isValidIp_PublicRemote( $ip ) ) {
			$q = new \WP_Comment_Query( [
				'number'     => 10,
				'search'     => $commData[ 'comment_author_IP' ],
				'status'     => [
					'0',
					'hold',
					'pending',
					'spam',
					'trash',
				],
				'relation'   => 'OR',
				'meta_query' => [
					[
						'key'   => self::con()->prefix( 'spam_human' ),
						'value' => '1'
					],
					[
						'key'   => self::con()->prefix( 'spam_humanrepeated' ),
						'value' => '1'
					],
				]
			] );

			/** @var \WP_Comment[] $comments */
			$comments = \array_filter( \is_array( $q->comments ) ? $q->comments : [], function ( $comment ) use ( $ip ) {
				return $comment->comment_author_IP === $ip;
			} );

			if ( !empty( $comments ) ) {
				$mResult = new \WP_Error(
					'humanrepeated',
					__( 'Human SPAM filter detected repeated comment SPAM attempts.', 'wp-simple-firewall' )
				);
			}
		}

		return $mResult;
	}
}