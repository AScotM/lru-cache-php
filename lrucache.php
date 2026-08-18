<?php

interface CacheInterface
{
    public function get(int $key): int;
    public function put(int $key, int $value): void;
    public function has(int $key): bool;
    public function clear(): void;
    public function stats(): array;
    public function dump(): array;
}

class Node 
{
    public int $key;
    public int $value;
    public ?Node $prev;
    public ?Node $next;

    public function __construct(int $key, int $value) 
    {
        $this->key = $key;
        $this->value = $value;
        $this->prev = null;
        $this->next = null;
    }
}

class LRUCache implements CacheInterface
{
    private int $capacity;
    private array $map;
    private Node $head;
    private Node $tail;

    public function __construct(int $capacity) 
    {
        if ($capacity <= 0) {
            throw new InvalidArgumentException("Capacity must be greater than 0");
        }
        
        $this->capacity = $capacity;
        $this->map = [];
        $this->head = new Node(0, 0);
        $this->tail = new Node(0, 0);
        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
    }

    public function get(int $key): int 
    {
        if (!isset($this->map[$key])) {
            return -1;
        }

        $node = $this->map[$key];
        $this->removeNode($node);
        $this->addToFront($node);
        
        return $node->value;
    }

    public function put(int $key, int $value): void 
    {
        if (isset($this->map[$key])) {
            $node = $this->map[$key];
            $node->value = $value;
            $this->removeNode($node);
            $this->addToFront($node);
            return;
        }

        if (count($this->map) >= $this->capacity) {
            $lruNode = $this->tail->prev;
            if ($lruNode !== $this->head) {
                $lruKey = $lruNode->key;
                $this->removeNode($lruNode);
                unset($this->map[$lruKey]);
            }
        }

        $newNode = new Node($key, $value);
        $this->map[$key] = $newNode;
        $this->addToFront($newNode);
    }

    private function removeNode(Node $node): void 
    {
        if ($node === $this->head || $node === $this->tail) {
            throw new RuntimeException("Cannot remove sentinel nodes");
        }

        $node->prev->next = $node->next;
        $node->next->prev = $node->prev;
        
        $node->prev = null;
        $node->next = null;
    }

    private function addToFront(Node $node): void 
    {
        $node->next = $this->head->next;
        $node->prev = $this->head;
        $this->head->next->prev = $node;
        $this->head->next = $node;
    }

    public function has(int $key): bool
    {
        return isset($this->map[$key]);
    }

    public function clear(): void
    {
        $current = $this->head->next;
        while ($current !== $this->tail) {
            $next = $current->next;
            $current->prev = null;
            $current->next = null;
            $current = $next;
        }
        
        $this->map = [];
        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
    }

    public function stats(): array
    {
        return [
            'capacity' => $this->capacity,
            'size' => count($this->map),
            'is_empty' => empty($this->map),
            'remaining' => $this->capacity - count($this->map)
        ];
    }

    public function dump(): array 
    {
        $items = [];
        $order = [];
        $current = $this->head->next;
        
        while ($current !== $this->tail) {
            $items[$current->key] = $current->value;
            $order[] = $current->key;
            $current = $current->next;
        }
        
        return [
            'capacity' => $this->capacity,
            'size' => count($this->map),
            'items' => $items,
            'order' => $order
        ];
    }
}

class LRUCacheTest
{
    private LRUCache $cache;
    private array $results;

    public function __construct()
    {
        $this->results = [];
    }

    public function run(): void
    {
        $this->testBasicOperations();
        $this->testCapacityEviction();
        $this->testUpdateExistingKey();
        $this->testClear();
        $this->testHasMethod();
        $this->testStats();
        $this->testEdgeCases();
        $this->displayResults();
    }

    private function testBasicOperations(): void
    {
        $this->cache = new LRUCache(2);
        
        $this->cache->put(1, 1);
        $this->cache->put(2, 2);
        
        $result = $this->cache->get(1);
        $this->assertEqual(1, $result, 'Get existing key 1');
        
        $this->cache->put(3, 3);
        $result = $this->cache->get(2);
        $this->assertEqual(-1, $result, 'Get evicted key 2');
        
        $result = $this->cache->get(3);
        $this->assertEqual(3, $result, 'Get new key 3');
    }

