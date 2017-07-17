<?php namespace years\storage\qiniu;

use League\Flysystem\Filesystem;
use years\storage\qiniu\plugins\DownloadUrl;
use years\storage\qiniu\plugins\Fetch;
use years\storage\qiniu\plugins\ImageExif;
use years\storage\qiniu\plugins\ImageInfo;
use years\storage\qiniu\plugins\AvInfo;
use years\storage\qiniu\plugins\ImagePreviewUrl;
use years\storage\qiniu\plugins\PersistentFop;
use years\storage\qiniu\plugins\PersistentStatus;
use years\storage\qiniu\plugins\PrivateDownloadUrl;
use years\storage\qiniu\plugins\Qetag;
use years\storage\qiniu\plugins\UploadToken;
use years\storage\qiniu\plugins\PrivateImagePreviewUrl;
use years\storage\qiniu\plugins\VerifyCallback;

class QiniuFilesystemServiceProvider
{
    public static function register()
    {
        return function ($config) {
            if (isset($config['domains'])) {
                $domains = $config['domains'];
            } else {
                $domains = [
                    'default' => $config['domain'],
                    'https' => null,
                    'custom' => null
                ];
            }
            $qiniu_adapter = new QiniuAdapter(
                $config['access_key'],
                $config['secret_key'],
                $config['bucket'],
                $domains,
                $config['notify_url'] ? $config['notify_url'] : null,
                isset($config['access']) ? $config['access'] : 'public'
            );
            $file_system = new Filesystem($qiniu_adapter);
            $file_system->addPlugin(new PrivateDownloadUrl());
            $file_system->addPlugin(new DownloadUrl());
            $file_system->addPlugin(new AvInfo());
            $file_system->addPlugin(new ImageInfo());
            $file_system->addPlugin(new ImageExif());
            $file_system->addPlugin(new ImagePreviewUrl());
            $file_system->addPlugin(new PersistentFop());
            $file_system->addPlugin(new PersistentStatus());
            $file_system->addPlugin(new UploadToken());
            $file_system->addPlugin(new PrivateImagePreviewUrl());
            $file_system->addPlugin(new VerifyCallback());
            $file_system->addPlugin(new Fetch());
            $file_system->addPlugin(new Qetag());

            return $file_system;
        };

    }
}
