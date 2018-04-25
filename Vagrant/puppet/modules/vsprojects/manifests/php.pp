
class vsprojects::php
{
	## Install PHP
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
}
