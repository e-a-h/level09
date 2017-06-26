# Modding Tools for Journey

Required software

* php 7.1+
* php-mysqli
* php-mbstring
* php-pdo_mysql
* mysql
* all in a command-line env like Cygwin, babun, macos, linux.


Setup and run scripts:

1. Clone from this repo
1. git repo clone directory needs to be in your include_path in php.ini
1. launch.php script should be symlinked into your "Scripts" directory in the unpacked game:

```
ln -s /cygdrive/d/path/to/git/level09/launch.php /cygrdive/d/path/to/Journey/Scripts/launch
```

Then from within the "Scripts" directory, use launch like this:

```
php launch
```
