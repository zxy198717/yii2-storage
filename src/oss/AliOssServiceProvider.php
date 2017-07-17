<?php
namespace years\storage\oss;

use years\storage\oss\plugins\PutFile;
use years\storage\oss\plugins\PutRemoteFile;
use Yii;
use League\Flysystem\Filesystem;
use OSS\OssClient;

class AliOssServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public static function register()
    {
        return function($config)
        {
            $accessId  = $config['access_id'];
            $accessKey = $config['access_key'];
            $endPoint  = $config['endpoint']; // 默认作为外部节点
            $epInternal= empty($config['endpoint_internal']) ? $endPoint : $config['endpoint_internal']; // 内部节点
            $cdnDomain = empty($config['cdnDomain']) ? '' : $config['cdnDomain'];
            $bucket    = $config['bucket'];
            $ssl       = empty($config['ssl']) ? false : $config['ssl']; 
            $isCname   = empty($config['isCName']) ? false : $config['isCName'];
            $debug     = empty($config['debug']) ? false : $config['debug'];
            
            if($debug) Yii::trace('OSS config:', $config);
            $client  = new OssClient($accessId, $accessKey, $epInternal, $isCname);
            $adapter = new AliOssAdapter($client, $bucket, $endPoint, $ssl, $isCname, $debug, $cdnDomain);
            //Log::debug($client);
            $filesystem =  new Filesystem($adapter);
            
            $filesystem->addPlugin(new PutFile());
            $filesystem->addPlugin(new PutRemoteFile());
            //$filesystem->addPlugin(new CallBack());
            return $filesystem;
        };
    }
}