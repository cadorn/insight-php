<?php

require_once('Insight/Config.php');

class Insight_Helper
{
    private static $instance = null;

    private $config = null;
    private $server = null;
    private $dispatcher = null;
    private $announceReceiver = null;
    
    private $enabled = false;
    
    public static function init($configPath) {
        if(self::$instance) {
            throw new Exception("Insight_Helper already initialized!");
        }

        $config = new Insight_Config();
        $config->loadFromFile($configPath);

        self::$instance = new self();
        self::$instance->setConfig($config);

        if(self::$instance->isClientAuthorized()) {

            // initialize server            
            require_once('Insight/Server.php');
            self::$instance->server = new Insight_Server();
            self::$instance->server->setConfig($config);
            self::$instance->server->listen();
            
            
            // enable output buffering if not enabled
            
            

            // enable insight
            self::$instance->enabled = true;

            // flush on shutdown
            register_shutdown_function('Insight_Helper__shutdown');        
        }
        return self::$instance;
    }

    private function newInstance() {
        return new self();
    }

    private function __construct() {

//        $this->dispatcher->getEncoder()->setOption('treatArrayMapAsDictionary', true);
    }

    public static function getInstance() {
        if(!self::$instance) {
            // initialize in disabled mode
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function setConfig($config) {
        $this->config = $config;
    }

    public function getConfig() {
        return $this->config;
    }

    public function getDispatcher() {
        if(!$this->enabled) {
            throw new Exception("Insight is not enabled!");
        }
        if(!$this->dispatcher) {
            require_once('Insight/Dispatcher.php');
            $this->dispatcher = new Insight_Dispatcher();
        }
        return $this->dispatcher;
    }
    
    private function getAnnounceReceiver() {
        if(!$this->announceReceiver) {
            require_once('Insight/Receiver/Announce.php');
            $this->announceReceiver = new Insight_Receiver_Announce();
            // parse received headers
            // NOTE: This needs to be moved if we want to support multiple receivers
            $this->announceReceiver->getChannel()->parseReceived(getallheaders());
        }
        return $this->announceReceiver;
    }
/*
    private function newMessage() {
        require_once('Insight/Message.php');
        $message = new Insight_Message();
        $message->setConfig($this->config);
        return $message->from($this->config->getAppID());
    }
*/
    public static function to($name) {

        $instance = self::getInstance();

        if(!$instance->enabled) {
            return new Insight_Helper__NullMessage();
        }

        $info = $instance->config->getTargetInfo($name);
        
        if(!in_array($info['implements'], $instance->getAnnounceReceiver()->getReceivers())) {
            // if target was announced we allow it
            return new Insight_Helper__NullMessage();
        }

        require_once('Insight/Message.php');
        $message = new Insight_Message();
        $message->setHelper($instance);

        $message = $message->to($name);
        $message = $message->meta(array(
            'renderer' =>'php:variable'
        ));
 
        require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . $info['api'] . ".php");
        $class = str_replace("/", "_", $info['api']);

        $message = $message->api(new $class());

        return $message;
    }
    
    protected function isClientAuthorized() {
        // verify IP
        $authorized = false;
        $ips = $this->config->getClientIPs();
        if(count($ips)==1 && $ips[0]=='*') {
            $authorized = true;
        } else {
            foreach( $ips as $ip ) {
                if(substr($_SERVER['REMOTE_ADDR'], 0, strlen($ip))==$ip) {
                    $authorized = true;
                    break;
                }
            }
        }
        if(!$authorized) return false;

        $clientInfo = self::$instance->getClientInfo();
        if(!$clientInfo) return false;

        // verify client key
        $authorized = false;
        
        if($clientInfo['client']=='insight') {
            
            foreach( $this->config->getClientAuthkeys() as $authkey ) {
                if(in_array($authkey, $clientInfo['authkeys'])) {
                    $authorized = true;
                    break;
                }
            }
            
        } else
        if($clientInfo['client']=='firephp') {
            $authorized = true;
        }
        return $authorized;
    }

    protected function getClientInfo() {
        // Check if insight client is installed
        if(@preg_match_all('/^http:\/\/registry.pinf.org\/cadorn.org\/wildfire\/@meta\/protocol\/announce\/([\.\d]*)$/si',$this->getRequestHeader("x-wf-protocol-1"),$m) &&
            version_compare($m[1][0],'0.1.0','>=')) {
            return array(
                "client" => "insight",
                "authkeys" => $this->getAnnounceReceiver()->getAuthkeys(),
                "receivers" => $this->getAnnounceReceiver()->getReceivers()
            );
        } else
        // Check if FirePHP is installed on client via User-Agent header
        if(@preg_match_all('/\sFirePHP\/([\.\d]*)\s?/si',$this->getUserAgent(),$m) &&
            version_compare($m[1][0],'0.0.6','>=')) {
            return array("client" => "firephp");
        } else
        // Check if FirePHP is installed on client via X-FirePHP-Version header
        if(@preg_match_all('/^([\.\d]*)$/si',$this->getRequestHeader("X-FirePHP-Version"),$m) &&
            version_compare($m[1][0],'0.0.6','>=')) {
            return array("client" => "firephp");
        }
        return false;
    }

    protected function getUserAgent() {
        if(!isset($_SERVER['HTTP_USER_AGENT'])) return false;
        return $_SERVER['HTTP_USER_AGENT'];
    }

    protected function getRequestHeader($Name) {
        $headers = getallheaders();
        if(isset($headers[$Name])) {
            return $headers[$Name];
        } else
        // just in case headers got lower-cased in transport
        if(isset($headers[strtolower($Name)])) {
            return $headers[strtolower($Name)];
        }
        return false;
    }
}

class Insight_Helper__NullMessage {
    public function __call($name, $arguments) {
        return $this;
    }
}


function Insight_Helper__shutdown()
{
    $insight = Insight_Helper::getInstance();
    $insight->getDispatcher()->getChannel()->flush();
}


// auto-initialize based on environment if applicable
function main() {
    if(isset($_SERVER['INSIGHT_CONFIG_PATH'])) {
        Insight_Helper::init($_SERVER['INSIGHT_CONFIG_PATH']);
    }
}

main();
