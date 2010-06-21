<?php

class Insight_Plugin_Tester
{
    public function respond($server, $action, $args) {

        if($action=='TestClient') {

            FirePHP::to('controller')->triggerClientTest($args);

            return array(
                'type' => 'text/plain',
                'data' => "OK"
            );
        }
        return false;
    }
}
