<?php
/**
 * Created by PhpStorm.
 * User: kostanevazno
 * Date: 05.08.14
 * Time: 18:21
 *
 * TODO: check that placeholder is enable in module class
 * override methods
 */

namespace rico\yii2images\models;

/**
 * TODO: check path to save and all image method for placeholder
 */

use yii;

/**
 * PlaceHolder
 * \rico\yii2images\models\PlaceHolder
 */
class PlaceHolder extends Image
{
	/**
	 * @var string
	 */
	private $modelName = '';
	/**
	 * @var string
	 */
	private $itemId = '';
	/**
	 * @var string
	 */
	public $filePath = 'placeHolder.png';
	/**
	 * @var string
	 */
	public $urlAlias = 'placeHolder';

	/**
	 * @throws yii\base\Exception
	 */
	public function __construct()
	{
		$this->filePath = basename(Yii::getAlias($this->getModule()->placeHolderPath));
	}

	/**
	 * @return string
	 * @throws yii\base\Exception
	 */
	public function getPathToOrigin():string
	{
		$url = Yii::getAlias($this->getModule()->placeHolderPath);
		if (!$url) {
			throw new \Exception('PlaceHolder image must have path setting!');
		}
		return $url;
	}

	/**
	 * @return string
	 */
	protected function getSubDur(): string
	{
		return $this->urlAlias;
	}

	/**
	 * @param $isMain
	 * @return void
	 * @throws yii\base\Exception
	 */
	public function setMain($isMain = true)
	{
		throw new yii\base\Exception('You must not set placeHolder as main image!');
	}

}

