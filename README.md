ReAction
========

developpement still in progress :)

Requirements
============

The coolest PHP stack for networking :

* libevent
* zeromq
* pthreads
* php 5.3 +

Installing for windows
======================

No need to build anything, just download following :

- php 5.4 (thread safe)

- zeromq
https://github.com/Polycademy/php_zmq_binaries/blob/master/ZeroMQ-3.2.2rc2~miru1.5-x86.exe
https://github.com/Polycademy/php_zmq_binaries/raw/master/php-zmq-20130203/php-zmq/php54/php54-ts_zeromq-3.2.2/php_zmq.dll

Manipulations :

* Unzip php to c:\php5
* Rename php.ini-production to php.ini
* Install ZeroMQ-3.2.2rc2~miru1.5-x86.exe
* Copy C:\Program Files (x86)\ZeroMQ 3.2.2\bin\libzmq-v90-mt-3_2_2.dll to c:\php
* Rename libzmq-v90-mt-3_2_2.dll to libzmq.dll
* Download php_zmq.dll to c:\php5\ext
* Download libevent