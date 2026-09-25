<?php

declare(strict_types=1);

interface CacheInterface
{
    public function get(int $key): ?int;
    public function put(int $key, int $value): void;
    public function has(int $key): bool;
    public function clear(): void;
    public function stats(): array;
    public function dump(): array;
}

final class Node
{
    public ?Node $prev = null;
    public ?Node $next = null;

    public function __construct(
        public int $key,
        public int $value
    ) {
    }
}

final class LRUCache implements CacheInterface
{
    private array $map = [];
    private Node $head;
    private Node $tail;
    private int $size = 0;

    public function __construct(
        private readonly int $capacity
    ) {
        if ($capacity <= 0) {
            throw new InvalidArgumentException(
                'Capacity must be greater than 0'
            );
        }

        $this->head = new Node(0, 0);
        $this->tail = new Node(0, 0);

        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
    }

    public function get(int $key): ?int
    {
        if (!array_key_exists($key, $this->map)) {
            return null;
        }

        $node = $this->map[$key];

        $this->moveToFront($node);

        return $node->value;
    }

    public function put(int $key, int $value): void
    {
        if (array_key_exists($key, $this->map)) {
            $node = $this->map[$key];
            $node->value = $value;

            $this->moveToFront($node);

            return;
        }

        if ($this->size >= $this->capacity) {
            $this->evictLeastRecentlyUsed();
        }

        $node = new Node($key, $value);

        $this->map[$key] = $node;
        $this->addToFront($node);
        $this->size++;
    }

    public function has(int $key): bool
    {
        return array_key_exists($key, $this->map);
    }

    public function clear(): void
    {
        $current = $this->head->next;

        while ($current !== null && $current !== $this->tail) {
            $next = $current->next;

            $current->prev = null;
            $current->next = null;

            $current = $next;
        }

        $this->map = [];
        $this->size = 0;

        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
    }

    public function stats(): array
    {
        return [
            'capacity' => $this->capacity,
            'size' => $this->size,
            'is_empty' => $this->size === 0,
            'is_full' => $this->size === $this->capacity,
            'remaining' => $this->capacity - $this->size
        ];
    }

    public function dump(): array
    {
        $items = [];
        $order = [];

        $current = $this->head->next;

        while ($current !== null && $current !== $this->tail) {
            $items[$current->key] = $current->value;
            $order[] = $current->key;

            $current = $current->next;
        }

        return [
            'capacity' => $this->capacity,
            'size' => $this->size,
            'items' => $items,
            'order' => $order
        ];
    }

    private function moveToFront(Node $node): void
    {
        $this->removeNode($node);
        $this->addToFront($node);
    }

    private function addToFront(Node $node): void
    {
        $first = $this->head->next;

        if ($first === null) {
            throw new RuntimeException(
                'Cache list is in an invalid state'
            );
        }

        $node->prev = $this->head;
        $node->next = $first;

        $first->prev = $node;
        $this->head->next = $node;
    }

    private function removeNode(Node $node): void
    {
        if ($node === $this->head || $node === $this->tail) {
            throw new RuntimeException(
                'Cannot remove sentinel nodes'
            );
        }

        if ($node->prev === null || $node->next === null) {
            throw new RuntimeException(
                'Cannot remove a detached node'
            );
        }

        $previous = $node->prev;
        $next = $node->next;

        $previous->next = $next;
        $next->prev = $previous;

        $node->prev = null;
        $node->next = null;
    }

    private function evictLeastRecentlyUsed(): void
    {
        $node = $this->tail->prev;

        if ($node === null || $node === $this->head) {
            throw new RuntimeException(
                'Cannot evict from an empty cache'
            );
        }

        $key = $node->key;

        $this->removeNode($node);
        unset($this->map[$key]);

        $this->size--;
    }
}

final class LRUCacheTest
{
    private array $results = [];

    public function run(): void
    {
        $this->testBasicOperations();
        $this->testCapacityEviction();
        $this->testRecencyOrdering();
        $this->testUpdateExistingKey();
        $this->testNegativeValues();
        $this->testClear();
        $this->testHasMethod();
        $this->testStats();
        $this->testSingleCapacity();
        $this->testInvalidCapacity();
        $this->testRepeatedAccess();
        $this->testRepeatedEviction();

        $this->displayResults();
    }

