<?php

namespace years\storage;
use yii\di\ServiceLocator;
use years\storage\qiniu\QiniuFilesystemServiceProvider;

class Storage extends ServiceLocator {
    /**
     *
     * @var FilesystemManager 
     */
    public $manager;

    public function init() {
        parent::init();
        
        $this->manager = new FilesystemManager();

        $this->manager->extend("qiniu", QiniuFilesystemServiceProvider::register());
        $this->manager->extend("oss", oss\AliOssServiceProvider::register());
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->manager->$method(...$parameters);
    }
}
