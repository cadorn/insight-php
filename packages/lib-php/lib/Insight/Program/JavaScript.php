<?php

require_once('Insight/Util.php');

abstract class Insight_Program_JavaScript {

    protected $alias = null;
    protected $containerName = null;
    protected $forceReload = false;

    public function setAlias($alias) {
        $this->alias = $alias;
    }

    public function getAlias() {
        return $this->alias;
    }

    public function setContainerName($name) {
        $this->containerName = $name;
    }

    public function getContainerName() {
        return $this->containerName;
    }

    public function setForceReload($forceReload) {
        $this->forceReload = $forceReload;
    }

    public function getInsightRegistrationMessage() {
        $reflectionClass = new ReflectionClass(get_class($this));
        return array(
            'id' => Insight_Util::getInstallationId() . '/' . get_class($this) . '/' . $this->containerName,
            'controllerClass' => get_class($this),
            'controllerFile' => $reflectionClass->getFileName(),
            'programRootPath' => $this->_getProgramRootPath(),
            // TODO: Only send the files hash upon request
            'programFilesMTimeHash' => $this->_getProgramFilesMTimeHash(),
            'forceReload' => $this->forceReload,
            'container' => $this->containerName,
            'alias' => $this->alias,
            'options' => $this->getOptions()
        );
    }

    protected function _getProgramRootPath() {
        $file = $this->getProgramRootPath();
        if(!is_dir($file) || !is_readable($file)) {
            throw new Exception('Program root path "' . $file . '" does not exist or is not readable.');
        }
        // Ensure program is declared in a directory we have access to
        if(!Insight_Helper::getInstance()->getServer()->canServeFile($file)) {
            throw new Exception('Program "' . $file . '" cannot be access remotely. You need to configure acces with ["implements"]["cadorn.org/insight/@meta/config/0"]["paths"].');
        }
        $descriptorFile = $file . '/package.json';
        if(!is_file($descriptorFile) || !is_readable($descriptorFile)) {
            throw new Exception('Package descriptor for program not accessbile: ' . $descriptorFile);
        }
        $descriptor = json_decode(file_get_contents($descriptorFile), true);
        if(!json_decode(file_get_contents($descriptorFile))) {
            throw new Exception('Package descriptor for program not valid JSON: ' . $descriptorFile);
        }
        if(!isset($descriptor['mappings'])) {
            throw new Exception('Package descriptor must declare "mappings": ' . $descriptorFile);
        }
        if(!isset($descriptor['implements'])) {
            throw new Exception('Package descriptor must declare "implements": ' . $descriptorFile);
        }
        if(!isset($descriptor['implements']['cadorn.org/insight/@meta/plugin/0'])) {
            throw new Exception('Package descriptor must declare "implements" -> "cadorn.org/insight/@meta/plugin/0": ' . $descriptorFile);
        }
        if(!isset($descriptor['implements']['cadorn.org/insight/@meta/plugin/0']['main'])) {
            $descriptor['implements']['cadorn.org/insight/@meta/plugin/0']['main'] = 'main';
        }
        return $file;
    }

    public function getProgramRootPath() {
        $reflectionClass = new ReflectionClass(get_class($this));
        $file = dirname($reflectionClass->getFileName()) . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $this->containerName;
        if(!file_exists($file)) {
            $file = dirname(dirname($reflectionClass->getFileName())) . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $this->containerName;
        }
        return $file;
    }
    
    protected function _getProgramFilesMTimeHash() {
        $dir = $this->_getProgramRootPath();
        $stats = array();
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $path ) {
            if($path->isFile()) {
                if(substr($path->getBasename(), 0, 1) != '.') {
                    $stats[] = $path->getMTime();
                }
            }
        }
        if($this->forceReload) {
            $stats[] = microtime(true);
        }
        return md5(implode(':', $stats));
    }

    public function getWrappedProgram() {
        $rootPath = $this->getProgramRootPath();

        $payloadDelimiter = '[|:NEXT-SECTION:|]';

        $payload = array();
        $header = array(
            'sections' => array(
                'descriptor' => array(),
                'modules' => array(),
                'css' => array(),
                'images' => array()
            )
        );

        // add package.json for program
        $section = file_get_contents($rootPath . DIRECTORY_SEPARATOR . 'package.json');
        $payload[] = $section;
        $header['sections']['descriptor'][$this->containerName] = strlen($section);

        // wrap all JS modules
        // TODO: Parse for require() in modules to only include used modules
        // TODO: Determine lib dir based on package.json
        $libRootPath = $rootPath . DIRECTORY_SEPARATOR . 'lib';
        $section = false;
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($libRootPath)) as $path ) {
            if($path->isFile() && substr($path->getBasename(),-3,3)=='.js') {
                $moduleId = $this->containerName . '/' . str_replace(array('\\\\', '\\'), '/', substr($path->getPathname(), strlen($libRootPath)+1, -3));
                $payload[] = $section = file_get_contents($path->getPathname());
                $header['sections']['modules'][$moduleId] = strlen($section);
            }
        }

        // add css files if found
        $cssRootPath = $rootPath . DIRECTORY_SEPARATOR . 'resources';
        if(is_dir($cssRootPath)) {
            $section = false;
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cssRootPath)) as $path ) {
                if($path->isFile() && substr($path->getBasename(),-4,4)=='.css') {
                    $cssPath = str_replace(array('\\\\', '\\'), '/', substr($path->getPathname(), strlen($cssRootPath)+1));
                    $payload[] = $section = file_get_contents($path->getPathname());
                    $header['sections']['css'][$cssPath] = strlen($section);
                }
            }
        }

        // add image files if found
        $imagesRootPath = $rootPath . DIRECTORY_SEPARATOR . 'resources';
        if(is_dir($imagesRootPath)) {
            $section = false;
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imagesRootPath)) as $path ) {
                if($path->isFile() && substr($path->getBasename(),-4,4)=='.png') {
                    $imagePath = str_replace(array('\\\\', '\\'), '/', substr($path->getPathname(), strlen($imagesRootPath)+1));
                    $payload[] = $section = base64_encode(file_get_contents($path->getPathname()));
                    $header['sections']['images'][$imagePath] = strlen($section);
                }
            }
        }
        
        return json_encode($header) . $payloadDelimiter . implode($payloadDelimiter, $payload);
    }

    public function sendSimpleMessage($message) {
        FirePHP::to('plugin')->plugin($this->alias)->sendSimpleMessage($message);
    }

    public function onMessage($message) {
    }

    public function getOptions() {
        return array();
    }
}