    private function testBasicOperations(): void
    {
        $cache = new LRUCache(2);

        $cache->put(1, 1);
        $cache->put(2, 2);

        $this->assertSame(
            1,
            $cache->get(1),
            'Get existing key 1'
        );

        $cache->put(3, 3);

        $this->assertSame(
            null,
            $cache->get(2),
            'Get evicted key 2'
        );

        $this->assertSame(
            3,
            $cache->get(3),
            'Get new key 3'
        );
    }

    private function testCapacityEviction(): void
    {
        $cache = new LRUCache(3);

        $cache->put(1, 100);
        $cache->put(2, 200);
        $cache->put(3, 300);

        $this->assertSame(
            3,
            $cache->dump()['size'],
            'Size after three inserts'
        );

        $cache->get(1);
        $cache->put(4, 400);

        $dump = $cache->dump();

        $this->assertSame(
            3,
            $dump['size'],
            'Size after eviction'
        );

        $this->assertFalse(
            $cache->has(2),
            'Key 2 should be evicted'
        );

        $this->assertTrue(
            $cache->has(1),
            'Key 1 should remain'
        );

        $this->assertTrue(
            $cache->has(3),
            'Key 3 should remain'
        );

        $this->assertTrue(
            $cache->has(4),
            'Key 4 should be added'
        );

        $this->assertSame(
            [4, 1, 3],
            $dump['order'],
            'Recency order after eviction'
        );
    }

    private function testRecencyOrdering(): void
    {
        $cache = new LRUCache(4);

        $cache->put(1, 10);
        $cache->put(2, 20);
        $cache->put(3, 30);
        $cache->put(4, 40);

        $this->assertSame(
            [4, 3, 2, 1],
            $cache->dump()['order'],
            'Initial recency order'
        );

        $cache->get(2);

        $this->assertSame(
            [2, 4, 3, 1],
            $cache->dump()['order'],
            'Recency order after accessing key 2'
        );

        $cache->get(1);

        $this->assertSame(
            [1, 2, 4, 3],
            $cache->dump()['order'],
            'Recency order after accessing key 1'
        );

        $cache->put(5, 50);

        $this->assertSame(
            [5, 1, 2, 4],
            $cache->dump()['order'],
            'Recency order after eviction and insert'
        );

        $this->assertFalse(
            $cache->has(3),
            'Least recently used key 3 should be evicted'
        );
    }

    private function testUpdateExistingKey(): void
    {
        $cache = new LRUCache(2);

        $cache->put(1, 100);
        $cache->put(2, 200);
        $cache->put(1, 500);

        $this->assertSame(
            500,
            $cache->get(1),
            'Updated value for key 1'
        );

        $this->assertSame(
            [1, 2],
            $cache->dump()['order'],
            'Updated key becomes most recently used'
        );

        $cache->put(3, 300);

        $this->assertTrue(
            $cache->has(1),
            'Updated key 1 should remain'
        );

        $this->assertFalse(
            $cache->has(2),
            'Key 2 should be evicted'
        );

        $this->assertTrue(
            $cache->has(3),
            'Key 3 should exist'
        );
    }

    private function testNegativeValues(): void
    {
        $cache = new LRUCache(2);

        $cache->put(1, -1);
        $cache->put(2, -500);

        $this->assertSame(
            -1,
            $cache->get(1),
            'Negative one is a valid cached value'
        );

        $this->assertSame(
            -500,
            $cache->get(2),
            'Negative values are preserved'
        );

        $this->assertSame(
            null,
            $cache->get(999),
            'Missing key returns null'
        );
    }

