<?php
// http://www.phpunit.de/manual/current/en/api.html
require_once 'PHPUnit/Framework.php';
require_once 'popo.php';
 
class User extends Popo\Popo {
  function __construct() {
    $this->__addEvent(new Popo\Counter('user'));
    $this->__addEvent(new Popo\Tag('vegetable', $this, 'tags'));
  }
}

class TestPopo extends PHPUnit_Framework_TestCase {
    protected function setUp() {
      $this->session = new Popo\Session();
      $this->session->flushdb();
      $this->user = new User();
      $this->session->attach($this->user);
      $this->user->name = "Robert";
    }
    public function testId() {
      $this->assertEquals(1, $this->user->id);
    }
    public function testCounter() {
      $this->assertEquals(1, $this->session->query->counter('user'));
    }
    public function testSearch() {
      $this->user->tags = array('petit pois', 'carotte', 'courgette');
      $this->assertEquals(array('petit pois', 'carotte', 'courgette'), $this->user->__data['tags']);
      $this->session->store($this->user);
      #var_dump($this->session->dump());
      $this->assertContains(Popo\Session::buildkey($this->user), $this->session->redis->smembers('tag:vegetable:carotte'));
    }
}

