<?php

require_once('Insight/Plugin/Group.php');

class Insight_Plugin_Selective extends Insight_Plugin_Group {
    
    
    public function open() {
        $this->message->meta($this->_addFileLineMeta(array(
            'group.start' => true
        )))->send(true);
        return $this->message;
    }

    public function close() {
        $this->message->meta($this->_addFileLineMeta(array(
            'group.end' => true
        )))->send(true);
        return $this->message;
    }
}
