<?php
/**
 * @file
 * @license https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */

namespace Wikimedia\CSS\Sanitizer;

use Wikimedia\CSS\Objects\AtRule;
use Wikimedia\CSS\Objects\ComponentValueList;
use Wikimedia\CSS\Objects\RuleList;
use Wikimedia\CSS\Objects\SimpleBlock;
use Wikimedia\CSS\Objects\Token;

/**
 * @covers \Wikimedia\CSS\Sanitizer\Sanitizer
 */
class SanitizerTest extends \PHPUnit_Framework_TestCase {

	public function testErrors() {
		$sanitizer = $this->getMockForAbstractClass( Sanitizer::class );
		$rm = new \ReflectionMethod( $sanitizer, 'sanitizationError' );
		$rm->setAccessible( true );

		$this->assertSame( [], $sanitizer->getSanitizationErrors() );
		$rm->invoke( $sanitizer,
			'foobar', new Token( Token::T_WHITESPACE, [ 'position' => [ 42, 23 ] ] )
		);
		$rm->invoke( $sanitizer,
			'baz', new Token( Token::T_WHITESPACE, [ 'position' => [ 1, 2 ] ] )
		);
		$this->assertSame(
			[ [ 'foobar', 42, 23 ], [ 'baz', 1, 2 ] ],
			$sanitizer->getSanitizationErrors()
		);
		$sanitizer->clearSanitizationErrors();
		$this->assertSame( [], $sanitizer->getSanitizationErrors() );
	}

	public function testSanitize() {
		$ws = new Token( Token::T_WHITESPACE );
		$block = SimpleBlock::newFromDelimiter( Token::T_LEFT_BRACE );
		$block->getValue()->add( $ws );
		$block2 = SimpleBlock::newFromDelimiter( Token::T_LEFT_BRACE );

		$mb = $this->getMockBuilder( Sanitizer::class )->setMethods( [ 'doSanitize' ] );

		$san = $mb->getMockForAbstractClass();
		$san->expects( $this->once() )->method( 'doSanitize' )
			->with( $this->identicalTo( $block ) )
			->willReturn( null );
		$this->assertNull( $san->sanitize( $block ) );

		$san = $mb->getMockForAbstractClass();
		$san->expects( $this->once() )->method( 'doSanitize' )
			->with( $this->identicalTo( $block ) )
			->willReturn( $block2 );
		$this->assertSame( $block2, $san->sanitize( $block ) );

		$san = $mb->getMockForAbstractClass();
		$san->expects( $this->once() )->method( 'doSanitize' )
			->with( $this->identicalTo( $block ) )
			->willReturn( $block );
		$this->assertSame( $block, $san->sanitize( $block ) );
	}

	public function testSanitizeObj() {
		$token1 = new Token( Token::T_WHITESPACE );
		$token2 = new Token( Token::T_COMMA );

		$sanitizer1 = $this->getMockForAbstractClass( Sanitizer::class );
		$rp = new \ReflectionProperty( $sanitizer1, 'sanitizationErrors' );
		$rp->setAccessible( true );
		$rp->setValue( $sanitizer1, [ [ 'x', 1, 2 ] ] );
		$rm = new \ReflectionMethod( $sanitizer1, 'sanitizeObj' );
		$rm->setAccessible( true );

		$sanitizer2 = $this->getMockBuilder( Sanitizer::class )
			->setMethods( [ 'doSanitize', 'getSanitizationErrors', 'clearSanitizationErrors' ] )
			->getMockForAbstractClass();
		$sanitizer2->expects( $this->once() )->method( 'doSanitize' )
			->with( $this->identicalTo( $token1 ) )
			->willReturn( $token2 );
		$sanitizer2->expects( $this->once() )->method( 'getSanitizationErrors' )
			->willReturn( [ [ 'foo', 42, 23 ] ] );
		$sanitizer2->expects( $this->once() )->method( 'clearSanitizationErrors' );

		$this->assertSame( $token2, $rm->invoke( $sanitizer1, $sanitizer2, $token1 ) );
		$this->assertSame( [ [ 'x', 1, 2 ], [ 'foo', 42, 23 ] ], $sanitizer1->getSanitizationErrors() );
	}

