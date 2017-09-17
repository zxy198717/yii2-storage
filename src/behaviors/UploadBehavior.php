<?php
namespace years\storage\behaviors;

use Closure;
use League\Flysystem\Filesystem;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\validators\FileValidator;
use yii\validators\ImageValidator;
use yii\web\UploadedFile;
/**
 * UploadBehavior automatically uploads file and fills the specified attribute
 * with a value of the name of the uploaded file.
 *
 * To use UploadBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use years\storage\behaviors\UploadBehavior;
 *
 * function behaviors()
 * {
 *     return [
 *         [
 *             'class' => UploadBehavior::className(),
 *             'attributes' => 'file',
 *             'scenarios' => ['insert', 'update'],
 *             'disk' => 'qiniu',
 *             'directory' => 'file',
 *         ],
 *     ];
 * }
 * ```
 *
 */
class UploadBehavior extends Behavior
{
    /**
     * @event Event an event that is triggered after a file is uploaded.
     */
    const EVENT_AFTER_UPLOAD = 'afterUpload';
    /**
     * @var array the attributes which holds the attachment.
     */
    public $attributes = [];
    /**
     * @var array the scenarios in which the behavior will be triggered
     */
    public $scenarios = [];
    /**
     * @var storage disk
     */
    public $disk;
    /**
     * @var string the directory in which to save files. default in disk root.
     */
    public $directory;
    /**
     * @var boolean|callable generate a new unique name for the file
     * set true or anonymous function takes the old filename and returns a new name.
     * @see self::generateFileName()
     */
    public $generateNewName = true;
    /**
     * @var boolean If `true` current attribute file will be deleted
     */
    public $unlinkOnSave = true;
    /**
     * @var boolean If `true` current attribute file will be deleted after model deletion.
     */
    public $unlinkOnDelete = true;
    /**
     * @var boolean $deleteTempFile whether to delete the temporary file after saving.
     */
    public $deleteTempFile = true;

    /**
     * @var UploadedFile[] array of UploadedFile the uploaded file instance.
     */
    private $_files = [];

    /**
     * @var Filesystem
     */
    private $_fileSystem;
    
