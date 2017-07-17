<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace years\storage\oss\plugins;

use League\Flysystem\Config;
use League\Flysystem\Plugin\AbstractPlugin;
class PutRemoteFile extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'putRemoteFile';
    }
    public function handle($path, $remoteUrl, array $options = []){
        $config = new Config($options);
        if (method_exists($this->filesystem, 'getConfig')) {
            $config->setFallback($this->filesystem->getConfig());
        }
        //Get file stream from remote url
        $resource = fopen($remoteUrl, 'r');
        return (bool)$this->filesystem->getAdapter()->writeStream($path, $resource, $config);
    }
}