<?php

require_once('Insight/Config.php');

class Insight_Helper
{
    private static $instance = null;
    
    private static $senderLibrary = null;

    private $config = null;
    private $server = null;
    private $channel = null;
    private $dispatcher = null;
    private $announceReceiver = null;
        
    private $authorized = false;
    private $enabled = false;
    
    private $plugins = array();
    
    
    public static function isInitialized() {
        return !!(self::$instance);
    }

    public static function setSenderLibrary($stringPointer) {
        self::$senderLibrary = $stringPointer;
    }

    public static function init($configPath, $additionalConfig) {
        if(self::$instance) {
            throw new Exception("Insight_Helper already initialized!");
        }

        try {

            // ensure min php version
            if(version_compare(phpversion(), '5.1') == -1) {
                throw new Exception('PHP version 5.1+ required. Your version: ' . phpversion());
            }

            $config = new Insight_Config();
            $config->loadFromFile($configPath, $additionalConfig);
    
            self::$instance = new self();
            self::$instance->setConfig($config);

            if(self::$instance->isClientAuthorized()) {

                self::$instance->authorized = true;

                // ensure cache path works
                $cachePath = $config->getCachePath();
                if(!file_exists($cachePath)) {
                    $baseCachePath = $config->getCachePath(true);
                    if(!is_writable($baseCachePath)) {
                        throw new Exception('Error creating cache path. Insufficient permissions. Directory not writable: ' . $baseCachePath);
                    }
                    if(!mkdir($cachePath, 0775, true)) {
                        throw new Exception('Error creating cache path at: ' . $cachePath);
                    }
                }
                if(!is_dir($cachePath)) {
                    throw new Exception('Cache path not a directory: ' . $cachePath);
                }
                if(!is_writable($cachePath)) {
                    throw new Exception('Cache path not writable: ' . $cachePath);
                }

                // enable output buffering if not enabled
                if(!($ob = ini_get('output_buffering')) || $ob==4096) {
                    // @see http://ca.php.net/manual/en/function.ob-get-level.php
                    if(ob_get_level()<=1) { // ob_start() has not been called prior
                        ob_start();
                    }
                }
    
                // always enable insight for now
                self::$instance->enabled = true;
    
                // flush on shutdown
                register_shutdown_function('Insight_Helper__shutdown');

                // set transport
                require_once('Insight/Transport.php');
                $transport = new Insight_Transport();
                $transport->setConfig($config);
                self::$instance->getChannel()->setTransport($transport);
    
                // initialize server
                require_once('Insight/Server.php');
                self::$instance->server = new Insight_Server();
                self::$instance->server->setConfig($config);
    
                // NOTE: This may stop script execution if a transport data request is detected
                $transport->setServer(self::$instance->server);
                $transport->listen();
    
                // NOTE: This may stop script execution if a server request is detected
                self::$instance->server->listen();
    
                // send package info
                // TODO: Figure out a way to not send this all the time
                //       Could be done via static data structures with checksums where the client announces which
                //       data structures it has by sending the checksum in the request headers
                if($packageInfo = $config->getPackageInfo()) {
                    self::to('package')->setInfo($packageInfo);
                }
                self::to('controller')->setServerUrl(self::$instance->server->getUrl());
    
                // Look for x-insight trigger
                $insight = false;
                if(isset($_GET['x-insight'])) {
                    $insight = $_GET['x-insight'];
                }
                if(isset($_POST['x-insight'])) {
                    $insight = $_POST['x-insight'];
                }
                if($insight=='inspect' || Insight_Server::getRequestHeader('x-insight')=='inspect') {
                    Insight_Helper::to('controller')->triggerInspect();
                }
            }
        } catch(Exception $e) {

            // disable sending of data
            self::$instance->enabled = false;

            header("HTTP/1.0 500 Internal Server Error");
            header("Status: 500 Internal Server Error");
            if(isset(self::$instance->authorized) && self::$instance->authorized) {
                header('x-insight-status: ERROR');
                header('x-insight-status-msg: ' . $e->getMessage());
            }
            if(!Insight_Helper::debug('Initialization Error: ' . $e->getMessage())) {
                throw $e;
            }
        }
        return self::$instance;
    }
    
    private function getChannel() {
        if(!$this->channel) {
            require_once('Wildfire/Channel/HttpHeader.php');
            $this->channel = new Wildfire_Channel_HttpHeader();
        }
        return $this->channel;
    }

