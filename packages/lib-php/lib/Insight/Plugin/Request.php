<?php

require_once('Insight/Plugin/Console.php');
require_once('Insight/Plugin/Timeline.php');

class Insight_Plugin_Request {
    
    
    protected $message = null;
    
    public function setMessage($message) {
        $this->message = $message;
    }
/*
    public function getDefaultMeta() {
        return array(
            'renderer' =>'php:variable'
        );
    }
*/
    public function console($name) {
        return $this->message->api(new Insight_Plugin_Console())->meta(array(
            'target' => 'console/' . $name
        ));
    }

    public function timeline($name) {
        return $this->message->api(new Insight_Plugin_Timeline())->meta(array(
            'target' => 'timeline/' . $name
        ));
    }
}