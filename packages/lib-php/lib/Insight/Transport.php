<?php

require_once('Wildfire/Transport.php');

class Insight_Transport extends Wildfire_Transport {

    const TTL = 600;  // 10 minutes
    
    private $config;
    private $server;

    
    public function setConfig($config) {
        $this->config = $config;
    }
    
    public function setServer($server) {
        $this->server = $server;
    }

    public function listen() {
        if($this->server->getRequestHeader('x-insight')!='transport') {
            return;
        }
        $payload = json_decode($_POST['payload'], true);
        $file = $this->getPath($payload['key']);
        if(file_exists($file)) {
            readfile($file);
            
            // delete old files
            // TODO: Only do this periodically
  
            $time = time();
            foreach (new DirectoryIterator($this->getBasePath()) as $fileInfo) {
                if($fileInfo->isDot()) continue;
                if($fileInfo->getMTime() < $time-self::TTL) {
                    unlink($fileInfo->getPathname());
                }
            }
        }
        exit;
    }

    public function getBasePath() {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'insight';
        if(!file_exists($path)) {
            mkdir($path, 0775);
            if(!file_exists($path)) {
                throw new Exception('Unable to create directory at: ' . $path);
            }
        }
        return $path;
    }
    
    public function getPath($key) {
        return $this->getBasePath() . DIRECTORY_SEPARATOR . $key;
    }

    protected function getPointerData($key) {
        return array(
            "url" => $this->getUrl($key),
            "headers" => array(
                "x-insight" => "transport"
            ),
            "payload" => array(
                "key" => $key
            )
        );
    }    
    
    public function getUrl($key) {
        return $this->server->getUrl();
    }

    public function getData($key) {
        // not needed - see listen()
    }

    public function setData($key, $value) {
        $file = $this->getPath($key);
        file_put_contents($file, $value);
        if(!file_exists($file)) {
            throw new Exception("Unable to write data to: " . $file);
        }
    }

}