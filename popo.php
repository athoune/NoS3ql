<?php

Class Session {
  function __construct(){
    $this->redis = new Redis();
    $this->redis->connect('127.0.0.1', 6379);
  }
  public function nextId() {
    return $this->redis->incr('id');
  }
  public function get($clazz, $key) {
    $data = $this->redis->get('data:'. $clazz .':'. $key);
  }
  private function buildKey($obj) {
    return 'data:'. get_class($obj) .':'. $obj->id;
  }
  public function store($obj) {
    $this->attach($obj);
    $this->redis->set($this->buildKey($obj), json_encode($obj->__data));
  }
  public function attach(&$obj) {
    if($obj->id == null) {
      $obj->id = $this->nextId();
    }
  }
}

class Event {
  public function onCreate() {}
  public function onModify() {}
  public function onDelete() {}
}

class Counter extends Event {
  function __construct($key) {
    
  }
}

class Popo {
  public $__data = array('id'=>null);
  private $__dirty = array();
  private $__events = array();
  
  public function __addEvent($event) {
    $this->__events[] = $event;
  }
  
  public function __get($key) {
    if(array_key_exists($key, $this->__data)) {
      return $this->__data[$key];
    }
    trigger_error('no such key');
  }
  public function __set($key, $value) {
    $this->__data[$key] = $value;
    $this->__dirty[$key] = $value;
  }
}