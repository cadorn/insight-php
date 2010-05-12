<?php

class Insight_Plugin_Console {
    
    protected $message = null;

    public function setMessage($message) {
        $this->message = $message;
    }

    public function label($label) {
        return $this->message->meta(array(
            'label' => $label
        ));
    }

    public function renderer($renderer) {
        return $this->message->meta(array(
            'renderer' => $renderer
        ));
    }

    public function log($data) {
        return $this->message->meta(array(
            'priority' => 'log'
        ))->send($data);
    }
    
    public function info($data) {
        $this->message->meta(array(
            'priority' => 'info'
        ))->send($data);
    }
    
    public function warn($data) {
        $this->message->meta(array(
            'priority' => 'warn'
        ))->send($data);
    }
    
    public function error($data) {
        $this->message->meta(array(
            'priority' => 'error'
        ))->send($data);
    }
    
}
