<?php namespace App\Component\Apache;

use Symfony\Component\DependencyInjection\ContainerInterface;
use App\Component\Apache\VirtualHost;
use App\Component\Apache\Php;

class VirtualHostRepository
{
    protected $container;
    
    protected $apacheLogDir;
    
    protected $vHostDir;
    
    protected $vhosts;
    
    protected $vhostConfigs;    // to can get config by host name
    
    public function __construct( ContainerInterface $container )
    {
        $this->container    = $container;
        $this->apacheLogDir = '/var/log/httpd/';
        
        $this->vHostDir     = $this->container->getParameter( 'virtual_hosts_dir' );
        $this->_initVhosts();
    }
    
    public function virtualHosts()
    {
        return $this->vhosts;
    }
    
    public function getVirtualHostByConfig( $config )
    {
        if ( isset( $this->vhosts[$config] ) ) {
            return $this->vhosts[$config];
        }
        
        return null;
    }
    
    public function getVirtualHostByHost( $host )
    {
        if ( isset( $this->vhostConfigs[$host] ) ) {
            $config = $this->vhostConfigs[$host];
            if ( isset( $this->vhosts[$config] ) ) {
                return $this->vhosts[$config];
            }
        }
        
        return null;
    }
    
    public function setVirtualHost( $host, $phpVersion = 'default' )
    {
        $apache         = $this->container->get( 'vs_app.apache_service' );
        $phpBrew        = $this->container->get( 'vs_app.php_brew' );
        $twig           = $this->container->get( 'templating' );
        
        $vhost          = $this->getVirtualHostByHost( $host );
        $fpmSocket      = $phpBrew->fpmSocket( $phpVersion );
        $withSsl        = false;
        //var_dump(htmlspecialchars_decode( $vhost->getDocumentRoot() )); die;
        $tpl            = $phpVersion == 'default' ? 'mkvhost/simple.twig' : 'mkvhost/simple-fpm.twig';
        $vhostConfig    = $twig->render( $tpl, [
            'host'          => $vhost->getHost(),
            'documentRoot'  => $vhost->getDocumentRoot(),
            'serverAdmin'   => $vhost->getServerAdmin(),
            'apacheLogDir'  => $vhost->getApacheLogDir(),
            'fpmSocket'     => $fpmSocket
        ]);
        
        if ( $withSsl )
        {
            $vhostConfig  .= "\n\n" . $twig->render( 'templates/mkvhost/ssl.twig', [
                'host'          => $vhost->getHost(),
                'documentRoot'  => $vhost->getDocumentRoot(),
                'serverAdmin'   => $vhost->getServerAdmin(),
                'apacheLogDir'  => $vhost->getApacheLogDir()
            ]);
        }
        
        file_put_contents( '/tmp/vhost.conf', htmlspecialchars_decode( $vhostConfig ) );
        
        exec( 'sudo mv -f /tmp/vhost.conf ' . $this->vhostConfigs[$host] ); // Write VHost file 
        $apache->reload(); // Reload Apache
    }
    
    protected function _initVhosts()
    {
        if ( ! is_dir( $this->vHostDir ) ) {
            throw new \Exception( 'Apache virtual host dir "' . $this->vHostDir . '" not exists!' );
        }
        
        $handle = opendir( $this->vHostDir );
        if ( ! $handle ) {
            throw new \Exception( 'Apache virtual host dir "' . $this->vHostDir . '" cannot be opened!' );
        }
        
        $this->vhosts   = [];
        while ( false !== ( $entry = readdir( $handle ) ) ) {
            if ( $entry != "." && $entry != ".." ) {
                $config                 = $this->vHostDir . '/' . $entry;
                $vhost                  = $this->_parseVhost( $config );
                
                if ( ! isset($vhost['ServerName'] ) ) {   // may be phpMyAdmin.conf
                    continue;   
                }
                
                //$host                   = $vhost['ServerName'];
                //$serverAdmin            = isset( $vhost['ServerAdmin'] ) ? $vhost['ServerAdmin'] : 'webmaster@' . $host;
                $vhost['LogDir']        = $this->apacheLogDir;
                
                $this->vhosts[$config]  = new VirtualHost( $vhost );
                
                $this->vhostConfigs[$vhost['ServerName']]   = $config;
            }
        }
        closedir( $handle );
    }
    
    protected function _parseVhost( $confFile )
    {
        $vhost  = [
            'config'            => $confFile,
            'PhpVersion'        => 'default',
            'PhpStatus'         => Php::STATUS_INSTALLED,
            'PhpStatusLabel'    => Php::phpStatus( Php::STATUS_INSTALLED ),
        ];
        
        $handle = fopen( $confFile, 'r' ) or die( 'Virtual host conf cannot be opened ..' );
        while( ! feof( $handle ) ) {
            $line = fgets( $handle );
            $line = trim( $line );
            
            $this->_parseLine( $line, $vhost );
            
        }
        fclose( $handle );
        
        return $vhost;
    }
    
    protected function _parseLine( $line, &$vhost )
    {
        $tokens = explode( ' ',$line );
        
        if( ! empty( $tokens ) ) {
            
            switch ( strtolower( $tokens[0] ) ) {
                case 'serveadmin':
                    $vhost['ServerAdmin'] = $tokens[1];
                    break;
                case 'documentroot':
                    $vhost['DocumentRoot'] = trim( $tokens[1], '"' );
                    break;
                case 'servername':
                    $vhost['ServerName'] = $tokens[1];
                    break;
                case 'serveralias':
                    $vhost['ServerAlias'] = $tokens[1];
                    break;
            }
            
            if( $tokens[0] == '<Proxy' ) {
                if ( isset( $tokens[1] ) ) {
                    $proxyParts                     = explode( '/', $tokens[1] );
                    $phpVersionParts                = explode( '-', substr( $proxyParts[4], 4 ) );
                    
                    $vhost['PhpVersion']            = $phpVersionParts[0];
                    $vhost['PhpVersionCustomName']  = isset( $phpVersionParts[1] ) ? $phpVersionParts[1] : '';
                    
                    $phpBrew                        = $this->container->get( 'vs_app.php_brew' );
                    $phpStatus                      = $phpBrew->getPhpStatus( $vhost['PhpVersion'] );
                    
                    $vhost['PhpStatus']             = $phpStatus;
                    $vhost['PhpStatusLabel']        = Php::phpStatus( $phpStatus );
                }
            }
            
        } else {
            echo "Puked...";
        }
    }
}