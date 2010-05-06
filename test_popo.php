<?php
require_once 'popo.php';

$session = new Session();

class User extends Popo {
  function __settings() {
    $this->__addEvent(new Counter('user'));
  }
}

$user = new User();

$session->attach($user);
print $user->id ."\n";
print $session->query->counter('user') . "\n";
$user->name = "Robert";
var_dump($user->__modify());
