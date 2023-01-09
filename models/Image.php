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
 * @property int isMain
 */
class Image extends ActiveRecord
{
	use ModuleTrait;

	const GRAPHICS_LIB_IMAGICK = 'Imagick';

	/**
	 * @var bool
	 */
	private $helper = false;

	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%image}}';
	}

	/**
	 * @inheritdoc
	 */
	public function rules(): array
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
	public function attributeLabels(): array
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

	/**
	 * @return bool
	 * @throws Exception
	 * @throws ErrorException
	 */
	public function clearCache(): bool
	{
		$subDir = $this->getSubDur();
		$dirToRemove = $this->getModule()->getCachePath().DIRECTORY_SEPARATOR.$subDir;
		if(preg_match('/'.preg_quote($this->modelName, '/').'/', $dirToRemove)){
			BaseFileHelper::removeDirectory($dirToRemove);
		}

		return true;
	}

	/**
	 * @return array|string|string[]
	 * @throws Exception
	 */
	public function getExtension(){
		return pathinfo($this->getPathToOrigin(), PATHINFO_EXTENSION);
	}

	/**
	 * @param bool $size
	 * @return string
	 * @throws Exception
	 */
	public function getUrl(bool $size = false): string
	{
		$urlSize = ($size) ? '_'.$size : '';
		return Url::toRoute([
			'/'.$this->getPrimaryKey().'/images/image-by-item-and-alias',
			'item' => $this->modelName.$this->itemId,
			'dirtyAlias' =>  $this->urlAlias.$urlSize.'.'.$this->getExtension()
		]);
	}

	/**
	 * @param bool $size
	 * @return string
	 * @throws Exception|ImagickException
	 * @throws \Exception
	 */
	public function getPath($size = false): string
	{
		$urlSize = ($size) ? '_'.$size : '';
		$base = $this->getModule()->getCachePath();
		$sub = $this->getSubDur();

		$origin = $this->getPathToOrigin();
		$filePath = $this->getFilePath($base, $sub, $urlSize, $origin);

		if(!file_exists($filePath)){
			$this->createVersion($origin, $size);
			if(!file_exists($filePath)){
				throw new \Exception('Problem with image creating.');
			}
		}

		return $filePath;
	}

	/**
	 * @param string $base - base cache path
	 * @param string $imageSubDir - image sub directory
	 * @param string $widthHeight - image size width and heihgt from url path
	 * @param string $sourcePath - image source path
	 * @return false|string
	 * @throws Exception|ImagickException
	 */
	protected function getFilePath($cachePath, $imageSubDir, $widthHeight, $sourcePath): string
	{
		return $cachePath.DIRECTORY_SEPARATOR.
			$imageSubDir.
			DIRECTORY_SEPARATOR.
			$this->urlAlias.
			$widthHeight.'.'.pathinfo($sourcePath, PATHINFO_EXTENSION);
	}

	/**
	 * @param bool $size
	 * @return false|string
	 * @throws Exception|ImagickException
	 */
	public function getContent(bool $size = false){
		return file_get_contents($this->getPath($size));
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function getPathToOrigin(): string
	{
		$base = $this->getModule()->getStorePath();
		return $base.DIRECTORY_SEPARATOR.$this->filePath;
	}

	/**
	 * @return false|int[]
	 * @throws Exception
	 * @throws ImagickException
	 * @throws \Exception
	 */
	public function getSizes()
	{
		$sizes = false;
		if($this->getModule()->graphicsLibrary == static::GRAPHICS_LIB_IMAGICK){
			$image = new Imagick($this->getPathToOrigin());
			$sizes = $image->getImageGeometry();
		}else{
			$image = new SimpleImage($this->getPathToOrigin());
			$sizes['width'] = $image->getWidth();
			$sizes['height'] = $image->getHeight();
		}

		return $sizes;
	}

	/**
	 * @param $imagePath
	 * @param string|null $sizeString
	 * @return SimpleImage|Imagick
	 * @throws Exception
	 * @throws ImagickException
	 * @throws \Exception
	 */
	public function createVersion($imagePath, $sizeString = null)
	{
		if(strlen($this->urlAlias)<1){
			throw new \Exception('Image without urlAlias!');
		}

		$cachePath = $this->getModule()->getCachePath();
		$subDirPath = $this->getSubDur();
		$fileExtension =  pathinfo($this->filePath, PATHINFO_EXTENSION);
		$sizePart = $sizeString ? '_'.$sizeString : '';

		$pathToSave = $cachePath.'/'.$subDirPath.'/'.$this->urlAlias.$sizePart.'.'.$fileExtension;
		BaseFileHelper::createDirectory(dirname($pathToSave), 0777, true);

		$size = $sizeString ? $this->getModule()->parseSize($sizeString): false;

		if($this->getModule()->graphicsLibrary == static::GRAPHICS_LIB_IMAGICK){
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
					$image->resize(null, $size['height']);
				}elseif($size['width']){
					$image->resize($size['width']);
				}else{
					throw new \Exception('Something wrong with this->module->parseSize($sizeString)');
				}
			}
			$image->toFile($pathToSave, null, $this->getModule()->imageCompressionQuality );
		}

		return $image;
	}


	/**
	 * @param bool $isMain
	 * @return void
	 */
	public function setMain(bool $isMain = true)
	{
		$this->isMain = $isMain;
	}

	/**
	 * @param bool $size
	 * @return string
	 * @throws Exception|ImagickException
	 */
	public function getMimeType(bool $size = false): string
	{
		return image_type_to_mime_type ( exif_imagetype( $this->getPath($size) ) );
	}

	/**
	 * @return string
	 */
	protected function getSubDur(): string
	{
		return Inflector::pluralize($this->modelName).'/'.$this->modelName.$this->itemId;
	}
}