    private $_temp_attributes =[];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if (empty($this->attributes)) {
            throw new InvalidConfigException('The "attributes" property must be set.');
        }
        if (Yii::$app->has("storage")) {
            $this->_fileSystem = Yii::$app->storage->disk($this->disk);

            if(!$this->_fileSystem) {
                throw new InvalidConfigException('The "disk" property must be set with supported drivers. please check config in `config/filesystems.php');
            }
        }
    }
    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            BaseActiveRecord::EVENT_AFTER_VALIDATE => 'afterValidate',
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }
    /**
     * This method is invoked before validation starts.
     */
    public function beforeValidate()
    {
        /** @var BaseActiveRecord $model */

        $model = $this->owner;

        if (empty($this->scenarios) || in_array($model->scenario, $this->scenarios)) {
            foreach ($this->attributes as $attribute) {
                if (count($fs = UploadedFile::getInstances($model, $attribute)) > 0) {
                    $validators = $model->getActiveValidators($attribute);
                    $find_file_validator = false;
                    foreach ($validators ?: []  as $validator) {
                        if ($validator instanceof FileValidator) {
                            $find_file_validator = true;
                            $model->$attribute = $validator->maxFiles == 1 ? $fs[0] : $fs;
                        } elseif($validator instanceof ImageValidator) {
                            $find_file_validator = true;
                            $model->$attribute = $fs[0];
                        }
                    }
                    if(!$find_file_validator) {
                        $model->$attribute = count($fs) == 1 ? $fs[0] : $fs;
                    }
                } else {
                    $value = $model->$attribute;
                    if (! ($model->$attribute == '__DEL__' || (is_array($value) && count($value) > 0 && $value[0] == "__DEL__"))) {
                        $model->$attribute = $model->getOldAttribute($attribute);
                    } else {
                        $this->_temp_attributes[$attribute] = '__DEL__';
                        $model->$attribute = '';
                    }
                }
            }
        }
    }

    public function afterValidate() {
        $model = $this->owner;
        if ($model->hasErrors()) {
            foreach ($this->attributes as $attribute) {
                if (isset($this->_temp_attributes[$attribute])) {
                    $model->{$attribute} = "";
                } else {
                    $model->$attribute = $model->getOldAttribute($attribute);
                }
            }
        }
    }

    protected function prepareAttribute($attribute) {

        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (count($fs = UploadedFile::getInstances($model, $attribute)) > 0) {
            foreach ($fs as $file) {
                if ($file instanceof UploadedFile) {
                    $file->name = $this->getFileName($file);
                    $files[] = $file;
                }
            }
        }

        if(isset($this->_temp_attributes[$attribute])) {
            $model->{$attribute} = "";
        } else {
            unset($model->{$attribute});
        }

        if (isset($files) && count($files) > 0) {
            $values = collect($files)->map(function($file) {
                return $this->getDirectoryPath($file->name);
            });

            if (count($values) == 1) {
                $model->$attribute = $values[0];
            } else {
                $model->$attribute = implode(',', $values->toArray());
            }

            if (!$model->getIsNewRecord() && $model->isAttributeChanged($attribute)) {
                if ($this->unlinkOnSave === true) {
                    $this->delete($attribute, true);
                }
            }

            $this->_files = array_merge($this->_files, $files);
        }
    }

    /**
     * This method is called at the beginning of inserting or updating a record.
     */
    public function beforeSave()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (empty($this->scenarios) || in_array($model->scenario, $this->scenarios)) {
            foreach ($this->attributes as $attribute) {
                $this->prepareAttribute($attribute);
            }
        } else {
            if (!$model->getIsNewRecord() ) {
                foreach ($this->attributes as $attribute) {
                    if ( $model->isAttributeChanged($attribute) && $this->unlinkOnSave === true)
                        $this->delete($attribute, true);
                }
            }
        }
    }
    /**
     * This method is called at the end of inserting or updating a record.
     * @throws \yii\base\InvalidParamException
     */
    public function afterSave()
    {
        if (count($this->_files) > 0) {
            foreach ($this->_files as $file) {
                $this->_fileSystem->put($this->getDirectoryPath($file->name), file_get_contents($file->tempName));
            }
            $this->afterUpload();
        }
    }
    /**
     * This method is invoked after deleting a record.
     */
    public function afterDelete()
    {
        foreach ($this->attributes as $attribute) {
            if ($this->unlinkOnDelete && $attribute) {
                $this->delete($attribute);
            }
        }
    }

    public function getDirectoryPath($fileName) {
        $path = $this->resolvePath($this->directory);
        if (empty($path)) {
            return $fileName;
        }
        return $fileName ? $path . '/' . $fileName : null;
    }

    /**
     * Replaces all placeholders in path variable with corresponding values.
     */
    protected function resolvePath($path)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        return preg_replace_callback('/{([^}]+)}/', function ($matches) use ($model) {
            $name = $matches[1];
            $attribute = ArrayHelper::getValue($model, $name);
            if (is_string($attribute) || is_numeric($attribute)) {
                return $attribute;
            } else {
                return $matches[0];
            }
        }, $path);
    }
    /**
     * Deletes old file.
     * @param string $attribute
     * @param boolean $old
     */
    protected function delete($attribute, $old = false)
    {
        $model = $this->owner;
        $path = ($old === true) ? $model->getOldAttribute($attribute) : $model->$attribute;

        //Try to delete
        try {
            if($path) {
                if (is_string($path)) {
                    $path = explode(",", $path);
                }
                foreach ((array) $path as $p) {
                    $this->_fileSystem->delete($p);
                }
            }
        } catch(\Exception $exception) {

        }
    }
    /**
     * @param UploadedFile $file
     * @return string
     */
    protected function getFileName($file)
    {
        if ($this->generateNewName) {
            return $this->generateNewName instanceof Closure
                ? call_user_func($this->generateNewName, $file)
                : $this->generateFileName($file);
        } else {
            return $this->sanitize($file->name);
        }
    }
    /**
     * Replaces characters in strings that are illegal/unsafe for filename.
     *
     * #my*  unsaf<e>&file:name?".png
     *
     * @param string $filename the source filename to be "sanitized"
     * @return boolean string the sanitized filename
     */
    public static function sanitize($filename)
    {
        return str_replace([' ', '"', '\'', '&', '/', '\\', '?', '#'], '-', $filename);
    }
    /**
     * Generates random filename.
     * @param UploadedFile $file
     * @return string
     */
    protected function generateFileName($file)
    {
        return uniqid() . '.' . $file->extension;
    }
    /**
     * This method is invoked after uploading a file.
     * The default implementation raises the [[EVENT_AFTER_UPLOAD]] event.
     * You may override this method to do postprocessing after the file is uploaded.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    protected function afterUpload()
    {
        $this->owner->trigger(self::EVENT_AFTER_UPLOAD);
    }
}
