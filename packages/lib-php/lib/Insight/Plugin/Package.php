<?php

class Insight_Plugin_Package {

    protected $message = null;

    public function setMessage($message) {
        $this->message = $message;
    }

    public function setInfo($info) {
        return $this->message->meta(array(
            "encoder" => "JSON",
            "target" => "info"
        ))->send($info);
    }

}