	public function testSanitizeList() {
		$token1i = new Token( Token::T_WHITESPACE );
		$token1o = new Token( Token::T_COMMA );
		$token2i = new Token( Token::T_RIGHT_PAREN );
		$token2o = null;
		$token3i = new Token( Token::T_COLON );
		$token3o = new Token( Token::T_SEMICOLON );

		$sanitizer1 = $this->getMockForAbstractClass( Sanitizer::class );
		$rp = new \ReflectionProperty( $sanitizer1, 'sanitizationErrors' );
		$rp->setAccessible( true );
		$rp->setValue( $sanitizer1, [ [ 'x', 1, 2 ] ] );
		$rm = new \ReflectionMethod( $sanitizer1, 'sanitizeList' );
		$rm->setAccessible( true );

		$sanitizer2 = $this->getMockBuilder( Sanitizer::class )
			->setMethods( [ 'doSanitize', 'getSanitizationErrors', 'clearSanitizationErrors' ] )
			->getMock();
		$sanitizer2->expects( $this->at( 0 ) )->method( 'doSanitize' )
			->with( $this->identicalTo( $token1i ) )
			->willReturn( $token1o );
		$sanitizer2->expects( $this->at( 1 ) )->method( 'doSanitize' )
			->with( $this->identicalTo( $token2i ) )
			->willReturn( $token2o );
		$sanitizer2->expects( $this->at( 2 ) )->method( 'doSanitize' )
			->with( $this->identicalTo( $token3i ) )
			->willReturn( $token3o );
		$sanitizer2->expects( $this->at( 3 ) )->method( 'getSanitizationErrors' )
			->willReturn( [ [ 'foo', 42, 23 ] ] );
		$sanitizer2->expects( $this->at( 4 ) )->method( 'getSanitizationErrors' )
			->willReturn( [] );

		$list = new ComponentValueList( [ $token1i, $token2i, $token3i ] );
		$ret = $rm->invoke( $sanitizer1, $sanitizer2, $list );
		$this->assertInstanceOf( ComponentValueList::class, $ret );
		$this->assertSame( [ $token1o, $token3o ], iterator_to_array( $ret ) );
		$this->assertSame( [ [ 'x', 1, 2 ], [ 'foo', 42, 23 ] ], $sanitizer1->getSanitizationErrors() );
	}

	public function testSanitizeRules() {
		$mb = $this->getMockBuilder( RuleSanitizer::class )
			->setMethods( [ 'handlesRule', 'getIndex', 'doSanitize' ] );

		$san1 = $mb->getMockForAbstractClass();
		$san1->expects( $this->any() )->method( 'handlesRule' )->willReturnCallback( function ( $rule ) {
			return $rule->getName() === 'san1';
		} );
		$san1->expects( $this->any() )->method( 'getIndex' )->willReturnCallback( function () {
			return [ 1, 2 ];
		} );
		$san1->expects( $this->any() )->method( 'doSanitize' )->willReturnCallback( function ( $rule ) {
			$this->assertSame( 'san1', $rule->getName() );
			return $rule;
		} );

		$san2 = $mb->getMockForAbstractClass();
		$san2->expects( $this->any() )->method( 'handlesRule' )->willReturnCallback( function ( $rule ) {
			return $rule->getName() === 'san2';
		} );
		$san2->expects( $this->any() )->method( 'getIndex' )->willReturnCallback( function () {
			return 2;
		} );
		$san2->expects( $this->any() )->method( 'doSanitize' )->willReturnCallback( function ( $rule ) {
			$this->assertSame( 'san2', $rule->getName() );
			return $rule;
		} );

		$san3 = $mb->getMockForAbstractClass();
		$san3->expects( $this->any() )->method( 'handlesRule' )->willReturnCallback( function ( $rule ) {
			return $rule->getName() === 'san3';
		} );
		$san3->expects( $this->any() )->method( 'getIndex' )->willReturnCallback( function () {
			return 2;
		} );
		$san3->expects( $this->any() )->method( 'doSanitize' )->willReturnCallback( function ( $rule ) {
			$this->assertSame( 'san3', $rule->getName() );
			return null;
		} );

		$sanX = $mb->getMockForAbstractClass();
		$sanX->expects( $this->any() )->method( 'handlesRule' )->willReturnCallback( function ( $rule ) {
			return $rule->getName() === 'san2';
		} );
		$sanX->expects( $this->never() )->method( 'getIndex' );
		$sanX->expects( $this->never() )->method( 'doSanitize' );

		$AT = Token::T_AT_KEYWORD;
		$r1 = new AtRule( new Token( $AT, [ 'value' => 'san1', 'position' => [ 1, 1 ] ] ) );
		$r2 = new AtRule( new Token( $AT, [ 'value' => 'san1', 'position' => [ 2, 1 ] ] ) );
		$r3 = new AtRule( new Token( $AT, [ 'value' => 'san2', 'position' => [ 3, 1 ] ] ) );
		$r4 = new AtRule( new Token( $AT, [ 'value' => 'san2', 'position' => [ 4, 1 ] ] ) );
		$r5 = new AtRule( new Token( $AT, [ 'value' => 'san3', 'position' => [ 5, 1 ] ] ) );
		$r6 = new AtRule( new Token( $AT, [ 'value' => 'san2', 'position' => [ 6, 1 ] ] ) );
		$r7 = new AtRule( new Token( $AT, [ 'value' => 'san4', 'position' => [ 7, 1 ] ] ) );
		$r8 = new AtRule( new Token( $AT, [ 'value' => 'san2', 'position' => [ 8, 1 ] ] ) );
		$test = new RuleList( [ $r1, $r2, $r3, $r4, $r5, $r6, $r7, $r8 ] );

		$san = $this->getMockForAbstractClass( Sanitizer::class );
		$rm = new \ReflectionMethod( $san, 'sanitizeRules' );
		$rm->setAccessible( true );
		$ret = $rm->invoke( $san, [ $san1, $san2, $san3, $sanX ], $test );
		$this->assertInstanceOf( RuleList::class, $ret );
		$this->assertSame( [ $r1, $r3, $r4, $r6, $r8 ], iterator_to_array( $ret ) );
		$this->assertSame( [
			[ 'misordered-rule', 2, 1 ],
			[ 'unrecognized-rule', 7, 1 ],
		], $san->getSanitizationErrors() );
	}
}