    private function testCapacityEviction(): void
    {
        $this->cache = new LRUCache(3);
        
        $this->cache->put(1, 100);
        $this->cache->put(2, 200);
        $this->cache->put(3, 300);
        
        $dump = $this->cache->dump();
        $this->assertEqual(3, $dump['size'], 'Size after 3 inserts');
        
        $this->cache->get(1);
        $this->cache->put(4, 400);
        
        $dump = $this->cache->dump();
        $this->assertEqual(3, $dump['size'], 'Size after eviction');
        $this->assertFalse($this->cache->has(2), 'Key 2 should be evicted');
        $this->assertTrue($this->cache->has(1), 'Key 1 should remain');
        $this->assertTrue($this->cache->has(3), 'Key 3 should remain');
        $this->assertTrue($this->cache->has(4), 'Key 4 should be added');
        
        $order = $dump['order'];
        $this->assertEqual(1, $order[0], 'Most recent should be key 1');
    }

    private function testUpdateExistingKey(): void
    {
        $this->cache = new LRUCache(2);
        
        $this->cache->put(1, 100);
        $this->cache->put(1, 200);
        
        $result = $this->cache->get(1);
        $this->assertEqual(200, $result, 'Updated value for key 1');
        
        $this->cache->put(2, 300);
        $this->cache->put(3, 400);
        
        $dump = $this->cache->dump();
        $this->assertEqual(2, $dump['size'], 'Size after capacity full');
        $this->assertTrue($this->cache->has(1), 'Key 1 should remain after update');
        $this->assertTrue($this->cache->has(3), 'Key 3 should remain');
        $this->assertFalse($this->cache->has(2), 'Key 2 should be evicted');
    }

    private function testClear(): void
    {
        $this->cache = new LRUCache(2);
        
        $this->cache->put(1, 100);
        $this->cache->put(2, 200);
        
        $this->cache->clear();
        
        $dump = $this->cache->dump();
        $this->assertEqual(0, $dump['size'], 'Size after clear');
        $this->assertFalse($this->cache->has(1), 'Key 1 should not exist after clear');
        $this->assertFalse($this->cache->has(2), 'Key 2 should not exist after clear');
        
        $this->cache->put(3, 300);
        $result = $this->cache->get(3);
        $this->assertEqual(300, $result, 'Can add after clear');
    }

    private function testHasMethod(): void
    {
        $this->cache = new LRUCache(2);
        
        $this->cache->put(1, 100);
        $this->cache->put(2, 200);
        
        $this->assertTrue($this->cache->has(1), 'Has key 1');
        $this->assertTrue($this->cache->has(2), 'Has key 2');
        $this->assertFalse($this->cache->has(3), 'Does not have key 3');
        
        $this->cache->get(1);
        $this->cache->put(3, 300);
        
        $this->assertTrue($this->cache->has(1), 'Has key 1 after access');
        $this->assertFalse($this->cache->has(2), 'Does not have key 2 after eviction');
        $this->assertTrue($this->cache->has(3), 'Has key 3 after addition');
    }

    private function testStats(): void
    {
        $this->cache = new LRUCache(5);
        
        $stats = $this->cache->stats();
        $this->assertEqual(5, $stats['capacity'], 'Capacity in stats');
        $this->assertEqual(0, $stats['size'], 'Size in stats');
        $this->assertTrue($stats['is_empty'], 'Is empty in stats');
        $this->assertEqual(5, $stats['remaining'], 'Remaining in stats');
        
        $this->cache->put(1, 100);
        $this->cache->put(2, 200);
        $this->cache->put(3, 300);
        
        $stats = $this->cache->stats();
        $this->assertEqual(3, $stats['size'], 'Size after inserts');
        $this->assertFalse($stats['is_empty'], 'Is not empty');
        $this->assertEqual(2, $stats['remaining'], 'Remaining after inserts');
    }

