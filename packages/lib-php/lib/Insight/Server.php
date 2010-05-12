<?php

require_once('Insight/Plugin/FileViewer.php');
require_once('Insight/Plugin/Stop.php');

class Insight_Server
{
    
    private $config = null;
    private $plugins = array();
    
    
    function __construct() {
        $this->registerPlugin(new Insight_Plugin_FileViewer());
        $this->registerPlugin(new Insight_Plugin_Stop());
    }
    
    public function setConfig($config) {
        $this->config = $config;
    }
    
    public function registerPlugin($plugin) {
        $this->plugins[strtolower(get_class($plugin))] = $plugin;
    }

    public function listen() {

// TODO: Listen for server requests

        
        $insightHeader = $this->getRequestHeader("X-Insight");
        
        if($insightHeader) {
            
            // TODO: Authorize
            
            if(!isset($_POST['payload'])) {
                throw new Exception("No payload in POST request");
            }

            try {

                $response = $this->respond(json_decode($_POST['payload'], true));

                if(!$response) {
                    header("HTTP/1.0 204 No Content");
                    header("Status: 204 No Content");
                } else {
                    switch($response['type']) {
                        case 'json':
                            header("Content-Type: application/json");
                            echo(json_encode($response['data']));
                            break;
                        default:
                            echo($response['data']);
                            break;
                    }
                }
            } catch(Exception $e) {
                header("HTTP/1.0 500 Internal Server Error");
                header("Status: 500 Internal Server Error");

                echo($e->getMessage());

                // TODO: Log to FirePHP
            }
            exit;
        }
    }

    protected function respond($payload) {
        if(!$payload['target']) {
            throw new Exception('$payload.target not set');
        }
        if(!$payload['action']) {
            throw new Exception('$payload.action not set');
        }
        if(!isset($this->plugins[strtolower($payload['target'])])) {
            throw new Exception('$payload.target not found in $plugins');
        }
        $plugin = $this->plugins[strtolower($payload['target'])];
        return $plugin->respond($this, $payload['action'], (isset($payload['args']))?$payload['args']:array() );
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
