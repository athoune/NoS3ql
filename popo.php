<?php
class Query {
  function __construct($session) {
    $this->session = $session;
  }
  function counter($key) {
    return $this->session->redis->get("counter:$key");
  }
}
Class Session {
  public $redis;
  function __construct(){
    $this->redis = new Redis();
    $this->redis->connect('127.0.0.1', 6379);
    $this->query = new Query($this);
  }
  public function nextId() {
    return $this->redis->incr('id');
  }
  public function get($clazz, $key) {
    return new $clazz($this, $this->redis->get('data:'. $clazz .':'. $key));
  }
  private function buildKey($obj) {
    return 'data:'. get_class($obj) .':'. $obj->id;
  }
  public function store(&$obj) {
    $this->attach($obj);
    $obj->__doModify();
    $this->redis->set($this->buildKey($obj), json_encode($obj->__data));
    //print "[STORE] " . $this->buildKey($obj) . "\n";
  }
  public function attach(&$obj) {
    $obj->__session = $this;
    if($obj->id == null) {
      $obj->id = $this->nextId();
      $obj->__doCreate();
    }
  }
  public function delete(&$obj) {
    $key = $this->buildKey($obj);
    $this->redis->delete($key);
    $obj->__doDelete();
    $obj->__session = null;
  }
}

class Event {
  public function onCreate($session) {}
  public function onModify($session) {}
  public function onDelete($session) {}
}

class Counter extends Event {
  function __construct($key) {
    $this->name = "counter:$key";
  }
  public function onCreate($session) {
    $session->redis->incr($this->name);
  }
  public function onDelete($session) {
    $session->redis->decr($this->name);
  }
}

class Tag extends Event {
  function __construct($family) {
    $this->family = "tag:$family:";
  }
}

abstract class Popo {
  public $__data;
  public $__session;
  private $__before = array();
  private $__events = array();
  
  protected function __settings() { }

  public function __construct($session = null, $data = array('id'=>null)) {
    $this->__session = $session;
    $this->__data = $data;
    $this->__before = $data;
    $this->__settings();
  }
  
  public function __destruct() {
    if($this->__session != null) {
      $this->__session->store($this);
    }
  }
  
  public function __modify() {
    $modify = array();
    foreach($this->__before as $k => $v) {
      if($this->__data[$k] != $v) {
        $modify[] = $k;
      }
    }
    return array(
      'modify' => $modify, 
      'append' => array_diff(array_keys($this->__data), array_keys($this->__before)),
      'deleted' => array_diff(array_keys($this->__before), array_keys($this->__data)));
  }
  
  public function __addEvent($event) {
    $this->__events[] = $event;
  }
  public function __doDelete() {
    foreach($this->__events as $event) {
      $event->onDelete($this->__session);
    }
  }
  public function __doCreate() {
    foreach($this->__events as $event) {
      $event->onCreate($this->__session);
    }
  }
  public function __doModify() {
    foreach($this->__events as $event) {
      $event->onModify($this->__session);
    }
  }
  public function __get($key) {
    if(array_key_exists($key, $this->__data)) {
      return $this->__data[$key];
    }
    trigger_error("$key doesn't exist");
  }
  public function __set($key, $value) {
    $this->__data[$key] = $value;
  }
}