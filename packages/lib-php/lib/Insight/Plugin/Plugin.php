<?php

require_once('Insight/Plugin/API.php');
require_once('Insight/Plugin/Plugin/Message.php');

class Insight_Plugin_Plugin extends Insight_Plugin_API {

    public function removeAll() {
        $this->message->meta(array(
            'encoder' => 'JSON',
            'action' => 'removeAll'
        ))->send(true);
    }

    public function plugin($alias) {
        return $this->message->meta(array(
            'alias' => $alias
        ));
    }

    public function register($options) {

        if(!isset($this->message->meta['alias'])) {
            throw new Exception('Plugin alias not set! Use ->plugin("<alias>")-> first');
        }

        if(!isset($options['container'])) {
            throw new Exception('Container not set');
        }

        $program = $this->loadProgram($options['class'], $options['file']);

        $program->setAlias($this->message->meta['alias']);
        $program->setContainerName($options['container']);

        if(isset($options['forceReload']) && $options['forceReload']) {
            $program->setForceReload(true);
        }

        $this->message->meta(array(
            'encoder' => 'JSON',
            'action' => 'register'
        ))->send($program->getInsightRegistrationMessage());
    }

    public function sendSimpleMessage($message) {
        $this->message->meta(array(
            'encoder' => 'JSON',
            'action' => 'simpleMessage'
        ))->send($message);
    }

    public function show() {
        $this->message->meta(array(
            'encoder' => 'JSON',
            'action' => 'show'
        ))->send(true);
    }

    public function respond($server, $request) {

        if($request->getAction()=='GetProgram') {

            $args = $request->getArguments();

            $program = $this->loadProgram($args['controllerClass'], $args['controllerFile']);

            $program->setContainerName($args['container']);
            $program->setAlias($args['alias']);

            if(isset($args['forceReload']) && $args['forceReload']) {
                $program->setForceReload(true);
            }

            return array(
                'type' => 'application/javascript',
                'data' => $program->getWrappedProgram()
            );
        } else
        if($request->getAction()=='MessageProgram') {

            $args = $request->getArguments();

            $program = $this->loadProgram($args['controllerClass'], $args['controllerFile']);

            $program->setContainerName($args['container']);
            $program->setAlias($args['alias']);

            $program->onMessage(new Insight_Plugin_Plugin_Message($args['message']));

            return array(
                'type' => 'text/plain',
                'data' => 'OK'
            );
        }
        return array(
            'type' => 'error',
            'status' => '403'
        );
    }

    protected function loadProgram($class, $file=null) {

        if(!class_exists($class)) {
            if(!isset($file)) {
                throw new Exception('File not set. Needed because class not found.');
            }
            if(!is_file($file) || !is_readable($file)) {
                throw new Exception('File not accessible: ' . $file);
            }
            if(!require_once($file)) {
                throw new Exception('Error while requiring file: ' . $file);
            }
            if(!class_exists($class)) {
                throw new Exception('Class "' . $class . '" not declared in file: ' . $file);
            }
        }

        $reflectionClass = new ReflectionClass($class);

        // Ensure class is declared in a directory we have access to
        if(!Insight_Helper::getInstance()->getServer()->canServeFile($reflectionClass->getFileName())) {
            throw new Exception('Class "' . $class . '" in file "' . $reflectionClass->getFileName() . ' cannot be access remotely. You need to configure acces with ["implements"]["cadorn.org/insight/@meta/config/0"]["paths"].');
        }

        $program = new $class();

        if(!is_a($program, 'Insight_Program_JavaScript')) {
            throw new Exception('Class "' . $class . '" in file "' . $reflectionClass->getFileName() . '" does not inherit from Insight_Program_JavaScript');
        }
        
        return $program;
    }
}
