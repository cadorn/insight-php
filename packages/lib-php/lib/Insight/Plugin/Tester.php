<?php

class Insight_Plugin_Tester
{
    public function respond($server, $request) {

        if($request->getAction()=='TestClient') {

            FirePHP::to('controller')->triggerClientTest($request->getArguments());

            return array(
                'type' => 'text/plain',
                'data' => "OK"
            );
        }
        return false;
    }
}
