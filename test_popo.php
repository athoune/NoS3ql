<?php
require_once 'popo.php';

$session = new Session();

class User extends Popo {
  function __construct() {
    $this->__addEvent(new Counter('user'));
  }
  
}
$user = new User;

$session->attach($user);
print $user->id ."\n";