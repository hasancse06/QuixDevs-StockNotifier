<?php
use PHPUnit\Framework\TestCase;
use QuixDevs\StockNotifier\Inventory\Alert_State;

final class AlertStateTest extends TestCase {
 public function testEpisodeSequence(): void {
  self::assertSame('healthy', Alert_State::transition(false,8,5));
  self::assertSame('crossed', Alert_State::transition(false,5,5));
  self::assertSame('awaiting_restock', Alert_State::transition(true,4,5));
  self::assertSame('rearmed', Alert_State::transition(true,10,5));
  self::assertSame('crossed', Alert_State::transition(false,5,5));
 }
 public function testZeroNegativeAndUnmanaged(): void {
  self::assertTrue(Alert_State::low(0,0));
  self::assertTrue(Alert_State::low(-2,0));
  self::assertFalse(Alert_State::low(null,5));
  self::assertSame('disabled',Alert_State::transition(false,null,5));
 }
}
