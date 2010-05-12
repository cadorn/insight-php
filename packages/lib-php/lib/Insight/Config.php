<?php

class Insight_Config
{
    protected $defaultConfig = array(
        "schema" => "http://registry.pinf.org/cadorn.org/insight/@meta/schema/insight-app-config/0.1.0",
        "server" => array(
            "path" => "/_INSIGHT_/"
        ),
        "targets" => array(
            "console" => array(
                "implements" => "http://registry.pinf.org/cadorn.org/insight/@meta/receiver/console/page/0",
                "api" => "Insight/Plugin/Page"
            ),
            "request" => array(
                "implements" => "http://registry.pinf.org/cadorn.org/insight/@meta/receiver/console/request/0",
                "api" => "Insight/Plugin/Request"
            ),
            "app" => array(
                "implements" => "http://registry.pinf.org/cadorn.org/insight/@meta/receiver/console/application/0",
                "api" => "Insight/Plugin/Application"
            )
        ),
        "renderers" => array(
            "php" => array(
                "uid" => "http://registry.pinf.org/cadorn.org/renderers/packages/php/0"
            ),
            "http" => array(
                "uid" => "http://registry.pinf.org/cadorn.org/renderers/packages/http/0"
            )
        )
    );
    protected $config = null;

    
    
    public function loadFromFile($file) {
        $this->config = $this->merge($this->defaultConfig, json_decode(file_get_contents($file), true));
        $this->validate($this->config);
    }
    
    public function merge($default, $new) {
        $combined = $default;
        foreach( array("schema", "appid", "client", "server", "targets", "renderers") as $name ) {
            if(isset($new[$name])) {
                if(is_array($new[$name])) {
                    if(!isset($default[$name])) {
                        $default[$name] = array();
                    }
                    $combined[$name] = array_merge_recursive($default[$name], $new[$name]);
                } else {
                    $combined[$name] = $new[$name];
                }
            }
        }
        return $combined;
    }
    
    private function validate($config) {
        if(!isset($config['client'])) {
            throw new Exception('"client" config property not set');
        }
        if(!isset($config['client']['ips'])) {
            throw new Exception('"client.ips" config property not set');
        }
        if(!isset($config['client']['authkeys'])) {
            throw new Exception('"client.authkeys" config property not set');
        }
        if(!isset($config['targets'])) {
            throw new Exception('"targets" config property not set');
        }
        if(!isset($config['renderers'])) {
            throw new Exception('"renderers" config property not set');
        }
        if(!isset($config['server'])) {
            throw new Exception('"server" config property not set');
        }
        if($config['server']===false) {
            // disabled server
        } else {
            if(!isset($config['server']['path'])) {
                throw new Exception('"server.path" config property not set');
            }
        }
    }

    public function getAppID() {
        return $this->config['appid'];
    }

    public function getServerInfo() {
        return $this->config['server'];
    }

    public function getTargets() {
        return $this->config['targets'];
    }
    
    public function getTargetInfo($name) {
        if(!isset($this->config['targets'][$name])) {
            throw new Exception('"targets.'.$name.'" config property not set');
        }
        return $this->config['targets'][$name];
    }

    public function getRendererInfo($name) {
        if(!isset($this->config['renderers'][$name])) {
            throw new Exception('"renderers.'.$name.'" config property not set');
        }
        return $this->config['renderers'][$name];
    }

    public function getClientAuthkeys() {
        return $this->config['client']['authkeys'];
    }

    public function getClientIPs() {
        return $this->config['client']['ips'];
    }
}
