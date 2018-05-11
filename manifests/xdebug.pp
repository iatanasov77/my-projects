class devenv::xdebug 
{
	case $operatingsystem 
	{
		'RedHat', 'CentOS', 'Fedora': 
		{
			$ini_file_path = '/etc/php.d/15-xdebug.ini'
			$package = 'php-pecl-xdebug'
			$php = 'php-cli'
			$zend_extension_module = 'xdebug.so'
		}
		'Debian', 'Ubuntu':
		{
			$ini_file_path = '/etc/php5/conf.d/xdebug_config.ini'
			$package = 'php-xdebug'
			$php = 'php-cli'
			$zend_extension_module = 'xdebug.so'
		}
	}

	$default_enable      = '1'
	$remote_enable       = '1'
	$remote_handler      = 'dbgp'
	$remote_host         = 'localhost'
	$remote_port         = '9000'
	$remote_autostart    = '1'
	$remote_connect_back = '0'
	$remote_log          = false
	$idekey              = ''
}
