<?php

class Insight_Plugin_Stop
{
    public function respond($server, $action, $args) {
        
        if($action=='Get') {

            return array(
                'type' => 'json',
                'data' => array('this' => 'is the stop info')
            );

        }

        return false;
    }
}
