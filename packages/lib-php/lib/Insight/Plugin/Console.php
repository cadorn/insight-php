<?php

require_once('Insight/Util.php');
require_once('Insight/Plugin/Group.php');


class Insight_Plugin_Console {
    
    protected $temporaryTraceOffset = null;
    protected $traceOffset = 4;
    protected $message = null;
    protected static $groupIndex = 0;


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
    
    public function setTemporaryTraceOffset($offset) {
        $this->temporaryTraceOffset = $offset;
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

    public function filter($filter) {
        return $this->message->meta(array(
            'encoder.filter' => $filter
        ));
    }
    
    public function log($data) {
        $this->message->meta($this->_addFileLineMeta(array(
            'priority' => 'log'
        )))->send($data);
    }

    public function info($data) {
        $this->message->meta($this->_addFileLineMeta(array(
            'priority' => 'info'
        )))->send($data);
    }
    
    public function warn($data) {
        $this->message->meta($this->_addFileLineMeta(array(
            'priority' => 'warn'
        )))->send($data);
    }

    public function error($data) {
        $this->message->meta($this->_addFileLineMeta(array(
            'priority' => 'error'
        ), $data))->send($data);
    }

    public function group($name=null) {
        if(!$name) {
            $name = md5(uniqid() . microtime(true) . (self::$groupIndex++));
        }
        return $this->message->api(new Insight_Plugin_Group())->meta(array(
            'group' => $name
        ));
    }

    public function trace($title) {

        $trace = debug_backtrace();
        if(!$trace) return false;
        array_splice($trace, 0, $this->traceOffset-1);

        $this->message->meta($this->_addFileLineMeta(array(
            'renderer' => 'insight:structures/trace',
            'encoder.depthExtend' => 5
        )))->send(array(
            'title' => $title,
            'trace' => $trace
        ));
    }

    public function table($title, $data, $header=false) {
        
        if(is_object($data)) {
            // convert object to name/value table
            $originalData = $data;
            $data = array();
            foreach( (array)$originalData as $name => $value ) {
                $data[] = array($name, $value);
            }
        } else
        if(is_array($data) && !Insight_Util::is_list($data)) {
            // convert associative array to name/value table
            $originalData = $data;
            $data = array();
            foreach( $originalData as $name => $value ) {
                $data[] = array($name, $value);
            }
        }
        
        $this->message->meta($this->_addFileLineMeta(array(
            'renderer' => 'insight:structures/table',
            'encoder.depthExtend' => 2
        )))->send(array(
            'title' => $title,
            'data' => $data,
            'header' => $header
        ));
    }

    protected function _addFileLineMeta($meta=false, $data=false) {
        if(!$meta) {
            $meta = array();
        }
        if($data!==false && $data instanceof Exception && $this->temporaryTraceOffset==-1) {
            $meta['file'] = $data->getFile();
            $meta['line'] = $data->getLine();
        } else {
            $backtrace = debug_backtrace();
            $offset = $this->traceOffset;
            if($this->temporaryTraceOffset!==null) {
                $offset = $this->temporaryTraceOffset;
                $this->temporaryTraceOffset = null;
            }
            $meta['file'] = $backtrace[$offset]['file'];
            $meta['line'] = $backtrace[$offset]['line'];
        }
        return $meta;
    }
}
