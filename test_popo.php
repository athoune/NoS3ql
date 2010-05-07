<?php
require_once 'popo.php';

$session = new Session();

class User extends Popo {
  function __settings() {
    $this->__addEvent(new Counter('user'));
    $this->__addEvent(new Tag('vegetable', $this, 'tags'));
  }
}

$user = new User();

$session->attach($user);
print $user->id ."\n";
print $session->query->counter('user') . "\n";
$user->name = "Robert";
$user->tags = array('petit pois', 'carotte', 'courgette');
var_dump($user->__modify());
$session->store($user);
var_dump($session->redis->smembers('tag:vegetable:carotte'));
