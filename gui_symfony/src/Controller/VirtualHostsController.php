<?php namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\Component\Apache\VirtualHost;
use App\Component\Apache\Php;
use App\Form\Type\ProjectHostType;
use App\Entity\ProjectHost;

class VirtualHostsController extends Controller
{   
    /**
     * @Route("/hosts_new", name="virtual-hosts-new")
     */
    public function indexNew( Request $request )
    {
        $installedProjects  = getcwd() . '/../../installed_projects.json';
        var_dump( json_decode( file_get_contents( $installedProjects ), true ) ); die;
    }
    
    /**
     * @Route("/hosts", name="virtual-hosts")
     */
    public function index( Request $request )
    {
        $virtualHosts       = $this->container->get( 'vs_app.apache_virtual_hosts' );
        $phpBrew            = $this->container->get( 'vs_app.php_brew' );
        $installedVersions  = $phpBrew->getInstalledVersions();
        
        //$repository = $this->getDoctrine()->getRepository( ProjectHost::class );
        //$host       = $id ? $repository->find( $id ) : new ProjectHost();
        $formHost       = $this->_hostForm( new ProjectHost() );
        
        return $this->render('pages/virtual_hosts.html.twig', [
            'hosts'                 => $virtualHosts->virtualHosts(),
            'installedPhpVersions'  => $installedVersions,  
            'formHost'              => $formHost->createView(),
        ]);
    }
    
    /**
     * @Route("/hosts/create", name="virtual-hosts-create")
     */
    public function create( Request $request )
    {
        $formHost   = $this->_hostForm( new ProjectHost() );
        
        $formHost->handleRequest( $request );
        if ( $formHost->isSubmitted() ) {
            $em     = $this->getDoctrine()->getManager();
            $host   = $formHost->getData();
            
            $em->persist( $host );
            $em->flush();
            
            $this->createVirtualhost( $host );
            
            return $this->redirectToRoute( 'virtual-hosts' );
        }
    }
    
    /**
     * @Route("/hosts/{host}/php-version", name="virtual-hosts-set-php-version")
     */
    public function setPhpVersion( Request $request )
    {
        if ( $request->isMethod( 'post' ) ) {
            $vhosts     = $this->container->get( 'vs_app.apache_virtual_hosts' );
     
            $host       = $request->attributes->get( 'host' );
            $phpVersion = ltrim( $request->request->get( 'php_version' ), 'php-' );
            
            $vhosts->setVirtualhost( $host, $phpVersion );

            return $this->redirectToRoute( 'virtual-hosts' );
        }
    }
    
    private function _hostForm( ProjectHost $host )
    {
        $form   = $this->createForm( ProjectHostType::class, new ProjectHost(), [
            'action' => $this->generateUrl( 'virtual-hosts-create' ),
            'method' => 'POST'
        ]);
        
        return $form;
    }
    
    private function createVirtualhost( ProjectHost $host )
    {
        $vhosts = $this->container->get( 'vs_app.apache_virtual_hosts' );
        
        if ( $host->getPhpVersion() != 'default' ) {
            $template   = 'simple-fpm';
        } else {
            $template   = 'simple';
        }
        
        $vhost  = new VirtualHost([
            'PhpVersion'        => $host->getPhpVersion(),
            'PhpStatus'         => Php::STATUS_INSTALLED,
            'PhpStatusLabel'    => Php::phpStatus( Php::STATUS_INSTALLED ),
            
            'ServerName'        => $host->getHost(),
            'DocumentRoot'      => $host->getDocumentRoot(),
            'ServerAdmin'       => 'webmaster@' . $host->getHost(),
            'LogDir'            => '/var/log/httpd/',
            'WithSsl'           => $host->getWithSsl(),
        ]);
        
        $vhosts->generateVirtualhost( $vhost, $template );
    }
}
