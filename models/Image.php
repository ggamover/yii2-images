<?php


/**
 * This is the model class for table "image".
 *
 * @property integer $id
 * @property string $filePath
 * @property integer $itemId
 * @property integer $isMain
 * @property string $modelName
 * @property string $urlAlias
 */

namespace rico\yii2images\models;

use Imagick;
use ImagickException;
use Yii;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\db\ActiveRecord;
use yii\helpers\Inflector;
use yii\helpers\Url;
use yii\helpers\BaseFileHelper;
use rico\yii2images\ModuleTrait;

use claviska\SimpleImage;


/**
 * Image
 * \rico\yii2images\models\Image
 *
 * @property string urlAlias
 * @property int itemId
 * @property string filePath
 * @property string modelName
 * @property string name
 */
class Image extends ActiveRecord
{
    use ModuleTrait;


	/**
	 * @var bool
	 */
	private $helper = false;


	/**
	 * @return bool
	 * @throws Exception
	 * @throws ErrorException
	 */
	public function clearCache(){
        $subDir = $this->getSubDur();

        $dirToRemove = $this->getModule()->getCachePath().DIRECTORY_SEPARATOR.$subDir;

        if(preg_match('/'.preg_quote($this->modelName, '/').'/', $dirToRemove)){
            BaseFileHelper::removeDirectory($dirToRemove);

        }

        return true;
    }

	/**
	 * @return array|string|string[]
	 */
	public function getExtension(){
        $ext = pathinfo($this->getPathToOrigin(), PATHINFO_EXTENSION);
        return $ext;
    }

	/**
	 * @param $size
	 * @return string
	 */
	public function getUrl($size = false){
        $urlSize = ($size) ? '_'.$size : '';
        $url = Url::toRoute([
            '/'.$this->getPrimaryKey().'/images/image-by-item-and-alias',
            'item' => $this->modelName.$this->itemId,
            'dirtyAlias' =>  $this->urlAlias.$urlSize.'.'.$this->getExtension()
        ]);

        return $url;
    }

	/**
	 * @param $size
	 * @return string
	 * @throws Exception
	 */
	public function getPath($size = false){
        $urlSize = ($size) ? '_'.$size : '';
        $base = $this->getModule()->getCachePath();
        $sub = $this->getSubDur();

        $origin = $this->getPathToOrigin();

        $filePath = $base.DIRECTORY_SEPARATOR.
            $sub.DIRECTORY_SEPARATOR.$this->urlAlias.$urlSize.'.'.pathinfo($origin, PATHINFO_EXTENSION);
		if(!file_exists($filePath)){
            $this->createVersion($origin, $size);

            if(!file_exists($filePath)){
                throw new \Exception('Problem with image creating.');
            }
        }

        return $filePath;
    }

	/**
	 * @param $size
	 * @return false|string
	 * @throws Exception
	 */
	public function getContent($size = false){
        return file_get_contents($this->getPath($size));
    }

	/**
	 * @return string
	 * @throws Exception
	 */
	public function getPathToOrigin(){

        $base = $this->getModule()->getStorePath();

        $filePath = $base.DIRECTORY_SEPARATOR.$this->filePath;

        return $filePath;
    }


	/**
	 * @return false|int[]
	 * @throws Exception
	 * @throws ImagickException
	 */
	public function getSizes()
    {
        $sizes = false;
        if($this->getModule()->graphicsLibrary == 'Imagick'){
            $image = new Imagick($this->getPathToOrigin());
            $sizes = $image->getImageGeometry();
        }else{
            $image = new SimpleImage($this->getPathToOrigin());
            $sizes['width'] = $image->get_width();
            $sizes['height'] = $image->get_height();
        }

        return $sizes;
    }

