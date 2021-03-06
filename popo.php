<?php
namespace Popo;
/*
http://code.google.com/p/redis/wiki/CommandReference

[TODO] using MultiExecBlock when it will be stable
[TODO] using try/catch and revert
[TODO] S3 store, sync and async (with a storage worker)

*/

require_once 'Predis.php';

function escape($str) {
  return str_replace(' ', '_', $str);
}
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
    $this->redis = new \Predis\Client('redis://127.0.0.1:6379/');
    $this->query = new Query($this);
  }
  public function flushdb() {
    $this->redis->flushdb();
  }
  public function nextId() {
    return $this->redis->incr('id');
  }
  public function get($clazz, $key) {
    return new $clazz($this, $this->redis->get('data:'. $clazz .':'. $key));
  }
  public static function buildKey($obj) {
    return 'data:'. get_class($obj) .':'. $obj->id;
  }
  public function store(&$obj, &$multiExecBlock = null) {
    if($obj->__session == null) {
      $this->attach($obj);
    } else {
      $this->_store($obj, $multiExecBlock);
    }
  }
  private function _store(&$obj, &$multiExecBlock) {
    if($multiExecBlock == null) {
      $multiExecBlock = new \Predis\CommandPipeline($this->redis);
    }
    $obj->__doModify($multiExecBlock);
    $multiExecBlock->set($this->buildKey($obj), json_encode($obj->__data));
    $multiExecBlock->execute();
    //print "[STORE] " . $this->buildKey($obj) . "\n";
  }
  public function attach(&$obj) {
    $obj->__session = $this;
    $obj->__before = $obj->__data;
    if($obj->id == null) {
      $obj->id = $this->nextId();
      $multiExecBlock = new \Predis\CommandPipeline($this->redis);
      $obj->__doCreate($multiExecBlock);
      $this->_store($obj, $multiExecBlock);
    }
  }
  public function delete(&$obj) {
    $key = $this->buildKey($obj);
    $multiExecBlock = new Predis\CommandPipeline($this->redis);
    $multiExecBlock->delete($key);
    $obj->__doDelete($multiExecBlock);
    $multiExecBlock->execute();
    $obj->__session = null;
  }
  public function dump() {
    return $this->redis->keys('*');
  }
}

abstract class Event {
  public function onCreate($session, &$multiExecBlock) {}
  public function onModify($session, &$multiExecBlock) {}
  public function onDelete($session, &$multiExecBlock) {}
}

class Counter extends Event {
  function __construct($key) {
    $this->name = "counter:$key";
  }
  public function onCreate($session, &$multiExecBlock) {
    $multiExecBlock->incr($this->name);
  }
  public function onDelete($session, &$multiExecBlock) {
    $multiExecBlock->decr($this->name);
  }
}

class Tag extends Event {
  function __construct($family, $object, $field) {
    $this->family = "tag:$family:";
    $this->object = $object;
    $this->field = $field;
  }
  public function onCreate($session, &$multiExecBlock) {}
  public function onModify($session, &$multiExecBlock) {
    $now = $this->object->__data[$this->field];
    if($now == null) { $now = array(); }
    $before = $this->object->__before[$this->field];
    if($before == null) { $before = array(); }
    foreach(array_diff($before, $now) as $tag) {
      $multiExecBlock->sRemove($this->buildKey($tag), $session->buildKey($this->object));
    }
    foreach(array_diff($now, $before) as $tag) {
      $multiExecBlock->sadd($this->buildKey($tag), $session->buildKey($this->object));
    }
  }
  protected function buildKey($value) {
    return $this->family . escape($value);
  }
  public function onDelete($session, &$multiExecBlock) {
    foreach($this->object->__before[$this->field] as $tag) {
      $multiExecBlock->sRemove($this->buildKey($tag), $session->buildKey($this->object));
    }
  }
}

abstract class Popo {
  public $__data = array();
  public $__session = null;
  public $id = null;
  public $__before = array();
  private $__events = array();
  
  /*
  Trouver un système au niveau de la session, pour pas pourrir une méthode de popo
  */
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
  public function __doDelete(&$multiExecBlock) {
    foreach($this->__events as $event) {
      $event->onDelete($this->__session, $multiExecBlock);
    }
  }
  public function __doCreate(&$multiExecBlock) {
    foreach($this->__events as $event) {
      $event->onCreate($this->__session, $multiExecBlock);
    }
  }
  public function __doModify(&$multiExecBlock) {
    foreach($this->__events as $event) {
      $event->onModify($this->__session, $multiExecBlock);
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