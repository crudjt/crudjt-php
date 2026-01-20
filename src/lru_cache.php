<?php

namespace CRUDJT;

class LRUNode {
    public $key;
    public $value;
    public $prev = null;
    public $next = null;

    function __construct($key, $value) {
        $this->key = $key;
        $this->value = $value;
    }
}

class LRUCache {
    /**
     * @param Integer $capacity
     */
    private $capacity = 0;
    private $cache = [];
    private $head;
    private $tail;

    public function __construct(int $capacity) {
        $this->capacity = $capacity;
        $this->head = new LRUNode(0, 0);
        $this->tail = new LRUNode(0, 0);
        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
    }

    /**
     * @param Integer $key
     * @return Integer
     */
    public function get(string $key): ?array {
        if (!isset($this->cache[$key])) {
            return null;
        }

        $node = $this->cache[$key];
        $this->moveToHead($node);
        return $node->value;
    }

    /**
     * @param Integer $key
     * @param Integer $value
     * @return NULL
     */
    public function put(string $key, array $value): void {
        if (isset($this->cache[$key])) {
            // Update existing node and move it to head
            $node = $this->cache[$key];
            $node->value = $value;
            $this->moveToHead($node);
        }
        else {
            // Create new node
            $newNode = new LRUNode($key, $value);
            $this->cache[$key] = $newNode;
            $this->addNode($newNode);

            if (count($this->cache) > $this->capacity) {
                $this->removeLRUNode();
            }
        }
    }

    public function del(string $key): void {
        if (!isset($this->cache[$key])) {
            return;
        }

        $node = $this->cache[$key];
        $this->removeNode($node);
        unset($this->cache[$key]);
    }

    private function addNode(LRUNode $node): void {
        $node->next = $this->head->next;
        $node->prev = $this->head;
        $this->head->next->prev = $node;
        $this->head->next = $node;
    }

    private function removeNode(LRUNode $node): void {
        $node->prev->next = $node->next;
        $node->next->prev = $node->prev;
    }

    private function moveToHead(LRUNode $node): void {
        $this->removeNode($node);
        $this->addNode($node);
    }

    private function removeLRUNode(): void {
        $lru = $this->tail->prev;
        $this->removeNode($lru);
        unset($this->cache[$lru->key]);
    }
}
