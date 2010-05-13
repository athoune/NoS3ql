Manage HUGE data in S3 with a Redis Index.

Install
=======

Redis
------
PRedis library is easy to install, it's only one file :

    wget http://github.com/nrk/predis/raw/master/lib/Predis.php

Testing
-------
Phpunit is now a classic.

    pear channel-discover pear.phpunit.de
    pear channel-discover pear.symfony-project.com
    pear install phpunit/PHPUnit

S3
--

[Cloudfusion](http://getcloudfusion.com/) is a nice and well documented Library for Amazon Web Service

    wget http://tarzan-aws.googlecode.com/files/cloudfusion_2.5.zip
    unzip cloudfusion_2.5.zip

Testing it
==========

    phpunit TestPopo