    private function testClear(): void
    {
        $cache = new LRUCache(3);

        $cache->put(1, 100);
        $cache->put(2, 200);
        $cache->put(3, 300);

        $cache->clear();

        $dump = $cache->dump();

        $this->assertSame(
            0,
            $dump['size'],
            'Size after clear'
        );

        $this->assertSame(
            [],
            $dump['order'],
            'Order empty after clear'
        );

        $this->assertFalse(
            $cache->has(1),
            'Key 1 absent after clear'
        );

        $this->assertFalse(
            $cache->has(2),
            'Key 2 absent after clear'
        );

        $this->assertFalse(
            $cache->has(3),
            'Key 3 absent after clear'
        );

        $cache->put(4, 400);

        $this->assertSame(
            400,
            $cache->get(4),
            'Cache reusable after clear'
        );

        $this->assertSame(
            1,
            $cache->stats()['size'],
            'Size correct after reuse'
        );
    }

    private function testHasMethod(): void
    {
        $cache = new LRUCache(2);

        $cache->put(10, 1000);

        $this->assertTrue(
            $cache->has(10),
            'Has existing key'
        );

        $this->assertFalse(
            $cache->has(20),
            'Does not have missing key'
        );

        $cache->put(20, 2000);
        $cache->get(10);
        $cache->put(30, 3000);

        $this->assertTrue(
            $cache->has(10),
            'Recently accessed key remains'
        );

        $this->assertFalse(
            $cache->has(20),
            'Least recently used key removed'
        );

        $this->assertTrue(
            $cache->has(30),
            'New key exists'
        );
    }

    private function testStats(): void
    {
        $cache = new LRUCache(3);

        $stats = $cache->stats();

        $this->assertSame(
            3,
            $stats['capacity'],
            'Capacity in empty cache stats'
        );

        $this->assertSame(
            0,
            $stats['size'],
            'Initial size'
        );

        $this->assertTrue(
            $stats['is_empty'],
            'New cache is empty'
        );

        $this->assertFalse(
            $stats['is_full'],
            'New cache is not full'
        );

        $this->assertSame(
            3,
            $stats['remaining'],
            'Initial remaining capacity'
        );

        $cache->put(1, 10);
        $cache->put(2, 20);
        $cache->put(3, 30);

        $stats = $cache->stats();

        $this->assertSame(
            3,
            $stats['size'],
            'Full cache size'
        );

        $this->assertFalse(
            $stats['is_empty'],
            'Full cache is not empty'
        );

        $this->assertTrue(
            $stats['is_full'],
            'Cache reports full'
        );

        $this->assertSame(
            0,
            $stats['remaining'],
            'No remaining capacity'
        );

        $cache->put(4, 40);

        $stats = $cache->stats();

        $this->assertSame(
            3,
            $stats['size'],
            'Size remains bounded after eviction'
        );

        $this->assertTrue(
            $stats['is_full'],
            'Cache remains full after eviction'
        );
    }

    private function testSingleCapacity(): void
    {
        $cache = new LRUCache(1);

        $cache->put(1, 100);

        $this->assertSame(
            100,
            $cache->get(1),
            'Read from capacity one cache'
        );

        $cache->put(2, 200);

        $this->assertSame(
            null,
            $cache->get(1),
            'Old key evicted from capacity one cache'
        );

        $this->assertSame(
            200,
            $cache->get(2),
            'New key remains in capacity one cache'
        );

        $this->assertSame(
            1,
            $cache->stats()['size'],
            'Capacity one cache size remains one'
        );
    }

    private function testInvalidCapacity(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            static fn() => new LRUCache(0),
            'Zero capacity rejected'
        );

