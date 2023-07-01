<?php

namespace rico\yii2images;

use Exception;
use yii;
use rico\yii2images\models\Image;
use rico\yii2images\models\PlaceHolder;
use yii\base\InvalidConfigException;
use yii\helpers\Inflector;

/**
 * Module
 * \rico\yii2images\Module
 */
class Module extends \yii\base\Module
{
	/**
	 * @var string
	 */
	public $imagesStorePath = '@app/web/store';
	/**
	 * @var string
	 */
	public $imagesCachePath = '@app/web/cached';
	/**
	 * @var string
	 */
	public $graphicsLibrary = 'GD';
	/**
	 * @var string
	 */
	public $controllerNamespace = 'rico\yii2images\controllers';
	/**
	 * @var
	 */
	public $placeHolderPath;
	/**
	 * @var bool
	 */
	public $waterMark = false;
	/**
	 * @var
	 */
	public $className;

	/**
	 * @var int
	 */
	public $imageCompressionQuality = 100;

	/**
	 * @return void
	 * @throws Exception
	 */
	public function init()
	{
		parent::init();
		if (!$this->imagesStorePath
			or
			!$this->imagesCachePath
			or
			$this->imagesStorePath == '@app'
			or
			$this->imagesCachePath == '@app'
		)
			throw new Exception('Setup imagesStorePath and imagesCachePath images module properties!');
	}

	/**
	 * @param $item
	 * @param $dirtyAlias
	 * @return array|PlaceHolder|yii\db\ActiveRecord|null
	 * @throws Exception
	 */
	public function getImage($item, $dirtyAlias)
	{
		$params = $this->parseImageAlias($dirtyAlias);
		$alias = $params['alias'];

		$itemId = preg_replace('/[^0-9]+/', '', $item);
		$modelName = preg_replace('/[0-9]+/', '', $item);

		if(empty($this->className)) {
			$imageQuery = Image::find();
		} else {
			$class = $this->className;
			$imageQuery = $class::find();
		}
		$image = $imageQuery
			->where([
				'modelName' => $modelName,
				'itemId' => $itemId,
				'urlAlias' => $alias
			])
			->one();

		if(!$image){
			return $this->getPlaceHolder();
		}

		return $image;
	}

	/**
	 * @return false|string
	 */
	public function getStorePath()
	{
		return Yii::getAlias($this->imagesStorePath);
	}

	/**
	 * @return false|string
	 */
	public function getCachePath()
	{
		return Yii::getAlias($this->imagesCachePath);
	}

	/**
	 * @param $model
	 * @return string
	 */
	public function getModelSubDir($model): string
	{
		$modelName = $this->getShortClass($model);
		return Inflector::pluralize($modelName).'/'. $modelName . $model->getPrimaryKey();
	}

	/**
	 * @param $obj
	 * @return mixed|string
	 */
	public function getShortClass($obj)
	{
		$className = get_class($obj);
		if (preg_match('@\\\\([\w]+)$@', $className, $matches)) {
			$className = $matches[1];
		}

		return $className;
	}

	/**
	 *
	 * Parses size string
	 * For instance: 400x400, 400x, x400
	 *
	 * @param $notParsedSize
	 * @return array|null
	 * @throws Exception
	 */
	public function parseSize($notParsedSize): ?array
	{
		$sizeParts = explode('x', $notParsedSize);
		$part1 = (isset($sizeParts[0]) and $sizeParts[0] != '');
		$part2 = (isset($sizeParts[1]) and $sizeParts[1] != '');

		if ($part1 && $part2) {
			if (intval($sizeParts[0]) > 0
				&&
				intval($sizeParts[1]) > 0
			) {
				$size = [
					'width' => intval($sizeParts[0]),
					'height' => intval($sizeParts[1])
				];
			} else {
				$size = null;
			}
		} elseif ($part1 && !$part2) {
			$size = [
				'width' => intval($sizeParts[0]),
				'height' => null
			];
		} elseif (!$part1 && $part2) {
			$size = [
				'width' => null,
				'height' => intval($sizeParts[1])
			];
		} else {
			throw new Exception('Something bad with size, sorry!');
		}

		return $size;
	}

	/**
	 * @param $parameterized
	 * @return array
	 * @throws Exception
	 */
	public function parseImageAlias($parameterized): array
	{
		$params = explode('_', $parameterized);

		if (count($params) == 1) {
			$alias = $params[0];
			$size = null;
		} elseif (count($params) == 2) {
			$alias = $params[0];
			$size = $this->parseSize($params[1]);
			if (!$size) {
				$alias = null;
			}
		} else {
			$alias = null;
			$size = null;
		}

		return ['alias' => $alias, 'size' => $size];
	}

	/**
	 * @throws InvalidConfigException
	 * @return PlaceHolder|null
	 */
	public function getPlaceHolder(): ?PlaceHolder
	{
		if (!file_exists(Yii::getAlias($this->placeHolderPath)))
			throw new InvalidConfigException("PlaceHolder image file not found in {$this->placeHolderPath}");

		return $this->placeHolderPath ? new PlaceHolder(): null;
	}
}
