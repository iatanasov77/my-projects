
class vsprojects::lamp
{
	# Install and setup an Apache server with mod_php
	class { 'apache':
		default_vhost 	=> false,
		default_mods	=> false,
		mpm_module 		=> 'prefork',
	}

	# Apache modules
	class { 'apache::mod::expires': }
	class { 'apache::mod::headers': }
	class { 'apache::mod::rewrite': }
	class { 'apache::mod::vhost_alias': }

	# Install PHP
	#apt::ppa { 'ppa:ondrej/php':
	#	ensure	=> 'present'
	#}
	$packages = [
		"php7.1", 
		"php7.1-cli",
		"composer",
		"phpunit",
		"libapache2-mod-php7.1",
		"libapache2-mod-php",
	]
	package { $packages:
		ensure => installed
	}

	class { 'apache::mod::php': 
		php_version	=> '7.1',
	}

	# Install and setup MySql server
	exec { 'mkdir -p /var/log/mariadb':
		path     => '/usr/bin:/usr/sbin:/bin',
		provider => shell
	}
	class { 'mysql::server':
		create_root_user	=> true,
		root_password		=> 'vagrant',
	}

	# Install Sqlite server
	class { 'sqlite': }
	exec { 'apt-get install -y php-sqlite3':
		provider => shell
	}


	# Install PhpMyAdmin
	class { 'phpmyadmin': }
	phpmyadmin::server{ 'default': }

	# Setup default main virtual host
	apache::vhost { 'myprojects.lh':
		port    		=> '80',
		docroot 		=> '/vagrant/web',
		override		=> 'all',
		
		directories => [
			{
				'path'		=> '/usr/share/phpmyadmin',
				'Require'	=> 'all granted' ,
			},
		],
		
		aliases			=> [
			{
				alias => '/phpMyAdmin',
				path  => '/usr/share/phpmyadmin'
			}, 
			{
				alias => '/phpmyadmin',
				path  => '/usr/share/phpmyadmin'
			}
		],
	}
}