    private function testEdgeCases(): void
    {
        try {
            new LRUCache(0);
            $this->assertEqual(false, true, 'Should throw exception for capacity 0');
        } catch (InvalidArgumentException $e) {
            $this->assertEqual(true, true, 'Exception thrown for capacity 0');
        }
        
        try {
            new LRUCache(-5);
            $this->assertEqual(false, true, 'Should throw exception for negative capacity');
        } catch (InvalidArgumentException $e) {
            $this->assertEqual(true, true, 'Exception thrown for negative capacity');
        }
        
        $this->cache = new LRUCache(1);
        $this->cache->put(1, 100);
        $result = $this->cache->get(1);
        $this->assertEqual(100, $result, 'Get from single capacity cache');
        
        $this->cache->put(2, 200);
        $result = $this->cache->get(1);
        $this->assertEqual(-1, $result, 'Evicted from single capacity cache');
        $result = $this->cache->get(2);
        $this->assertEqual(200, $result, 'Remaining in single capacity cache');
        
        $result = $this->cache->get(999);
        $this->assertEqual(-1, $result, 'Get non-existent key returns -1');
    }

    private function assertEqual($expected, $actual, string $message): void
    {
        $this->results[] = [
            'test' => $message,
            'passed' => $expected === $actual,
            'expected' => $expected,
            'actual' => $actual
        ];
    }

    private function assertTrue(bool $condition, string $message): void
    {
        $this->assertEqual(true, $condition, $message);
    }

    private function assertFalse(bool $condition, string $message): void
    {
        $this->assertEqual(false, $condition, $message);
    }

    private function displayResults(): void
    {
        echo "LRU Cache Test Results\n";
        echo str_repeat('=', 60) . "\n\n";
        
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
                    "  Expected: %s, Actual: %s\n",
                    var_export($result['expected'], true),
                    var_export($result['actual'], true)
                );
            }
        }
        
        echo "\n" . str_repeat('-', 60) . "\n";
        echo sprintf("Total: %d, Passed: %d, Failed: %d\n", 
            count($this->results), 
            $passed, 
            $failed
        );
        echo str_repeat('=', 60) . "\n";
    }
}

$test = new LRUCacheTest();
$test->run();

echo "\n=== LRU Cache Demo (Capacity: 2) ===\n\n";

$cache = new LRUCache(2);

$cache->put(1, 1);
echo "Put(1, 1) - Cache: " . json_encode($cache->dump()) . "\n";

$cache->put(2, 2);
echo "Put(2, 2) - Cache: " . json_encode($cache->dump()) . "\n";

echo "Get(1): " . $cache->get(1) . "\n";

$cache->put(3, 3);
echo "Put(3, 3) - Cache: " . json_encode($cache->dump()) . "\n";

echo "Get(2): " . $cache->get(2) . "\n";

$cache->put(4, 4);
echo "Put(4, 4) - Cache: " . json_encode($cache->dump()) . "\n";

echo "Get(1): " . $cache->get(1) . "\n";
echo "Get(3): " . $cache->get(3) . "\n";
echo "Get(4): " . $cache->get(4) . "\n";

echo "\n=== Additional Tests ===\n";

$stats = $cache->stats();
echo "Cache Stats: " . json_encode($stats) . "\n";

echo "Has key 1: " . ($cache->has(1) ? 'true' : 'false') . "\n";
echo "Has key 2: " . ($cache->has(2) ? 'true' : 'false') . "\n";
echo "Has key 3: " . ($cache->has(3) ? 'true' : 'false') . "\n";
echo "Has key 4: " . ($cache->has(4) ? 'true' : 'false') . "\n";

echo "\nTesting capacity constraints...\n";
$cache2 = new LRUCache(3);
$cache2->put(1, 100);
$cache2->put(2, 200);
$cache2->put(3, 300);
echo "After adding 3 items to capacity 3: " . json_encode($cache2->dump()) . "\n";
$cache2->put(4, 400);
echo "After adding 4th item (should evict LRU): " . json_encode($cache2->dump()) . "\n";

echo "\nTesting clear method...\n";
$cache2->clear();
echo "After clear: " . json_encode($cache2->dump()) . "\n";

echo "\n=== Demo Complete ===\n";