	/**
	 * @param $sizeString
	 * @return array
	 * @throws Exception
	 * @throws ImagickException
	 */
	public function getSizesWhen($sizeString){

        $size = $this->getModule()->parseSize($sizeString);
        if(!$size){
            throw new \Exception('Bad size..');
        }



        $sizes = $this->getSizes();

        $imageWidth = $sizes['width'];
        $imageHeight = $sizes['height'];
        $newSizes = [];
        if(!$size['width']){
            $newWidth = $imageWidth*($size['height']/$imageHeight);
            $newSizes['width'] = intval($newWidth);
            $newSizes['height'] = $size['height'];
        }elseif(!$size['height']){
            $newHeight = intval($imageHeight*($size['width']/$imageWidth));
            $newSizes['width'] = $size['width'];
            $newSizes['height'] = $newHeight;
        }

        return $newSizes;
    }

	/**
	 * @param $imagePath
	 * @param $sizeString
	 * @return SimpleImage|Imagick
	 * @throws Exception
	 * @throws ImagickException
	 */
	public function createVersion($imagePath, $sizeString = false)
    {
        if(strlen($this->urlAlias)<1){
            throw new \Exception('Image without urlAlias!');
        }

        $cachePath = $this->getModule()->getCachePath();
        $subDirPath = $this->getSubDur();
        $fileExtension =  pathinfo($this->filePath, PATHINFO_EXTENSION);

        if($sizeString){
            $sizePart = '_'.$sizeString;
        }else{
            $sizePart = '';
        }

        $pathToSave = $cachePath.'/'.$subDirPath.'/'.$this->urlAlias.$sizePart.'.'.$fileExtension;

        BaseFileHelper::createDirectory(dirname($pathToSave), 0777, true);


        if($sizeString) {
            $size = $this->getModule()->parseSize($sizeString);
        }else{
            $size = false;
        }

            if($this->getModule()->graphicsLibrary == 'Imagick'){
                $image = new Imagick($imagePath);

                $image->setImageCompressionQuality( $this->getModule()->imageCompressionQuality );

                if($size){
                    if($size['height'] && $size['width']){
                        $image->cropThumbnailImage($size['width'], $size['height']);
                    }elseif($size['height']){
                        $image->thumbnailImage(0, $size['height']);
                    }elseif($size['width']){
                        $image->thumbnailImage($size['width'], 0);
                    }else{
                        throw new \Exception('Something wrong with this->module->parseSize($sizeString)');
                    }
                }

                $image->writeImage($pathToSave);
            }else{

                $image = new SimpleImage($imagePath);

                if($size){
                    if($size['height'] && $size['width']){

                        $image->thumbnail($size['width'], $size['height']);
                    }elseif($size['height']){
                        $image->fit_to_height($size['height']);
                    }elseif($size['width']){
                        $image->fit_to_width($size['width']);
                    }else{
                        throw new \Exception('Something wrong with this->module->parseSize($sizeString)');
                    }
                }

                $image->save($pathToSave, $this->getModule()->imageCompressionQuality );
            }

        return $image;

    }


	/**
	 * @param $isMain
	 * @return void
	 */
	public function setMain($isMain = true){
        if($isMain){
            $this->isMain = 1;
        }else{
            $this->isMain = 0;
        }

    }


	/**
	 * @param $size
	 * @return string
	 * @throws Exception
	 */
	public function getMimeType($size = false) {
        return image_type_to_mime_type ( exif_imagetype( $this->getPath($size) ) );
    }


	/**
	 * @return string
	 */
	protected function getSubDur(){
        return Inflector::pluralize($this->modelName).'/'.$this->modelName.$this->itemId;
    }



    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%image}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['filePath', 'itemId', 'modelName', 'urlAlias'], 'required'],
            [['itemId', 'isMain'], 'integer'],
            [['name'], 'string', 'max' => 80],
            [['filePath', 'urlAlias'], 'string', 'max' => 400],
            [['modelName'], 'string', 'max' => 150]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'filePath' => 'File Path',
            'itemId' => 'Item ID',
            'isMain' => 'Is Main',
            'modelName' => 'Model Name',
            'urlAlias' => 'Url Alias',
        ];
    }
}