    private function newInstance() {
        return new self();
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

    public function getEnabled() {
        return $this->enabled;
    }

    public function getDispatcher() {
        if(!$this->enabled) {
            throw new Exception("Insight is not enabled!");
        }
        if(!$this->dispatcher) {
            require_once('Insight/Dispatcher.php');
            $this->dispatcher = new Insight_Dispatcher();
            $this->dispatcher->setHelper($this);
            $this->dispatcher->setSenderID($this->config->getPackageId() . ((self::$senderLibrary)?'?lib='.self::$senderLibrary:''));
            $this->dispatcher->setChannel($this->getChannel());
        }
        return $this->dispatcher;
    }

    private function getAnnounceReceiver() {
        if(!$this->announceReceiver) {
            require_once('Insight/Receiver/Announce.php');
            $this->announceReceiver = new Insight_Receiver_Announce();
            $this->announceReceiver->setChannel($this->getChannel());
            // parse received headers
            // NOTE: This needs to be moved if we want to support multiple receivers
            $this->getChannel()->parseReceived(getallheaders());
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

        require_once($info['api'] . ".php");
        $class = str_replace("/", "_", $info['api']);

        $api = new $class();
        $message = $message->api($api);
        if(method_exists($api, 'getDefaultMeta')) {
            $message = $message->meta($api->getDefaultMeta());
        }

        return $message;
    }

    public static function plugin($name) {

        $instance = self::getInstance();

        if(!$instance->enabled) {
            return new Insight_Helper__NullMessage();
        }
        
        if(!isset($instance->plugins[$name])) {

            $info = $instance->config->getPluginInfo($name);
    
            require_once($info['api'] . ".php");
            $class = str_replace("/", "_", $info['api']);
    
            $instance->plugins[$name] = new $class();
        }

        return $instance->plugins[$name];
    }

    protected function isClientAuthorized() {

        // verify IP
        $authorized = false;
        $ips = $this->config->getIPs();
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
        if(!$clientInfo || $clientInfo['client']!='insight') {
            // announce installation
            // NOTE: Only an IP match is required for this. If client is announcing itself ($clientInfo) we do NOT send this header!
            // TODO: Use wildfire for this?
            header('x-insight-installation-id: ' . implode('.', array_reverse(explode('.', $_SERVER['SERVER_NAME']))));
            return false;
        }

        // verify client key
        $authorized = false;

        if($clientInfo['client']=='insight') {

            $authkeys = $this->config->getAuthkeys();

            if(count($authkeys)==1 && $authkeys[0]=='*') {
                $authorized = true;
            } else {
                foreach( $authkeys as $authkey ) {
                    if(in_array($authkey, $clientInfo['authkeys'])) {
                        $authorized = true;
                        break;
                    }
                }
            }
        }

        if(!$authorized) {
            // IP matched and client announced itself but authkey does not match
            header('x-insight-status: AUTHKEY_NOT_FOUND');
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

    public static function getRequestHeader($Name) {
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

    public static function debug($message, $type=null) {
        if(!defined('INSIGHT_DEBUG') || constant('INSIGHT_DEBUG')!==true) {
            return false;
        }
        echo '<div style="border: 2px solid black; background-color: red;"> <span style="font-weight: bold;">[INSIGHT]</span> ' . $message . '</div>';
        return true;
    }
}

class Insight_Helper__NullMessage {
    public function __call($name, $arguments) {
        return $this;
    }
}

function Insight_Helper__shutdown() {
    $insight = Insight_Helper::getInstance();

    // only send headers if this was not a transport request
    if(class_exists('Insight_Server') && Insight_Server::getRequestHeader('x-insight')=="transport") {
        return;
    }

    // if disabled do not flush headers
    if(!$insight->getEnabled()) {
        return;
    }

    Insight_Helper::debug('Flushing headers');

    $insight->getDispatcher()->getChannel()->flush(false, true);
}


// auto-initialize based on environment if applicable
function Insight_Helper__main() {
    $additionalConfig = isset($GLOBALS['INSIGHT_ADDITIONAL_CONFIG'])?$GLOBALS['INSIGHT_ADDITIONAL_CONFIG']:false;
    if(defined('INSIGHT_CONFIG_PATH')) {
        Insight_Helper::init(constant('INSIGHT_CONFIG_PATH'), $additionalConfig);
    } else
    if(getenv('INSIGHT_CONFIG_PATH')) {
        Insight_Helper::init(getenv('INSIGHT_CONFIG_PATH'), $additionalConfig);
    }
}

if(isset($GLOBALS['INSIGHT_AUTOLOAD'])?$GLOBALS['INSIGHT_AUTOLOAD']:true) {
    Insight_Helper__main();
}
