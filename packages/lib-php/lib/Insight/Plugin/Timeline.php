<?php

class Insight_Plugin_Timeline {
    
    protected $message = null;

    public function setMessage($message) {
        $this->message = $message;
    }

    public function renderer($renderer) {
        return $this->message->meta(array(
            'renderer' => $renderer
        ));
    }

    public function set($name, $value) {
        return $this->message->send(array(
            'name' => $name,
            'value' => $value
        ));
    }
}
