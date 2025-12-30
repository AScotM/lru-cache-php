<?php

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

class LRUCache 
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
            $this->removeNode($lruNode);
            unset($this->map[$lruNode->key]);
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

echo "=== LRU Cache Demo (Capacity: 2) ===\n\n";

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

echo "\n=== Demo Complete ===\n";
