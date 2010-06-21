<?php

class Insight_Plugin_Controller {
    
    protected $message = null;

    public function setMessage($message) {
        $this->message = $message;
    }

    public function triggerInspect() {
        return $this->message->meta(array(
            "encoder" => "JSON"
        ))->send(array(
            "action" => "inspectRequest"
        ));
    }
    
    public function setServerUrl($url) {
        return $this->message->meta(array(
            "encoder" => "JSON"
        ))->send(array(
            "serverUrl" => $url
        ));
    }

    public function triggerClientTest($payload) {
        return $this->message->meta(array(
            "encoder" => "JSON"
        ))->send(array(
            "action" => "testClient",
            "actionArgs" => array(
                "payload" => $payload
            )
        ));
    }

}
