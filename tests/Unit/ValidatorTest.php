<?php
use PHPUnit\Framework\TestCase;
use QuixDevs\StockNotifier\Support\Validator;

final class ValidatorTest extends TestCase {
 /** @dataProvider valid */
 public function testStrictThresholdAccepted( string $raw, int $expected ): void {
  $result = Validator::threshold( $raw );
  self::assertSame( $expected, $result );
  self::assertIsInt( $result );
 }
 public static function valid(): array {
  return array( array('0',0),array('1',1),array('5',5),array('10',10),array('100',100),array('0005',5),array('2147483647',2147483647) );
 }
 /** @dataProvider invalid */
 public function testStrictThresholdRejected( $raw ): void {
  $this->expectException( InvalidArgumentException::class );
  Validator::threshold( $raw );
 }
 public static function invalid(): array {
  return array_map( static fn($v)=>array($v), array('-1','5.5','1e3','2E5','abc','5abc','5,000','',' ',' 5','5 ','+5','1/2','5 000','2147483648','9999999999999999999999',5,5.0,null,array(),true,"5\n",'٥','５') );
 }
 public function testHeaderInjectionRejected(): void {
  self::assertFalse( Validator::email( "vendor@example.com\r\nBcc: stolen@example.com" ) );
  self::assertFalse( Validator::email( ' abc@example.com' ) );
  self::assertTrue( Validator::email( 'valid@example.com' ) );
 }
 public function testEmailListDeduplicates(): void {
  self::assertSame( array('a@example.com'), Validator::emails( 'a@example.com, A@example.com' ) );
 }
}
