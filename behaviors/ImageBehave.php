<?php
/**
 * Created by PhpStorm.
 * User: kostanevazno
 * Date: 22.06.14
 * Time: 16:58
 */

namespace rico\yii2images\behaviors;

use rico\yii2images\models\Image;

use yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use rico\yii2images\models;
use yii\helpers\BaseFileHelper;
use \rico\yii2images\ModuleTrait;

/**
 * ImageBehave
 * \rico\yii2images\behaviors\ImageBehave
 */
class ImageBehave extends Behavior
{
    use ModuleTrait;

	/**
	 * @var bool
	 */
	public $createAliasMethod = false;

    /**
     * @var ActiveRecord|null Model class, which will be used for storing image data in db, if not set default class(models/Image) will be used
     */

	/**
	 *
	 * Method copies image file to module store and creates db record.
	 *
	 * @param $absolutePath
	 * @param bool $isMain
	 * @param string $name
	 * @return bool|Image
	 * @throws yii\base\Exception
	 * @throws \Exception
	 */
    public function attachImage(
		$absolutePath,
		bool $isMain = false,
		string $name = ''
	)
    {
        if(!preg_match('#http#', $absolutePath) && !file_exists($absolutePath)){
			throw new \Exception('File not exist! :'.$absolutePath);
        }

        if (!$this->owner->primaryKey) {
            throw new \Exception('Owner must have primaryKey when you attach image!');
        }

        $pictureFileName =
            substr(md5(microtime(true) . $absolutePath), 4, 6)
            . '.' .
            pathinfo($absolutePath, PATHINFO_EXTENSION);
        $pictureSubDir = $this->getModule()->getModelSubDir($this->owner);
        $storePath = $this->getModule()->getStorePath($this->owner);

        $newAbsolutePath = $storePath .
            DIRECTORY_SEPARATOR . $pictureSubDir .
            DIRECTORY_SEPARATOR . $pictureFileName;

        BaseFileHelper::createDirectory($storePath . DIRECTORY_SEPARATOR . $pictureSubDir,
            0775, true);

        copy($absolutePath, $newAbsolutePath);

        if (!file_exists($newAbsolutePath)) {
            throw new \Exception('Cant copy file! ' . $absolutePath . ' to ' . $newAbsolutePath);
        }

        if ($this->getModule()->className === null) {
            $image = new models\Image;
        } else {
            $class = $this->getModule()->className;
            $image = new $class();
        }
        $image->itemId = $this->owner->primaryKey;
        $image->filePath = $pictureSubDir . '/' . $pictureFileName;
        $image->modelName = $this->getModule()->getShortClass($this->owner);
        $image->name = $name;
        $image->urlAlias = $this->getAlias();

        if(!$image->save()){
            return false;
        }

        if (count($image->getErrors()) > 0) {
			$errors = $image->getErrors();
            $ar = array_shift($errors);

            unlink($newAbsolutePath);
            throw new \Exception(array_shift($ar));
        }
        $img = $this->owner->getImage();

        //If main image not exists
        if(
            is_object($img) && get_class($img)=='rico\yii2images\models\PlaceHolder'
            or
            $img == null
            or
            $isMain
        ){
            $this->setMainImage($image);
        }


        return $image;
    }

    /**
     * Sets main image of model
     * @param $img
     * @throws \Exception
     */
    public function setMainImage($img)
    {
        if ($this->owner->primaryKey != $img->itemId) {
            throw new \Exception('Image must belong to this model');
        }
        $counter = 1;
        /* @var $img Image */
        $img->setMain(true);
        $img->urlAlias = $this->getAliasString() . '-' . $counter;
        $img->save();

        $images = $this->owner->getImages();
        foreach ($images as $allImg) {
            if ($allImg->getPrimaryKey() == $img->getPrimaryKey()) {
                continue;
            } else {
                $counter++;
            }

            $allImg->setMain(false);
            $allImg->urlAlias = $this->getAliasString() . '-' . $counter;
            $allImg->save();
        }

        $this->owner->clearImagesCache();
    }

	/**
	 * Clear all images cache (and resized copies)
	 * @return bool
	 * @throws yii\base\ErrorException
	 * @throws yii\base\Exception
	 */
    public function clearImagesCache(): bool
	{
        $cachePath = $this->getModule()->getCachePath();
        $subDirectory = $this->getModule()->getModelSubDir($this->owner);
        $dirToRemove = "{$cachePath}/{$subDirectory}";

        if (preg_match('/' . preg_quote($cachePath, '/') . '/', $dirToRemove)) {
            BaseFileHelper::removeDirectory($dirToRemove);
            return true;
        }

		return false;
    }

