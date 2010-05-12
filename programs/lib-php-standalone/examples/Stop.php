<?php

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'common.inc.php');

require_once 'FirePHP/Insight.php';


$insight = FirePHP_Insight::getInstance(true);

$insight->setOptions(array(
    'applicationRootPath' => dirname(dirname(__FILE__))
));

$insight->listen(array(
    'authkeys' => array(
        'test-key'
    )
));


// some sample code

foreach( new DirectoryIterator(dirname(__FILE__)) as $item ) {

    if(!$item->isDot() && $item->isFile() &&
       $item->getFilename()!='Index.php' &&
       $item->getFilename()!='common.inc.php' &&
       substr($item->getFilename(),0,5)!='.tmp_') {

        print('<p>'. $item->getFilename() . '</p>');        
    }
}


// stop exectution

$insight->stop();

?>

<p>Open firebug and click on the inspector icon for the request in the <i>Net</i> panel.</p>
