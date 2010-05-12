<?php

class Insight_Message {
    
    protected $helper = null;
    
    protected $to = null;
    protected $meta = array();    
    protected $api = null;
    
    
    public function setHelper($helper) {
        $this->helper = $helper;
    }
    
    public function __call($name, $arguments) {

        if($this->api && method_exists($this->api, $name)) {
            $this->api->setMessage($this);
            return call_user_func_array(array($this->api, $name), $arguments);
        }
        if($name=='to') {
            $message = clone $this;
            $message->to = $arguments[0];
            return $message;
        } else
        if($name=='api') {
            $message = clone $this;
            $message->api = $arguments[0];
            return $message;
        } else
        if($name=='meta') {
            $message = clone $this;
            $message->meta = array_merge($message->meta, $arguments[0]);
            return $message;
        }
        throw new Exception("Unknown method: " + $name);
    }
    
    public function send($data) {
        
        $dispatcher = $this->helper->getDispatcher();

        $info = $this->helper->getConfig()->getTargetInfo($this->to);
        $dispatcher->setReceiverID($info['implements']);

        $meta = $this->meta;

        $parts = explode(":", $meta['renderer']);
        $info = $this->helper->getConfig()->getRendererInfo($parts[0]);
        $parts[0] = $info['uid'];
        $meta['renderer'] = implode(":", $parts);

        $dispatcher->send($data, $meta);
    }
}