	/**
	 * @return yii\db\ActiveQuery
	 * @throws yii\base\Exception
	 */
	protected function getInstance(): yii\db\ActiveQuery
	{
		$className = $this->getModule()->className;
		if ($className) {
			return $className::find();
		}

		return Image::find();
	}

	/**
	 * Returns model images
	 * First image always must be main image
	 * @return array|yii\db\ActiveRecord[]
	 * @throws yii\base\Exception
	 */
    public function getImages(): array
	{
        $imageRecords = $this->getInstance()
			->where($this->getImagesFinder())
			->orderBy(['isMain' => SORT_DESC, 'id' => SORT_ASC])
			->all();

        if(!$imageRecords && $this->getModule()->placeHolderPath){
            return [$this->getModule()->getPlaceHolder()];
        }
        return $imageRecords;
    }

	/**
	 * returns main model image
	 * @return array|null|ActiveRecord
	 * @throws yii\base\Exception
	 */
    public function getImage()
    {
        $image = $this->getInstance()
			->where($this->getImagesFinder(['isMain' => 1]))
			->orderBy(['isMain' => SORT_DESC, 'id' => SORT_ASC])
			->one();

        if(!$image){
            return $this->getModule()->getPlaceHolder();
        }

        return $image;
    }

	/**
	 * returns model image by name
	 * @return array|null|ActiveRecord
	 * @throws yii\base\Exception
	 */
    public function getImageByName($name)
    {
        $image = $this->getInstance()
			->where($this->getImagesFinder(['name' => $name]))
			->orderBy(['isMain' => SORT_DESC, 'id' => SORT_ASC])
			->one();

        if(!$image){
            return $this->getModule()->getPlaceHolder();
        }

        return $image;
    }

	/**
	 * Remove all model images
	 * @throws yii\base\ErrorException
	 * @throws yii\base\Exception
	 */
    public function removeImages(): bool
	{
        $images = $this->owner->getImages();
        if (count($images) < 1) {
            return true;
        } else {
            foreach ($images as $image) {
                $this->owner->removeImage($image);
            }
            $storePath = $this->getModule()->getStorePath();
            $pictureSubDir = $this->getModule()->getModelSubDir($this->owner);
            $dirToRemove = $storePath . DIRECTORY_SEPARATOR . $pictureSubDir;
            BaseFileHelper::removeDirectory($dirToRemove);
        }

		return false;
    }

    /**
     * removes concrete model's image
     * @param Image $img
     * @return bool
	 * @throws \Exception|\Throwable
     */
    public function removeImage(Image $img)
    {
        if ($img instanceof models\PlaceHolder) {
            return false;
        }
        $img->clearCache();

        $storePath = $this->getModule()->getStorePath();

        $fileToRemove = $storePath . DIRECTORY_SEPARATOR . $img->filePath;
        if (preg_match('@\.@', $fileToRemove) and is_file($fileToRemove)) {
            unlink($fileToRemove);
        }
        $img->delete();
        return true;
    }

	/**
	 * @param $additionWhere
	 * @return array
	 * @throws yii\base\Exception
	 */
	private function getImagesFinder($additionWhere = false)
    {
        $base = [
            'itemId' => $this->owner->primaryKey,
            'modelName' => $this->getModule()->getShortClass($this->owner)
        ];

        if ($additionWhere) {
            $base = \yii\helpers\BaseArrayHelper::merge($base, $additionWhere);
        }

        return $base;
    }

    /** Make string part of image's url
     * @return string
     * @throws \Exception
     */
    private function getAliasString()
    {
        if ($this->createAliasMethod) {
            $string = $this->owner->{$this->createAliasMethod}();
            if (!is_string($string)) {
                throw new \Exception("Image's url must be string!");
            } else {
                return $string;
            }

        } else {
            return substr(md5(microtime()), 0, 10);
        }
    }

    /**
     *
     * Обновить алиасы для картинок
     * Зачистить кэш
     */
    private function getAlias()
    {
        $aliasWords = $this->getAliasString();
        $imagesCount = count($this->owner->getImages());

        return $aliasWords . '-' . intval($imagesCount + 1);
    }
}
