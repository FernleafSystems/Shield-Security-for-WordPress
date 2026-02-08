<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\SearchTextParser;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SearchTextParserTest extends BaseUnitTest {

	public function test_parse_ip_filter() :void {
		$result = SearchTextParser::Parse( 'ip:192.168.1.1' );
		$this->assertSame( '192.168.1.1', $result[ 'ip' ] );
		$this->assertSame( '', $result[ 'remaining' ] );
	}

	public function test_parse_user_id_filter() :void {
		$result = SearchTextParser::Parse( 'user_id:42' );
		$this->assertSame( '42', $result[ 'user_id' ] );
		$this->assertSame( '', $result[ 'remaining' ] );
	}

	public function test_parse_user_name_filter() :void {
		$result = SearchTextParser::Parse( 'user_name:admin' );
		$this->assertSame( 'admin', $result[ 'user_name' ] );
		$this->assertSame( '', $result[ 'remaining' ] );
	}

	public function test_parse_user_email_filter() :void {
		$result = SearchTextParser::Parse( 'user_email:user@example.com' );
		$this->assertSame( 'user@example.com', $result[ 'user_email' ] );
		$this->assertSame( '', $result[ 'remaining' ] );
	}

	public function test_sanitise_user_name_strips_invalid_chars() :void {
		$result = SearchTextParser::Parse( 'user_name:bad!name#here' );
		$this->assertSame( 'badnamehere', $result[ 'user_name' ] );
	}

	public function test_sanitise_user_email_allows_plus() :void {
		$result = SearchTextParser::Parse( 'user_email:user+tag@example.com' );
		$this->assertSame( 'user+tag@example.com', $result[ 'user_email' ] );
	}

	public function test_sanitise_user_email_strips_invalid_chars() :void {
		$result = SearchTextParser::Parse( 'user_email:user!name@example.com' );
		$this->assertSame( 'username@example.com', $result[ 'user_email' ] );
	}

	public function test_multiple_filters_combined() :void {
		$result = SearchTextParser::Parse( 'ip:10.0.0.1 user_name:editor some text' );
		$this->assertSame( '10.0.0.1', $result[ 'ip' ] );
		$this->assertSame( 'editor', $result[ 'user_name' ] );
		$this->assertSame( 'some text', $result[ 'remaining' ] );
	}

	public function test_remaining_text_preserved() :void {
		$result = SearchTextParser::Parse( 'user_id:5 login attempt' );
		$this->assertSame( '5', $result[ 'user_id' ] );
		$this->assertSame( 'login attempt', $result[ 'remaining' ] );
	}

	public function test_empty_input() :void {
		$result = SearchTextParser::Parse( '' );
		$this->assertSame( '', $result[ 'ip' ] );
		$this->assertSame( '', $result[ 'user_id' ] );
		$this->assertSame( '', $result[ 'user_name' ] );
		$this->assertSame( '', $result[ 'user_email' ] );
		$this->assertSame( '', $result[ 'remaining' ] );
	}

	public function test_get_filter_definitions_returns_all_keys() :void {
		$defs = SearchTextParser::GetFilterDefinitions();
		$this->assertSame( [ 'ip', 'user_id', 'user_name', 'user_email' ], \array_keys( $defs ) );
	}

	public function test_each_definition_has_required_fields() :void {
		foreach ( SearchTextParser::GetFilterDefinitions() as $key => $def ) {
			$this->assertArrayHasKey( 'sanitise', $def, "Missing 'sanitise' for filter: {$key}" );
			$this->assertArrayHasKey( 'description', $def, "Missing 'description' for filter: {$key}" );
			$this->assertArrayHasKey( 'example', $def, "Missing 'example' for filter: {$key}" );
		}
	}

	public function test_sanitise_for_filter_known_key() :void {
		$this->assertSame( '192168', SearchTextParser::SanitiseForFilter( 'user_id', '192.168' ) );
	}

	public function test_sanitise_for_filter_unknown_key() :void {
		$this->assertSame( 'anything!@#', SearchTextParser::SanitiseForFilter( 'nonexistent', 'anything!@#' ) );
	}

	public function test_user_name_does_not_false_match_similar_prefix() :void {
		$result = SearchTextParser::Parse( 'user_name_extra:value user_name:admin' );
		$this->assertSame( 'admin', $result[ 'user_name' ] );
	}

	public function test_all_four_filters_combined() :void {
		$result = SearchTextParser::Parse( 'ip:1.2.3.4 user_id:7 user_name:admin user_email:a@b.com leftover' );
		$this->assertSame( '1.2.3.4', $result[ 'ip' ] );
		$this->assertSame( '7', $result[ 'user_id' ] );
		$this->assertSame( 'admin', $result[ 'user_name' ] );
		$this->assertSame( 'a@b.com', $result[ 'user_email' ] );
		$this->assertSame( 'leftover', $result[ 'remaining' ] );
	}
}
