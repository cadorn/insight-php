<?php

require_once('Wildfire/Channel/FlushListener.php');

class Insight_Dispatcher implements Wildfire_Channel_FlushListener
{
    const PROTOCOL_ID = 'http://registry.pinf.org/cadorn.org/wildfire/@meta/protocol/component/0.1.0';
    
    private $senderID = 'http://registry.pinf.org/cadorn.org/insight/packages/lib-php/';
    private $receiverID = null;
    
    private $channel = null;
    private $messageFactory = null;
    private $encoder = null;
    
//    protected $_registeredLanguagePacks = array();

    
    protected function getSenderID() {
        return $this->senderID;
    }
    
    public function setReceiverID($id) {
        $this->receiverID = $id;
    }
    
    protected function getReceiverID() {
        if(!$this->receiverID) {
            throw new Exception('receiverID not set');
        }
        return $this->receiverID;
    }
    
    public function setChannel($channel)
    {
        if(is_string($channel)) {
            require_once 'Wildfire/Channel/' . $channel . '.php';
            $class = 'Wildfire_Channel_' . $channel;
            $channel = new $class();
        }
        return $this->channel = $channel;
    }

/*
    public function registerTemplatePack($info, $channel=null) {
        $key = md5(serialize($info));
        if(in_array($key, $this->_registeredLanguagePacks)) {
            return;
        }
        if(!isset($info['catalog']) && !isset($info['location'])) {
            throw new Exception("'catalog' nor 'location' provided!");
        }
        $message = $this->getNewMessage(null);
        $message->setProtocol(self::PROTOCOL_ID);
        $message->setSender($this->getSenderID());
        $message->setReceiver('http://registry.pinf.org/cadorn.org/github/fireconsole/@meta/receiver/template-pack/0.1.0');
        $message->setData(json_encode(array(
            "action" => "require",
            "locator" => $info
        )));
        if(!$channel) {
            $channel = $this->getChannel();
        }
        $channel->enqueueOutgoing($message);
        
        $this->_registeredLanguagePacks[] = $key;
    }
*/

    public function setMessageFactory($messageFactory)
    {
        $this->messageFactory = $messageFactory;
        return true;
    }
    
    public function getChannel()
    {
        if(!$this->channel) {
            require_once 'Wildfire/Channel/HttpHeader.php';
            $this->channel = new Wildfire_Channel_HttpHeader();
        }
        $this->channel->addFlushListener($this);
/*        
        $this->registerTemplatePack(array(
            'catalog' => 'http://registry.pinf.org/cadorn.org/github/fireconsole-template-packs/packages/catalog.json',
            'name' => 'lang-php',
            'revision' => 'master'
        ), $this->channel);
*/
        return $this->channel;
    }

    /**
     * @interface Wildfire_Channel_FlushListener
     */
    public function channelFlushed(Wildfire_Channel $channel)
    {
        if($channel===$this->getChannel()) {
            // reset registered template packs
//            $this->_registeredLanguagePacks = array();
        }
    }
    
    private function getNewMessage($meta)
    {
        if(!$this->messageFactory) {
            require_once 'Wildfire/Message.php';
            return new Wildfire_Message();
        }
        return $this->messageFactory->newMessage($meta);
    }

    public function getEncoder()
    {
        if(!$this->encoder) {
            require_once 'Insight/Encoder/Default.php';
            $this->encoder = new Insight_Encoder_Default();
        }
        return $this->encoder;
    }

    public function send($data, $meta=array())
    {
        list($data, $meta) = $this->getEncoder()->encode($data, $meta);
        return $this->sendRaw(
            $data,
            ($meta)?json_encode($meta):''
        );
    }

    public function sendRaw($data, $meta='')
    {
        $message = $this->getNewMessage($meta);
        $message->setProtocol(self::PROTOCOL_ID);
        $message->setSender($this->getSenderID());
        $message->setReceiver($this->getReceiverID());
        if($meta) $message->setMeta($meta);
        $message->setData($data);
        $this->getChannel()->enqueueOutgoing($message);
        return true;
    }
}
