<?php
use PHPUnit\Framework\TestCase;

class SanitizeAndLogTest extends TestCase
{
    public function test_log_message_hard_cap_and_truncation()
    {
        // access class
        $ref = new ReflectionClass('WC_SC_Debugger');
        $prop = $ref->getProperty('debug_messages');
        $prop->setAccessible(true);

        // reset
        $prop->setValue([]);

        // push 1005 messages
        for ($i=0; $i<1005; $i++) {
            WC_SC_Debugger::log_message('info', str_repeat('x', 1500), ['k' => str_repeat('y', 11000)]);
        }
        $messages = $prop->getValue();
        $this->assertCount(1000, $messages, 'Should hard cap at 1000 messages');
        $this->assertSame(1000, strlen($messages[0]['message']), 'Message should be truncated to 1000 chars');
        $this->assertSame(['message' => 'Data too large to log'], $messages[0]['data']);
    }

    public function test_sanitize_for_logging_circular_and_depth()
    {
        // build a class instance to call private method via reflection
        $instance = WC_SC_Debugger::get_instance();
        $method = new ReflectionMethod($instance, 'sanitize_for_logging');
        $method->setAccessible(true);

        // circular object
        $a = new stdClass();
        $b = new stdClass();
        $a->b = $b;
        $b->a = $a; // circular
        $stack = [];
        $result = $method->invoke($instance, $a, 0, $stack);
        $this->assertIsArray($result);
        $this->assertStringStartsWith('[Object:', $method->invoke($instance, $a->b, 1, $stack));
        // visiting again should trigger circular guard
        $this->assertStringStartsWith('[Circular Reference:', $method->invoke($instance, $a, 1, $stack));

        // deep nesting beyond depth
        $deep = ['level1' => ['level2' => ['level3' => ['level4' => 'x']]]];
        $stack2 = [];
        $san = $method->invoke($instance, $deep, 0, $stack2);
        $this->assertEquals('[Max Depth Reached]', $san['level1']['level2']['level3']);

        // large array
        $large = range(1, 150);
        $stack3 = [];
        $largeRes = $method->invoke($instance, $large, 0, $stack3);
        $this->assertEquals('[Large Array - 150 items]', $largeRes);
    }
}

