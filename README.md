# Yii2 storege - upload behavior for ActiveRecord #
 
This package is one ActiveRecord behavior. It allows you to keep the uploaded file as-is. It support multiple attributes and files. 

Based on `Flysystem`, `qiniu` and `oss` supported. 
 
## Installation ##

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

    php composer.phar require --prefer-dist years/yii2-storage "*"

or add

    "years/yii2-storage": "*"

to the `require` section of your composer.json.

## Configuration ##

Add key `filesystems` to `params.php`

```
return [
	 xxxxx
    'filesystems' => [
    	'default' => 'qiniu',
    	'disks' => [
        	'local' => [
            	'driver' => 'local',
            	'root' => '@webroot/upload',
        	],

        	's3' => [
            	'driver' => 's3',
            	'key' => 'your-key',
            	'secret' => 'your-secret',
            	'region' => 'your-region',
            	'bucket' => 'your-bucket',
        	],

        	'qiniu' => [
            	'driver'  => 'qiniu',
            	'domains' => [
                	'default'   => 'olys54us5.bkt.clouddn.com', //你的七牛域名
                	'https'     => '',         //你的HTTPS域名
                	'custom'    => '',                //你的自定义域名
            	],
            	'access_key'=> 'xxxxx-BCT2',  //AccessKey
            	'secret_key'=> 'xxxx',  //SecretKey
            	'bucket'    => 'xxx',  //Bucket名字
            	'notify_url'=> '',  //持久化处理回调地址
        	],

        	'oss' => [
                'driver'        => 'oss',
                'access_id'     => 'xxxx',
                'access_key'    => 'xxx',
                'bucket'        => 'xxx',
                'endpoint'      => 'oss-cn-hangzhou-internal.aliyuncs.com',
                'isCName'       => false,
                'debug'         => true
        	],
   	 	],
    ],
];


```
 
## UploadBehavior ##

This behavior allow you to add file uploading logic with ActiveRecord behavior.

### Usage ###
Attach the behavior to your model class:

    public function behaviors()
    {
        return [
            [
                'class' => '\years\storage\behaviors\UploadBehavior',
                'attributes' => 'avatar',
                'disk' => 'qiniu',
                'directory' => 'images',
            ],
        ];
    }
    
Add validation rule:

    public function rules()
    {
        return [
            ['avatar', 'file'],   
        ];
    }

Setup proper form enctype:

    $form = \yii\bootstrap\ActiveForm::begin([
        'enableClientValidation' => false,
        'options' => [
            'enctype' => 'multipart/form-data',
        ],
    ]);

File should be uploading fine.

## Licence ##

MIT