        $this->assertThrows(
            InvalidArgumentException::class,
            static fn() => new LRUCache(-5),
            'Negative capacity rejected'
        );
    }

    private function testRepeatedAccess(): void
    {
        $cache = new LRUCache(3);

        $cache->put(1, 10);
        $cache->put(2, 20);
        $cache->put(3, 30);

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(
                10,
                $cache->get(1),
                'Repeated access returns correct value'
            );
        }

        $this->assertSame(
            [1, 3, 2],
            $cache->dump()['order'],
            'Repeated access preserves correct ordering'
        );

        $cache->put(4, 40);

        $this->assertFalse(
            $cache->has(2),
            'Correct key evicted after repeated access'
        );
    }

    private function testRepeatedEviction(): void
    {
        $cache = new LRUCache(3);

        for ($i = 1; $i <= 100; $i++) {
            $cache->put($i, $i * 10);
        }

        $this->assertSame(
            3,
            $cache->stats()['size'],
            'Size bounded after repeated eviction'
        );

        $this->assertSame(
            [100, 99, 98],
            $cache->dump()['order'],
            'Final order after repeated eviction'
        );

        $this->assertFalse(
            $cache->has(97),
            'Older key removed after repeated eviction'
        );

        $this->assertTrue(
            $cache->has(98),
            'Key 98 remains'
        );

        $this->assertTrue(
            $cache->has(99),
            'Key 99 remains'
        );

        $this->assertTrue(
            $cache->has(100),
            'Key 100 remains'
        );
    }

    private function assertSame(
        mixed $expected,
        mixed $actual,
        string $message
    ): void {
        $this->results[] = [
            'test' => $message,
            'passed' => $expected === $actual,
            'expected' => $expected,
            'actual' => $actual
        ];
    }

    private function assertTrue(
        bool $condition,
        string $message
    ): void {
        $this->assertSame(true, $condition, $message);
    }

    private function assertFalse(
        bool $condition,
        string $message
    ): void {
        $this->assertSame(false, $condition, $message);
    }

    private function assertThrows(
        string $expectedClass,
        callable $callback,
        string $message
    ): void {
        try {
            $callback();

            $this->results[] = [
                'test' => $message,
                'passed' => false,
                'expected' => $expectedClass,
                'actual' => 'No exception'
            ];
        } catch (Throwable $throwable) {
            $this->results[] = [
                'test' => $message,
                'passed' => $throwable instanceof $expectedClass,
                'expected' => $expectedClass,
                'actual' => $throwable::class
            ];
        }
    }

    private function displayResults(): void
    {
        echo "LRU Cache Test Results\n";
        echo str_repeat('=', 70) . "\n\n";

        $passed = 0;
        $failed = 0;

        foreach ($this->results as $result) {
            $status = $result['passed'] ? 'PASS' : 'FAIL';

            if ($result['passed']) {
                $passed++;
            } else {
                $failed++;
            }

            echo sprintf(
                "[%s] %s\n",
                $status,
                $result['test']
            );

            if (!$result['passed']) {
                echo sprintf(
                    "  Expected: %s\n  Actual:   %s\n",
                    var_export($result['expected'], true),
                    var_export($result['actual'], true)
                );
            }
        }

        echo "\n" . str_repeat('-', 70) . "\n";

        echo sprintf(
            "Total: %d, Passed: %d, Failed: %d\n",
            count($this->results),
            $passed,
            $failed
        );

        echo str_repeat('=', 70) . "\n";
    }
}

function runDemo(): void
{
    echo "\n=== LRU Cache Demo ===\n\n";

    $cache = new LRUCache(3);

    $cache->put(1, 100);
    $cache->put(2, 200);
    $cache->put(3, 300);

    echo "Initial cache: ";
    echo json_encode(
        $cache->dump(),
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
    echo "\n\n";

    echo "Get(1): ";
    echo var_export($cache->get(1), true);
    echo "\n";

    echo "Order: ";
    echo json_encode(
        $cache->dump()['order'],
        JSON_THROW_ON_ERROR
    );
    echo "\n\n";

    $cache->put(4, 400);

    echo "After Put(4, 400): ";
    echo json_encode(
        $cache->dump(),
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
    echo "\n\n";

    echo "Get(2): ";
    echo var_export($cache->get(2), true);
    echo "\n";

    echo "Get(4): ";
    echo var_export($cache->get(4), true);
    echo "\n\n";

    $cache->put(5, -1);

    echo "Stored negative value: ";
    echo var_export($cache->get(5), true);
    echo "\n";

    echo "Missing value: ";
    echo var_export($cache->get(999), true);
    echo "\n\n";

    echo "Stats: ";
    echo json_encode(
        $cache->stats(),
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
    echo "\n\n";

    $cache->clear();

    echo "After clear: ";
    echo json_encode(
        $cache->dump(),
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
    echo "\n";

    echo "\n=== Demo Complete ===\n";
}

if (
    PHP_SAPI === 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__
) {
    $test = new LRUCacheTest();
    $test->run();

    runDemo();
}
